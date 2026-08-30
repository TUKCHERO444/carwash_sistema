<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KardexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['acceso-ventas', 'acceso-inventario', 'acceso-auditoria'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();
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

    // ─── Producto: entrada INV ─────────────────────────────────────────────

    public function test_producto_store_registers_kardex_entrada_inventario(): void
    {
        $this->actingAs($this->user);
        $this->giveAll();

        $this->post(route('productos.store'), [
            'nombre' => 'Refrigerante Azul',
            'precio_compra' => 15.00,
            'precio_venta' => 25.00,
            'inventario' => 6,
        ])->assertRedirect();

        $producto = Producto::where('nombre', 'Refrigerante Azul')->first();

        $this->assertDatabaseHas('movimientos_kardex', [
            'producto_id' => $producto->id,
            'tipo' => 'entrada',
            'fuente' => 'inventario',
            'origen_id' => 'INV-0001',
            'cantidad' => 6,
            'stock_antes' => 0,
            'stock_despues' => 6,
        ]);
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
}
