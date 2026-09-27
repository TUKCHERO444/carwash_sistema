<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductoPrecioTest extends TestCase
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

    /** @test */
    public function store_rejects_precio_venta_inferior_al_precio_compra(): void
    {
        $response = $this->post(route('productos.store'), [
            'nombre' => 'Producto Sin Margen',
            'precio_compra' => 25.50,
            'precio_venta' => 20.00,
            'activo' => 1,
        ]);

        $response->assertSessionHasErrors('precio_venta');
        $this->assertDatabaseCount('productos', 0);
    }

    /** @test */
    public function store_accepts_precio_venta_igual_al_precio_compra(): void
    {
        $response = $this->post(route('productos.store'), [
            'nombre' => 'Producto Al Costo',
            'precio_compra' => 25.50,
            'precio_venta' => 25.50,
            'activo' => 1,
        ]);

        $response->assertRedirect(route('productos.index'));
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Producto Al Costo',
            'precio_compra' => 25.50,
            'precio_venta' => 25.50,
        ]);
    }

    /** @test */
    public function store_accepts_precio_venta_superior_al_precio_compra(): void
    {
        $response = $this->post(route('productos.store'), [
            'nombre' => 'Producto Con Margen',
            'precio_compra' => 25.50,
            'precio_venta' => 40.00,
            'activo' => 1,
        ]);

        $response->assertRedirect(route('productos.index'));
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Producto Con Margen',
            'precio_compra' => 25.50,
            'precio_venta' => 40.00,
        ]);
    }

    /** @test */
    public function update_rejects_precio_venta_inferior_al_precio_compra(): void
    {
        $producto = Producto::factory()->create();

        $response = $this->put(route('productos.update', $producto), [
            'nombre' => $producto->nombre,
            'precio_compra' => 30.00,
            'precio_venta' => 25.00,
            'activo' => 1,
        ]);

        $response->assertSessionHasErrors('precio_venta');
        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'precio_venta' => $producto->precio_venta,
        ]);
    }

    // Feature: compras-ingreso-mercaderia, Requisitos 8 a 11: el costo desconocido
    // se representa con 0, y la regla de margen solo aplica cuando hay costo.

    /** @test */
    public function store_sin_costo_conocido_no_exige_margen(): void
    {
        $response = $this->post(route('productos.store'), [
            'nombre' => 'Producto Nuevo Sin Costo',
            'precio_compra' => 0,
            'precio_venta' => 3.00,
            'activo' => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Producto Nuevo Sin Costo',
            'precio_compra' => 0,
            'precio_venta' => 3.00,
        ]);
    }

    /** @test */
    public function update_a_cero_libera_el_requisito_de_margen(): void
    {
        $producto = Producto::factory()->create([
            'precio_compra' => 50.00,
            'precio_venta' => 20.00,
        ]);

        $response = $this->put(route('productos.update', $producto), [
            'nombre' => $producto->nombre,
            'precio_compra' => 0,
            'precio_venta' => 20.00,
            'activo' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $producto->refresh();

        $this->assertEquals(0, $producto->precio_compra);
        $this->assertEquals(20.00, $producto->precio_venta);
    }

    /** @test */
    public function update_con_costo_positivo_rechaza_venta_inferior(): void
    {
        $producto = Producto::factory()->create([
            'precio_compra' => 50.00,
            'precio_venta' => 80.00,
        ]);

        $response = $this->put(route('productos.update', $producto), [
            'nombre' => $producto->nombre,
            'precio_compra' => 60.00,
            'precio_venta' => 50.00, // Menor que costo
            'activo' => 1,
        ]);

        $response->assertSessionHasErrors('precio_venta');
    }

    /** @test */
    public function update_con_costo_cero_permite_cualquier_venta_positiva(): void
    {
        $producto = Producto::factory()->create([
            'precio_compra' => 0,
            'precio_venta' => 100.00,
        ]);

        $response = $this->put(route('productos.update', $producto), [
            'nombre' => $producto->nombre,
            'precio_compra' => 0,
            'precio_venta' => 1.00, // Muy bajo, pero permitido si costo = 0
            'activo' => 1,
        ]);

        $response->assertSessionHasNoErrors();
    }

    /** @test */
    public function store_precio_compra_ausente_se_persiste_como_cero_y_no_como_nulo(): void
    {
        $response = $this->post(route('productos.store'), [
            'nombre' => 'Producto Sin Costo Enviado',
            'precio_venta' => 50.00,
            'activo' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $producto = Producto::where('nombre', 'Producto Sin Costo Enviado')->first();
        $this->assertNotNull($producto);
        $this->assertEquals(0, $producto->precio_compra); // No null, es 0
    }

    /** @test */
    public function store_precio_compra_vacio_string_se_persiste_como_cero(): void
    {
        $response = $this->post(route('productos.store'), [
            'nombre' => 'Producto Costo Vacio',
            'precio_compra' => '',
            'precio_venta' => 50.00,
            'activo' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $producto = Producto::where('nombre', 'Producto Costo Vacio')->first();
        $this->assertNotNull($producto);
        $this->assertEquals(0, $producto->precio_compra);
    }

    /** @test */
    public function store_conserva_margen_cuando_hay_precio_compra(): void
    {
        $response = $this->post(route('productos.store'), [
            'nombre' => 'Producto Con Margen',
            'precio_compra' => 25.50,
            'precio_venta' => 40.00,
            'activo' => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Producto Con Margen',
            'precio_compra' => 25.50,
            'precio_venta' => 40.00,
        ]);
    }

    /** @test */
    public function store_acepta_venta_igual_al_costo(): void
    {
        $response = $this->post(route('productos.store'), [
            'nombre' => 'Producto Al Costo',
            'precio_compra' => 25.50,
            'precio_venta' => 25.50,
            'activo' => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Producto Al Costo',
            'precio_compra' => 25.50,
            'precio_venta' => 25.50,
        ]);
    }

    /** @test */
    public function store_rechaza_precio_compra_negativo(): void
    {
        $response = $this->post(route('productos.store'), [
            'nombre' => 'Producto Costo Negativo',
            'precio_compra' => -10.00,
            'precio_venta' => 50.00,
            'activo' => 1,
        ]);

        $response->assertSessionHasErrors('precio_compra');
    }

    /** @test */
    public function store_sin_precio_compra_solo_exige_precio_venta_positivo(): void
    {
        $response = $this->post(route('productos.store'), [
            'nombre' => 'Producto Solo Venta',
            'precio_venta' => 0.01, // Mínimo positivo
            'activo' => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Producto Solo Venta',
            'precio_compra' => 0,
            'precio_venta' => 0.01,
        ]);
    }

    /** @test */
    public function store_sin_precio_compra_rechaza_precio_venta_no_positivo(): void
    {
        $response = $this->post(route('productos.store'), [
            'nombre' => 'Producto Venta Cero',
            'precio_compra' => 0,
            'precio_venta' => 0,
            'activo' => 1,
        ]);

        $response->assertSessionHasErrors('precio_venta');
    }
}
