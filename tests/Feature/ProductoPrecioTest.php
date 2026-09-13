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
            'inventario' => 10,
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
            'inventario' => 10,
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
            'inventario' => 10,
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
            'stock' => $producto->stock,
            'inventario' => $producto->inventario,
            'activo' => 1,
        ]);

        $response->assertSessionHasErrors('precio_venta');
        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'precio_venta' => $producto->precio_venta,
        ]);
    }
}
