<?php

namespace Tests\Feature;

use App\Models\AjusteInventario;
use App\Models\Producto;
use App\Models\User;
use App\Services\KardexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConsumoInternoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['acceso-ajustes', 'acceso-inventario'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['acceso-ajustes', 'acceso-inventario']);
        $this->actingAs($this->user);
    }

    private function producto(int $stock = 10, string $destino = 'consumo_interno'): Producto
    {
        return Producto::factory()->conExistencias($stock)->create([
            'nombre' => 'Shampoo industrial',
            'destino' => $destino,
        ]);
    }

    private function payload(Producto $producto, int $cantidad, ?string $motivo = 'Consumo interno para servicios de lavado.'): array
    {
        return [
            'tipo' => 'consumo_interno',
            'motivo' => $motivo,
            'lineas' => [
                ['producto_id' => $producto->id, 'cantidad' => $cantidad],
            ],
        ];
    }

    public function test_consumo_interno_descuenta_con_kardex_y_trazabilidad(): void
    {
        // Feature: consumo-interno-insumos, Property 1: salida controlada y trazable.
        $producto = $this->producto(10);

        $this->post(route('ajustes.store'), $this->payload($producto, 2))
            ->assertRedirect()
            ->assertSessionHas('success');

        $ajuste = AjusteInventario::first();
        $this->assertSame('consumo_interno', $ajuste->tipo);

        $this->assertSame(8, $producto->fresh()->stock);
        $this->assertDatabaseHas('movimientos_kardex', [
            'producto_id' => $producto->id,
            'tipo' => 'salida',
            'fuente' => 'consumo_interno',
            'origen_id' => $ajuste->correlativo,
            'cantidad' => 2,
            'stock_antes' => 10,
            'stock_despues' => 8,
            'usuario_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('detalle_ajustes_inventario', [
            'ajuste_id' => $ajuste->id,
            'producto_id' => $producto->id,
            'cantidad' => -2,
        ]);
        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'ajustes',
            'accion' => 'crear ajuste',
            'usuario_id' => $this->user->id,
        ]);
    }

    public function test_cantidad_positiva_se_normaliza_a_salida(): void
    {
        $producto = $this->producto(10);

        // El formulario envía cantidades positivas; el servicio las niega.
        $this->post(route('ajustes.store'), $this->payload($producto, 3))
            ->assertRedirect();

        $this->assertSame(7, $producto->fresh()->stock);
    }

    public function test_motivo_obligatorio_y_stock_suficiente(): void
    {
        $producto = $this->producto(2);

        $this->post(route('ajustes.store'), $this->payload($producto, 1, ''))
            ->assertSessionHasErrors('motivo');
        $this->assertSame(0, AjusteInventario::count());

        $this->post(route('ajustes.store'), $this->payload($producto, 5))
            ->assertSessionHas('error');
        $this->assertSame(0, AjusteInventario::count());
        $this->assertSame(2, $producto->fresh()->stock);
    }

    public function test_merma_y_dano_siguen_intactos(): void
    {
        // Feature: consumo-interno-insumos, Property 2: merma/daño diferenciados.
        $this->assertContains('consumo_interno', KardexService::fuentesValidas());
        $this->assertArrayHasKey('consumo_interno', AjusteInventario::TIPOS);

        foreach (['merma', 'daño'] as $tipo) {
            $producto = $this->producto(10);
            $payload = $this->payload($producto, -1, 'Merma operativa de prueba.');
            $payload['tipo'] = $tipo;

            $this->post(route('ajustes.store'), $payload)->assertRedirect();

            $this->assertSame(9, $producto->fresh()->stock);
            $this->assertDatabaseHas('movimientos_kardex', [
                'producto_id' => $producto->id,
                'tipo' => 'salida',
                'fuente' => $tipo,
                'cantidad' => 1,
            ]);
        }
    }

    public function test_producto_comercial_admite_salida_por_consumo(): void
    {
        // Los productos de doble uso (destino comercial) también pueden
        // consumirse internamente sin cambiar su clasificación.
        $producto = $this->producto(10, 'comercial');

        $this->post(route('ajustes.store'), $this->payload($producto, 2))
            ->assertRedirect();

        $this->assertSame(8, $producto->fresh()->stock);
        $this->assertSame('comercial', $producto->fresh()->destino);
    }

    public function test_producto_destino_por_defecto_y_edicion(): void
    {
        $producto = Producto::factory()->conExistencias(5)->create();

        $this->assertSame('comercial', $producto->fresh()->destino);

        $this->put(route('productos.update', $producto), [
            'nombre' => $producto->nombre,
            'categoria_id' => $producto->categoria_id,
            'marca_id' => $producto->marca_id,
            'precio_compra' => 10,
            'precio_venta' => 20,
            'destino' => 'consumo_interno',
        ])->assertRedirect();

        $this->assertSame('consumo_interno', $producto->fresh()->destino);
        $this->assertSame('Consumo interno', Producto::etiquetaDestino('consumo_interno'));

        $this->get(route('productos.index'))
            ->assertOk()
            ->assertSee('Consumo interno');
    }

    public function test_index_ajustes_filtra_y_muestra_consumo(): void
    {
        $producto = $this->producto(10);
        $this->post(route('ajustes.store'), $this->payload($producto, 1));

        $this->get(route('ajustes.index', ['tipo' => 'consumo_interno']))
            ->assertOk()
            ->assertSee('Consumo Interno');
    }
}
