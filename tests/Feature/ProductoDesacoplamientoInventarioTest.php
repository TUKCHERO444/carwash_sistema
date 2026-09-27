<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Fase 0 — Tests de caracterización del desacoplamiento catálogo/inventario.
 *
 * Estos tests fijan el comportamiento que separa "definir un producto" de
 * "reponer sus existencias". Un producto es un dato de catálogo; su stock es el
 * resultado de las operaciones de inventario registradas por el sistema.
 *
 * @see .kiro/specs/compras-ingreso-mercaderia/requirements.md
 */
class ProductoDesacoplamientoInventarioTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-inventario', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-inventario');

        $this->actingAs($this->user);
    }

    private function payloadAlta(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Shampoo Autos',
            'descripcion' => 'Shampoo para lavado de autos',
            'precio_compra' => 15.00,
            'precio_venta' => 25.00,
            'activo' => 1,
        ], $overrides);
    }

    private function payloadEdicion(Producto $producto, array $overrides = []): array
    {
        return array_merge([
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion,
            'precio_compra' => $producto->precio_compra,
            'precio_venta' => $producto->precio_venta,
            'activo' => 1,
        ], $overrides);
    }

    // Feature: compras-ingreso-mercaderia, Requisito 4: la edición de un producto
    // no puede modificar stock ni inventario.

    /** @test */
    public function update_ignora_stock_e_inventario_enviados(): void
    {
        $producto = Producto::factory()->create([
            'stock' => 50,
            'inventario' => 50,
        ]);

        $this->put(route('productos.update', $producto), $this->payloadEdicion($producto, [
            'stock' => 999,
            'inventario' => 999,
        ]));

        $producto->refresh();

        $this->assertSame(50, $producto->stock, 'La edición no debe escribir en stock.');
        $this->assertSame(50, $producto->inventario, 'La edición no debe escribir en inventario.');
    }

    /** @test */
    public function update_no_exige_stock_ni_inventario(): void
    {
        $producto = Producto::factory()->create([
            'stock' => 30,
            'inventario' => 30,
        ]);

        $response = $this->put(route('productos.update', $producto), $this->payloadEdicion($producto, [
            'nombre' => 'Nombre Corregido',
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('productos.index'));

        $producto->refresh();

        $this->assertSame('Nombre Corregido', $producto->nombre);
        $this->assertSame(30, $producto->stock, 'La edición no debe alterar el stock.');
        $this->assertSame(30, $producto->inventario, 'La edición no debe alterar el inventario.');
    }

    /** @test */
    public function update_no_deja_alterar_el_stock_a_cero(): void
    {
        $producto = Producto::factory()->create([
            'stock' => 12,
            'inventario' => 12,
        ]);

        $this->put(route('productos.update', $producto), $this->payloadEdicion($producto, [
            'stock' => 0,
            'inventario' => 0,
        ]));

        $producto->refresh();

        $this->assertSame(12, $producto->stock);
        $this->assertSame(12, $producto->inventario);
    }

    /** @test */
    public function vista_edicion_no_expone_campos_de_cantidad(): void
    {
        $producto = Producto::factory()->create();

        $response = $this->get(route('productos.edit', $producto));

        $response->assertOk();
        $response->assertDontSee('name="stock"', false);
        $response->assertDontSee('name="inventario"', false);
    }

    // Feature: compras-ingreso-mercaderia, Requisitos 8 a 11: precio_compra pasa a
    // ser opcional y la regla de margen se aplica solo cuando hay costo conocido.

    /** @test */
    public function store_acepta_precio_compra_cero(): void
    {
        $response = $this->post(route('productos.store'), $this->payloadAlta([
            'precio_compra' => 0,
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('productos.index'));
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Shampoo Autos',
            'precio_compra' => 0,
        ]);
    }

    /** @test */
    public function store_acepta_precio_compra_ausente(): void
    {
        $payload = $this->payloadAlta();
        unset($payload['precio_compra']);

        $response = $this->post(route('productos.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('productos.index'));
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Shampoo Autos',
            'precio_compra' => 0,
        ]);
    }

    /** @test */
    public function store_acepta_precio_compra_vacio(): void
    {
        $response = $this->post(route('productos.store'), $this->payloadAlta([
            'precio_compra' => '',
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('productos.index'));
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Shampoo Autos',
            'precio_compra' => 0,
        ]);
    }

    /** @test */
    public function store_sin_precio_compra_solo_exige_precio_venta_positivo(): void
    {
        $response = $this->post(route('productos.store'), $this->payloadAlta([
            'precio_compra' => 0,
            'precio_venta' => 5.00,
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['precio_venta' => 5.00]);
    }

    /** @test */
    public function store_sin_precio_compra_rechaza_precio_venta_no_positivo(): void
    {
        $response = $this->post(route('productos.store'), $this->payloadAlta([
            'precio_compra' => 0,
            'precio_venta' => 0,
        ]));

        $response->assertSessionHasErrors('precio_venta');
        $this->assertDatabaseCount('productos', 0);
    }

    /** @test */
    public function store_conserva_margen_cuando_hay_precio_compra(): void
    {
        $response = $this->post(route('productos.store'), $this->payloadAlta([
            'precio_compra' => 25.00,
            'precio_venta' => 20.00,
        ]));

        $response->assertSessionHasErrors('precio_venta');
        $this->assertDatabaseCount('productos', 0);
    }

    /** @test */
    public function store_acepta_venta_igual_al_costo(): void
    {
        $response = $this->post(route('productos.store'), $this->payloadAlta([
            'precio_compra' => 25.00,
            'precio_venta' => 25.00,
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['precio_venta' => 25.00]);
    }

    /** @test */
    public function store_rechaza_precio_compra_negativo(): void
    {
        $response = $this->post(route('productos.store'), $this->payloadAlta([
            'precio_compra' => -10.00,
        ]));

        $response->assertSessionHasErrors('precio_compra');
        $this->assertDatabaseCount('productos', 0);
    }

    /** @test */
    public function update_acepta_precio_compra_cero(): void
    {
        $producto = Producto::factory()->create([
            'precio_compra' => 18.00,
            'precio_venta' => 30.00,
            'stock' => 5,
            'inventario' => 5,
        ]);

        $response = $this->put(route('productos.update', $producto), $this->payloadEdicion($producto, [
            'precio_compra' => 0,
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('productos.index'));

        $producto->refresh();

        $this->assertEquals(0, $producto->precio_compra);
        $this->assertSame(5, $producto->stock);
    }

    /** @test */
    public function update_rechaza_precio_venta_inferior_cuando_hay_costo(): void
    {
        $producto = Producto::factory()->create([
            'precio_compra' => 30.00,
            'precio_venta' => 40.00,
        ]);

        $response = $this->put(route('productos.update', $producto), $this->payloadEdicion($producto, [
            'precio_compra' => 35.00,
            'precio_venta' => 30.00,
        ]));

        $response->assertSessionHasErrors('precio_venta');

        $producto->refresh();

        $this->assertEquals(30.00, $producto->precio_compra, 'Una validación fallida no debe escribir nada.');
        $this->assertEquals(40.00, $producto->precio_venta, 'Una validación fallida no debe escribir nada.');
    }

    // Feature: compras-ingreso-mercaderia, D2: el costo desconocido se representa con
    // 0 y no con null, porque la columna es NOT NULL y el reporte de inventario la
    // usa en aritmética.

    /** @test */
    public function precio_compra_ausente_se_persiste_como_cero_y_no_como_nulo(): void
    {
        $payload = $this->payloadAlta();
        unset($payload['precio_compra']);

        $this->post(route('productos.store'), $payload);

        $producto = Producto::where('nombre', 'Shampoo Autos')->firstOrFail();

        $this->assertNotNull($producto->precio_compra);
        $this->assertEquals(0, $producto->precio_compra);
    }
}
