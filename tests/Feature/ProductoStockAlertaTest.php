<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de la alerta de stock bajo del módulo de productos.
 *
 * La alerta se activa cuando ya se ha consumido el 75% del inventario del
 * ciclo vigente: `inventario - stock >= ceil(inventario * 0.75)`.
 * Con inventario 0 no hay ciclo de referencia, por lo que nunca alerta.
 *
 * Feature: productos-alerta-stock-bajo
 */
class ProductoStockAlertaTest extends TestCase
{
    use RefreshDatabase;

    private Role $rol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rol = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
    }

    private function crearAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole($this->rol);

        return $admin;
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
     * Feature: productos-alerta-stock-bajo, Property 1: Umbral del 75% consumido
     *
     * Para cualquier inventario I (>= 1) y cualquier stock S, esta_en_alerta
     * SHALL ser true si y solo si (I - S) >= ceil(I * 0.75).
     */
    public function test_property_1_umbral_del_75_por_ciento_consumido(): void
    {
        $faker = FakerFactory::create();

        for ($i = 0; $i < 100; $i++) {
            $inventario = $faker->numberBetween(1, 200);
            $stock = $faker->numberBetween(0, $inventario * 2);

            $esperado = ($inventario - $stock) >= (int) ceil($inventario * 0.75);
            $producto = $this->crearProducto($stock, $inventario);

            $this->assertSame(
                $esperado,
                $producto->esta_en_alerta,
                "Property 1 failed at iteration {$i}: inventario={$inventario}, stock={$stock}, esperado={$esperado}"
            );

            $producto->delete();
        }
    }

    /**
     * Feature: productos-alerta-stock-bajo, Property 2: Producto recién creado nunca alerta
     *
     * Para cualquier inventario I (>= 1), un producto recién creado tiene
     * stock == I, por lo que el consumo es 0 y esta_en_alerta SHALL ser false
     * (regresión del badge inmediato al crear).
     */
    public function test_property_2_recien_creado_nunca_alerta(): void
    {
        $faker = FakerFactory::create();

        for ($i = 0; $i < 100; $i++) {
            $inventario = $faker->numberBetween(1, 200);
            $producto = $this->crearProducto($inventario, $inventario);

            $this->assertFalse(
                $producto->esta_en_alerta,
                "Property 2 failed at iteration {$i}: inventario={$inventario}, stock==inventario no debe alertar"
            );

            $producto->delete();
        }
    }

    /**
     * Feature: productos-alerta-stock-bajo, Property 3: Inventario cero nunca alerta
     *
     * Para cualquier stock S, un producto con inventario = 0 no tiene ciclo de
     * referencia y esta_en_alerta SHALL ser false para todo S.
     */
    public function test_property_3_inventario_cero_nunca_alerta(): void
    {
        $faker = FakerFactory::create();

        for ($i = 0; $i < 100; $i++) {
            $stock = $faker->numberBetween(0, 50);
            $producto = $this->crearProducto($stock, 0);

            $this->assertFalse(
                $producto->esta_en_alerta,
                "Property 3 failed at iteration {$i}: inventario=0, stock={$stock} no debe alertar"
            );

            $producto->delete();
        }
    }

    /**
     * Feature: productos-alerta-stock-bajo, Property 4: Restock desactiva la alerta
     *
     * Para cualquier producto con inventario I >= 1 que parta en alerta, tras
     * renovar stock con cantidad C > 0, stock == inventario == S + C y la
     * alerta SHALL quedar desactivada (consumo 0 en el nuevo ciclo).
     */
    public function test_property_4_restock_desactiva_la_alerta(): void
    {
        $admin = $this->crearAdmin();
        $faker = FakerFactory::create();

        for ($i = 0; $i < 100; $i++) {
            $inventario = $faker->numberBetween(4, 200);
            // Asegurar un stock que parta en estado de alerta (consumo >= 75%)
            $stockMaxAlerta = $inventario - (int) ceil($inventario * 0.75);
            $stock = $faker->numberBetween(0, $stockMaxAlerta);
            $cantidad = $faker->numberBetween(1, 20);

            $producto = $this->crearProducto($stock, $inventario);
            $this->assertTrue(
                $producto->esta_en_alerta,
                "Property 4 failed at iteration {$i}: inventario={$inventario}, stock={$stock} debe partir en alerta"
            );

            $this->actingAs($admin)
                ->patchJson(route('productos.updateStock', $producto), ['cantidad_adicional' => $cantidad])
                ->assertOk();

            $producto->refresh();
            $this->assertSame($stock + $cantidad, $producto->stock);
            $this->assertFalse(
                $producto->esta_en_alerta,
                "Property 4 failed at iteration {$i}: stock={$producto->stock} == inventario={$producto->inventario} no debe alertar"
            );

            $producto->delete();
        }
    }

    /**
     * Feature: productos-alerta-stock-bajo, Property 5: buscar() expone stock_bajo
     *
     * Para cualquier producto con inventario I >= 1 y stock S, el JSON de
     * /productos/buscar SHALL incluir `inventario` y `stock_bajo` con
     * stock_bajo == ((I - S) >= ceil(I * 0.75)).
     */
    public function test_property_5_buscar_expone_stock_bajo(): void
    {
        $admin = $this->crearAdmin();
        $faker = FakerFactory::create();

        for ($i = 0; $i < 100; $i++) {
            $inventario = $faker->numberBetween(1, 200);
            $stock = $faker->numberBetween(0, $inventario * 2);

            $producto = $this->crearProducto($stock, $inventario);

            $response = $this->actingAs($admin)
                ->getJson('/productos/buscar?q='.urlencode($producto->nombre))
                ->assertOk();

            $json = collect($response->json('productos'))->firstWhere('id', $producto->id);

            $this->assertNotNull($json, "Property 5 failed at iteration {$i}: producto no aparece en búsqueda");

            $esperado = ($inventario - $stock) >= (int) ceil($inventario * 0.75);
            $this->assertSame($inventario, $json['inventario']);
            $this->assertSame($esperado, $json['stock_bajo']);

            $producto->delete();
        }
    }

    /**
     * Feature: productos-alerta-stock-bajo, Property 6: La vista index es coherente
     *
     * Para cualquier producto recién creado (stock == inventario, sin alerta),
     * la vista index NO SHALL renderizar el badge data-stock-badge y el número
     * NO SHALL llevar la clase de color rojo. Para un producto en alerta,
     * la vista SHALL incluir badge y número en rojo (regresión del badge
     * visible sin número rojo introducido por hidden + inline-flex en Tailwind v4).
     */
    public function test_property_6_vista_index_coherente(): void
    {
        $admin = $this->crearAdmin();
        $faker = FakerFactory::create();

        for ($i = 0; $i < 50; $i++) {
            $inventario = $faker->numberBetween(1, 200);

            // Producto recién creado: sin alerta
            $nuevo = $this->crearProducto($inventario, $inventario);
            $html = $this->actingAs($admin)->get(route('productos.index'))->assertOk()->getContent();
            preg_match('/data-stock-value="'.$nuevo->id.'"(.*?)<\/td>/s', $html, $m);
            $this->assertNotEmpty($m[1] ?? '', "Property 6 failed at iteration {$i}: fila del producto nuevo no hallada");
            $this->assertStringNotContainsString('data-stock-badge', $m[1], "Property 6 failed at iteration {$i}: badge no deve aparecer en producto nuevo");
            $this->assertStringContainsString('text-gray-700', $m[1], "Property 6 failed at iteration {$i}: número debe ser gris en producto nuevo");

            // Producto en alerta (consumo >= 75%)
            $stockAlerta = $inventario - (int) ceil($inventario * 0.75);
            $enAlerta = $this->crearProducto($stockAlerta, $inventario);
            $html = $this->actingAs($admin)->get(route('productos.index'))->assertOk()->getContent();
            preg_match('/data-stock-value="'.$enAlerta->id.'"(.*?)<\/td>/s', $html, $m2);
            $this->assertNotEmpty($m2[1] ?? '', "Property 6 failed at iteration {$i}: fila del producto en alerta no hallada");
            $this->assertStringContainsString('data-stock-badge', $m2[1], "Property 6 failed at iteration {$i}: badge debe aparecer en producto en alerta");
            $this->assertStringContainsString('text-red-700', $m2[1], "Property 6 failed at iteration {$i}: número debe ser rojo en producto en alerta");

            $nuevo->delete();
            $enAlerta->delete();
        }
    }
}
