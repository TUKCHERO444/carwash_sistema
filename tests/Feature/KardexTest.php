<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\CambioAceite;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Venta;
use App\Services\KardexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KardexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Categoria $categoria;

    private Marca $marca;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['acceso-ventas', 'acceso-inventario', 'acceso-auditoria'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();

        // Categoría y marca son obligatorias: se crean una vez por test.
        $this->categoria = Categoria::create(['nombre' => 'Aceites y Lubricantes']);
        $this->marca = Marca::create(['nombre' => 'Motul']);
    }

    private function giveAll(): void
    {
        $this->user->givePermissionTo(['acceso-ventas', 'acceso-inventario', 'acceso-auditoria']);
    }

    private function producto(int $stock = 10): Producto
    {
        return Producto::create([
            'nombre' => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta' => 35.00,
            'stock' => $stock,
            'inventario' => $stock,
            'activo' => true,
            'categoria_id' => $this->categoria->id,
            'marca_id' => $this->marca->id,
        ]);
    }

    /**
     * Registra un movimiento con una fuente concreta, para probar el filtro.
     */
    private function movimiento(Producto $producto, string $fuente, string $origenId): MovimientoKardex
    {
        return MovimientoKardex::create([
            'producto_id' => $producto->id,
            'tipo' => $fuente === 'compra' || $fuente === 'inventario' ? 'entrada' : 'salida',
            'fuente' => $fuente,
            'origen_id' => $origenId,
            'cantidad' => 2,
            'stock_antes' => 10,
            'stock_despues' => 8,
            'usuario_id' => $this->user->id,
            'fecha_movimiento' => now(),
        ]);
    }

    // ─── Venta: salida + compensación ──────────────────────────────────────

    public function test_venta_store_registers_kardex_salida_with_vta_origen(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();
        Caja::factory()->abierta()->create();

        $producto = $this->producto(10);

        $this->post(route('ventas.store'), [
            'subtotal' => 70.00,
            'total' => 70.00,
            'metodo_pago' => 'efectivo',
            'productos' => [
                ['producto_id' => $producto->id, 'cantidad' => 2, 'precio_unitario' => 35.00, 'subtotal' => 70.00],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('movimientos_kardex', [
            'producto_id' => $producto->id,
            'tipo' => 'salida',
            'fuente' => 'venta',
            'origen_id' => 'VTA-0001',
            'cantidad' => 2,
            'stock_antes' => 10,
            'stock_despues' => 8,
            'usuario_id' => $this->user->id,
        ]);

        $this->assertEquals(8, $producto->fresh()->stock);
    }

    public function test_venta_destroy_registers_compensating_entrada(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();
        Caja::factory()->abierta()->create();

        $producto = $this->producto(10);
        $venta = $this->post(route('ventas.store'), [
            'subtotal' => 70.00,
            'total' => 70.00,
            'metodo_pago' => 'efectivo',
            'productos' => [
                ['producto_id' => $producto->id, 'cantidad' => 2, 'precio_unitario' => 35.00, 'subtotal' => 70.00],
            ],
        ])->assertRedirect();

        $ventaId = Venta::first()->id;

        $this->delete(route('ventas.destroy', $ventaId))->assertRedirect();

        $this->assertDatabaseHas('movimientos_kardex', [
            'producto_id' => $producto->id,
            'tipo' => 'entrada',
            'fuente' => 'venta',
            'origen_id' => 'VTA-0001',
            'cantidad' => 2,
            'stock_antes' => 8,
            'stock_despues' => 10,
        ]);

        $this->assertEquals(10, $producto->fresh()->stock);
    }

    // ─── Producto: el alta ya no genera entrada de inventario ───────────────
    //
    // Feature: compras-ingreso-mercaderia, Requisito 3: un producto que entra al
    // catálogo no recibió mercadería, así que su alta no genera movimiento.
    // La reposición se registra desde la compra (Fase 3) o desde el modal de
    // reposición, que sí mantiene su entrada de inventario.

    public function test_producto_store_no_registers_kardex(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();

        $this->post(route('productos.store'), [
            'nombre' => 'Refrigerante Azul',
            'precio_compra' => 15.00,
            'precio_venta' => 25.00,
            'categoria_id' => $this->categoria->id,
            'marca_id' => $this->marca->id,
        ])->assertRedirect();

        $producto = Producto::where('nombre', 'Refrigerante Azul')->firstOrFail();

        $this->assertSame(0, $producto->stock);
        $this->assertSame(0, $producto->inventario);
        $this->assertDatabaseCount('movimientos_kardex', 0);
    }

    public function test_producto_store_ignora_inventario_enviado_sin_kardex(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();

        $this->post(route('productos.store'), [
            'nombre' => 'Refrigerante Verde',
            'precio_compra' => 15.00,
            'precio_venta' => 25.00,
            'inventario' => 6,
            'categoria_id' => $this->categoria->id,
            'marca_id' => $this->marca->id,
        ])->assertRedirect();

        $producto = Producto::where('nombre', 'Refrigerante Verde')->firstOrFail();

        $this->assertSame(0, $producto->stock);
        $this->assertDatabaseCount('movimientos_kardex', 0);
    }

    public function test_producto_update_stock_registers_kardex_entrada_inventario(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();

        $producto = $this->producto(5);

        $this->patchJson(route('productos.updateStock', $producto), [
            'cantidad_adicional' => 4,
        ])->assertJson(['success' => true, 'nuevo_stock' => 9]);

        $this->assertDatabaseHas('movimientos_kardex', [
            'producto_id' => $producto->id,
            'tipo' => 'entrada',
            'fuente' => 'inventario',
            'origen_id' => 'INV-0001',
            'cantidad' => 4,
            'stock_antes' => 5,
            'stock_despues' => 9,
        ]);
    }

    // ─── Cambio de aceite: confirmación registra salida con placa ──────────

    public function test_cambio_aceite_confirmacion_registers_kardex_salida(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();
        Caja::factory()->abierta()->create();

        $trabajador = Trabajador::create(['nombre' => 'Mecánico Test', 'estado' => true]);
        $cliente = Cliente::create(['placa' => 'ABC123', 'nombre' => 'Juan']);
        $producto = $this->producto(10);

        $pendiente = CambioAceite::create([
            'cliente_id' => $cliente->id,
            'trabajador_id' => $trabajador->id,
            'user_id' => $this->user->id,
            'fecha' => now()->toDateString(),
            'precio' => 70.00,
            'total' => 70.00,
            'estado' => 'pendiente',
        ]);
        $pendiente->productos()->attach($producto->id, [
            'cantidad' => 2,
            'precio' => 35.00,
            'total' => 70.00,
        ]);

        $this->post(route('cambio-aceite.procesarConfirmacion', $pendiente), [
            'placa' => 'ABC123',
            'fecha' => now()->toDateString(),
            'trabajadores_ids' => [$trabajador->id],
            'precio' => 70.00,
            'total' => 70.00,
            'metodo_pago' => 'efectivo',
            'productos' => [
                ['producto_id' => $producto->id, 'cantidad' => 2, 'precio' => 35.00, 'total' => 70.00],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('movimientos_kardex', [
            'producto_id' => $producto->id,
            'tipo' => 'salida',
            'fuente' => 'cambio_aceite',
            'origen_id' => 'ABC123',
            'cantidad' => 2,
            'stock_antes' => 10,
            'stock_despues' => 8,
        ]);

        $this->assertEquals(8, $producto->fresh()->stock);
    }

    // ─── Auditoría: controlador y permisos ─────────────────────────────────

    public function test_auditoria_index_requires_acceso_auditoria(): void
    {
        // Sin permiso de auditoría (solo autenticado + acceso-ventas).
        // La app convierte la UnauthorizedException de Spatie en un redirect al dashboard.
        $this->actingAs($this->user);
        $this->user->givePermissionTo('acceso-ventas');

        $this->get(route('kardex.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_auditoria_index_lists_movimientos_with_filters(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();

        $producto = $this->producto(10);

        MovimientoKardex::create([
            'producto_id' => $producto->id,
            'tipo' => 'salida',
            'fuente' => 'venta',
            'origen_id' => 'VTA-0001',
            'cantidad' => 2,
            'stock_antes' => 10,
            'stock_despues' => 8,
            'usuario_id' => $this->user->id,
            'fecha_movimiento' => now(),
        ]);

        $this->get(route('kardex.index', ['fuente' => 'venta']))
            ->assertOk()
            ->assertSee('movimientos');
    }

    public function test_auditoria_por_producto_returns_movimientos(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();

        $producto = $this->producto(10);

        MovimientoKardex::create([
            'producto_id' => $producto->id,
            'tipo' => 'salida',
            'fuente' => 'venta',
            'origen_id' => 'VTA-0001',
            'cantidad' => 2,
            'stock_antes' => 10,
            'stock_despues' => 8,
            'usuario_id' => $this->user->id,
            'fecha_movimiento' => now(),
        ]);

        $this->get(route('kardex.porProducto', $producto))
            ->assertOk()
            ->assertSee('VTA-0001');
    }

    // ─── Filtro por fuente del movimiento ────────────────────────────────

    /**
     * Regresión: las compras sí registraban movimiento pero el filtro de fuente
     * no offered la opción, así que era imposible listar solo compras.
     */
    public function test_filtro_fuente_aisla_compras_de_las_ventas(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();

        $producto = $this->producto(10);
        $this->movimiento($producto, 'compra', 'CMP-9001');
        $this->movimiento($producto, 'venta', 'VTA-9001');

        $this->get(route('kardex.index', ['fuente' => 'compra']))
            ->assertOk()
            ->assertSee('CMP-9001')
            ->assertDontSee('VTA-9001');
    }

    /**
     * Toda fuente que el sistema puede registrar debe ser filtrable.
     */
    public function test_cada_fuente_registrable_es_filtrable(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();

        $producto = $this->producto(10);

        // Un movimiento por cada origen, con un correlativo que lo identifica.
        $origenes = [];
        foreach (KardexService::fuentesValidas() as $i => $fuente) {
            $origenes[$fuente] = 'OR-'.$i;
            $this->movimiento($producto, $fuente, 'OR-'.$i);
        }

        // Filtrar por cada fuente devuelve solo ese movimiento.
        foreach ($origenes as $fuente => $origen) {
            $otros = array_diff($origenes, [$origen]);

            $this->get(route('kardex.index', ['fuente' => $fuente]))
                ->assertOk()
                ->assertSee($origen);

            foreach ($otros as $otro) {
                $this->get(route('kardex.index', ['fuente' => $fuente]))
                    ->assertDontSee($otro);
            }
        }
    }

    /**
     * El desplegable ofrece exactamente las fuentes del catálogo, con etiqueta legible.
     */
    public function test_selector_de_fuente_ofrece_todos_los_origenes(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();

        $response = $this->get(route('kardex.index'))->assertOk();

        foreach (KardexService::FUENTES as $valor => $etiqueta) {
            $response->assertSee('<option value="'.$valor.'"', false);
            $response->assertSee($etiqueta);
        }
    }

    /**
     * La columna Fuente muestra la etiqueta, no el valor crudo de la BD.
     */
    public function test_columna_fuente_muestra_la_etiqueta_legible(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();

        $producto = $this->producto(10);
        $this->movimiento($producto, 'conteo_fisico', 'AJU-9001');

        $this->get(route('kardex.index', ['fuente' => 'conteo_fisico']))
            ->assertOk()
            ->assertSee('Conteo físico')
            ->assertDontSee('>conteo_fisico<', false);
    }

    /**
     * Una fuente desconocida en la URL (typo o versión anterior) no rompe el
     * listado: se interpreta como "sin filtro".
     */
    public function test_fuente_desconocida_se_trata_como_sin_filtro(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();

        $producto = $this->producto(10);
        $this->movimiento($producto, 'compra', 'CMP-9001');
        $this->movimiento($producto, 'venta', 'VTA-9001');

        $this->get(route('kardex.index', ['fuente' => 'fuente_inventada']))
            ->assertOk()
            ->assertSee('CMP-9001')
            ->assertSee('VTA-9001');
    }

    public function test_registrar_movimiento_rechaza_una_fuente_invalida(): void
    {
        $this->actingAs($this->user);
        $producto = $this->producto(10);

        $this->expectException(\InvalidArgumentException::class);

        app(KardexService::class)->registrarSalida($producto, 1, 10, 'fuente_inventada', 'X-1');
    }

    /**
     * El CHECK de PostgreSQL congela la lista de fuentes para que la migración no
     * cambie de significado. Este test ata esa lista al catálogo de la app: si se
     * agrega una fuente y no se actualiza la migración, PostgreSQL la rechazaría.
     */
    public function test_check_de_postgres_lista_las_mismas_fuentes_que_el_catalogo(): void
    {
        $ruta = database_path('migrations/2026_09_27_090000_completar_fuentes_movimientos_kardex_table.php');
        $contenido = file_get_contents($ruta);

        preg_match('/FUENTES_PERMITIDAS\s*=\s*\[(.*?)\];/s', $contenido, $bloque);
        preg_match_all("/'([^']+)'/", $bloque[1] ?? '', $coincidencias);

        $congeladas = $coincidencias[1];

        $this->assertNotEmpty($congeladas, 'No se pudo leer la lista congelada de la migración.');
        $this->assertEqualsCanonicalizing(KardexService::fuentesValidas(), $congeladas);
    }
}
