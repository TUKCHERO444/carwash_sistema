<?php

namespace Tests\Feature\Seguridad;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * VUL-04: un nombre de producto o razón social con payload HTML/JS no debe
 * almacenarse. El escape de salida sigue aplicándose en el frontend; esta
 * prueba cubre la barrera de entrada.
 */
class XssAlmacenadoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Categoria $categoria;

    private Marca $marca;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-inventario', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'acceso-proveedores', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['acceso-inventario', 'acceso-proveedores']);
        $this->actingAs($this->user);

        $this->categoria = Categoria::create(['nombre' => 'Filtros']);
        $this->marca = Marca::create(['nombre' => 'Bosch']);
    }

    private function productoPayload(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Filtro de Aceite',
            'precio_compra' => 10,
            'precio_venta' => 20,
            'activo' => 1,
            'categoria_id' => $this->categoria->id,
            'marca_id' => $this->marca->id,
        ], $overrides);
    }

    private function proveedorPayload(array $overrides = []): array
    {
        return array_merge([
            'ruc' => '20123456789',
            'razon_social' => 'Distribuidora Andina S.A.C.',
            'direccion' => 'Av. Republica de Panama 3721, Surco',
            'estado_tributario' => 'Activo',
            'condicion' => 'Contribuyente',
            'estado' => '1',
        ], $overrides);
    }

    public static function payloadsXss(): array
    {
        return [
            'etiqueta img' => ['<img src=x onerror=alert(1)>'],
            'etiqueta script' => ['"><script>alert(1)</script>'],
            'comilla y evento' => ['Producto" onmouseover="alert(1)'],
            'backtick' => ['Producto`onclick=alert(1)'],
        ];
    }

    #[Test]
    #[DataProvider('payloadsXss')]
    public function store_de_producto_rechaza_payloads_xss(string $payload): void
    {
        $response = $this->post(route('productos.store'), $this->productoPayload(['nombre' => $payload]));

        $response->assertSessionHasErrors('nombre');
        $this->assertDatabaseCount('productos', 0);
    }

    #[Test]
    #[DataProvider('payloadsXss')]
    public function update_de_producto_rechaza_payloads_xss(string $payload): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Producto original']);

        $response = $this->put(
            route('productos.update', $producto),
            $this->productoPayload(['nombre' => $payload])
        );

        $response->assertSessionHasErrors('nombre');
        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'nombre' => 'Producto original',
        ]);
    }

    #[Test]
    public function store_de_producto_acepta_nombres_comerciales_con_puntuacion(): void
    {
        $nombre = 'Aceite 5W-30 Sintético (1L) & Filtro';

        $response = $this->post(route('productos.store'), $this->productoPayload(['nombre' => $nombre]));

        $response->assertRedirect(route('productos.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['nombre' => $nombre]);
    }

    #[Test]
    #[DataProvider('payloadsXss')]
    public function store_de_proveedor_rechaza_payloads_xss(string $payload): void
    {
        $response = $this->post(route('proveedores.store'), $this->proveedorPayload(['razon_social' => $payload]));

        $response->assertSessionHasErrors('razon_social');
        $this->assertDatabaseCount('proveedores', 0);
    }

    #[Test]
    public function update_de_proveedor_rechaza_payloads_xss(): void
    {
        $proveedor = Proveedor::factory()->create([
            'ruc' => '20123456789',
            'razon_social' => 'Proveedor original',
        ]);

        $response = $this->put(
            route('proveedores.update', $proveedor),
            $this->proveedorPayload([
                'ruc' => '20123456789',
                'razon_social' => '<img src=x onerror=alert(1)>',
            ])
        );

        $response->assertSessionHasErrors('razon_social');
        $this->assertDatabaseHas('proveedores', [
            'id' => $proveedor->id,
            'razon_social' => 'Proveedor original',
        ]);
    }

    #[Test]
    public function las_respuestas_web_incluyen_cabeceras_de_seguridad(): void
    {
        $response = $this->get('/ping');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }
}
