<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Fase 1 — El producto entra al catálogo sin existencias.
 *
 * Antes de la Fase 1, el alta exigía `inventario` con mínimo 1 y generaba una entrada
 * de Kardex con origen `INV-####`. Un producto recién definido no recibió mercadería,
 * así que ambas cosas se eliminaron.
 *
 * @see .kiro/specs/compras-ingreso-mercaderia/requirements.md (requisitos 1 a 3 y 6)
 */
class ProductoInventarioTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Shampoo Autos',
            'descripcion' => 'Shampoo para lavado de autos',
            'precio_compra' => 15.00,
            'precio_venta' => 25.00,
            'activo' => 1,
        ], $overrides);
    }

    // Feature: compras-ingreso-mercaderia, Requisito 2: el alta no exige cantidad.

    /** @test */
    public function store_acepta_alta_sin_campo_de_cantidad(): void
    {
        $response = $this->post(route('productos.store'), $this->payload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('productos.index'));
        $this->assertDatabaseHas('productos', ['nombre' => 'Shampoo Autos']);
    }

    /** @test */
    public function store_ignora_un_inventario_enviado(): void
    {
        $response = $this->post(route('productos.store'), $this->payload(['inventario' => 10]));

        $response->assertSessionHasNoErrors();

        $producto = Producto::where('nombre', 'Shampoo Autos')->firstOrFail();

        $this->assertSame(0, $producto->stock, 'El alta no debe aceptar existencias por formulario.');
        $this->assertSame(0, $producto->inventario);
    }

    // Feature: compras-ingreso-mercaderia, Requisito 1: nace con stock e inventario en 0.

    /** @test */
    public function store_persiste_el_producto_sin_existencias(): void
    {
        $this->post(route('productos.store'), $this->payload());

        $producto = Producto::where('nombre', 'Shampoo Autos')->firstOrFail();

        $this->assertSame(0, $producto->stock);
        $this->assertSame(0, $producto->inventario);
    }

    // Feature: compras-ingreso-mercaderia, Requisito 3: el alta no genera Kardex.

    /** @test */
    public function store_no_registra_movimiento_de_kardex(): void
    {
        $this->post(route('productos.store'), $this->payload());

        $this->assertDatabaseCount('movimientos_kardex', 0);
    }

    // Feature: compras-ingreso-mercaderia, Requisito 6: el formulario no expone cantidades.

    /** @test */
    public function create_view_no_expone_campos_de_cantidad(): void
    {
        $response = $this->get(route('productos.create'));

        $response->assertOk();
        $response->assertDontSee('name="inventario"', false);
        $response->assertDontSee('name="stock"', false);
    }

    /** @test */
    public function create_view_explica_como_se_reponen_las_existencias(): void
    {
        $response = $this->get(route('productos.create'));

        $response->assertOk();
        $response->assertSee('sin existencias', false);
        $response->assertSee('Kardex', false);
    }

    /** @test */
    public function create_view_marca_el_precio_compra_como_opcional(): void
    {
        $response = $this->get(route('productos.create'));

        $response->assertOk();
        $response->assertSee('(opcional)', false);
    }
}
