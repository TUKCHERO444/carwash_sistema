<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de la alerta de stock bajo en el buscador del panel de venta.
 *
 * El endpoint /ventas/buscar-productos expone `stock_bajo` para que el
 * vendedor vea en el detalle de la venta cuándo un producto está en alerta,
 * reutilizando la misma regla del módulo de productos: la alerta se activa
 * cuando ya se ha consumido el 75% del inventario del ciclo vigente.
 *
 * Feature: ventas-panel-alerta-stock-bajo
 */
class VentaBuscarProductoStockAlertaTest extends TestCase
{
    use RefreshDatabase;

    private Role $rol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rol = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'acceso-ventas', 'guard_name' => 'web']);
    }

    private function crearUsuarioConPermiso(): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($this->rol);
        $usuario->givePermissionTo('acceso-ventas');

        return $usuario;
    }

    private function crearProducto(int $stock, int $inventario): Producto
    {
        return Producto::create([
            'nombre' => 'Producto de prueba',
            'precio_compra' => 10.00,
            'precio_venta' => 20.00,
            'stock' => $stock,
            'inventario' => $inventario,
            'activo' => true,
        ]);
    }

    /**
     * Feature: ventas-panel-alerta-stock-bajo, Property 1: buscar-productos expone stock_bajo
     *
     * Para cualquier producto activo con inventario I >= 1 y stock S (>= 1), el
     * JSON de /ventas/buscar-productos SHALL incluir `stock_bajo` con
     * stock_bajo == ((I - S) >= ceil(I * 0.75)).
     */
    public function test_property_1_buscar_productos_expone_stock_bajo(): void
    {
        $usuario = $this->crearUsuarioConPermiso();
        $faker = FakerFactory::create();

        for ($i = 0; $i < 50; $i++) {
            $inventario = $faker->numberBetween(1, 200);
            $stock = $faker->numberBetween(1, $inventario * 2);

            $producto = $this->crearProducto($stock, $inventario);

            $response = $this->actingAs($usuario)
                ->getJson('/ventas/buscar-productos?q='.urlencode($producto->nombre))
                ->assertOk();

            $json = collect($response->json())->firstWhere('id', $producto->id);

            $this->assertNotNull($json, "Property 1 failed at iteration {$i}: producto no aparece en búsqueda");

            $esperado = ($inventario - $stock) >= (int) ceil($inventario * 0.75);
            $this->assertSame($stock, $json['stock']);
            $this->assertSame($esperado, $json['stock_bajo'], "Property 1 failed at iteration {$i}: inventario={$inventario}, stock={$stock}, esperado={$esperado}");

            $producto->delete();
        }
    }

    /**
     * Feature: ventas-panel-alerta-stock-bajo, Property 2: solo productos con stock exponen alerta
     *
     * El endpoint solo considera productos activos con stock > 0. Un producto
     * sin stock NO SHALL aparecer en la búsqueda del panel de venta.
     */
    public function test_property_2_productos_sin_stock_no_aparecen(): void
    {
        $usuario = $this->crearUsuarioConPermiso();
        $faker = FakerFactory::create();

        for ($i = 0; $i < 20; $i++) {
            $inventario = $faker->numberBetween(1, 50);

            $producto = $this->crearProducto(0, $inventario);

            $response = $this->actingAs($usuario)
                ->getJson('/ventas/buscar-productos?q='.urlencode($producto->nombre))
                ->assertOk();

            $json = collect($response->json())->firstWhere('id', $producto->id);

            $this->assertNull($json, "Property 2 failed at iteration {$i}: producto sin stock no debe aparecer");

            $producto->delete();
        }
    }
}
