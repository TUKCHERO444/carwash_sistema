<?php

namespace Tests\Feature\Cuenta;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carrito de cotización: botones, página, PDF y ownership (Req. 1–6).
 */
class CarritoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
    }

    private static int $ipSecuencia = 0;

    private function ip(): array
    {
        // El throttle (array en tests) persiste en memoria todo el proceso.
        self::$ipSecuencia++;
        $n = self::$ipSecuencia;

        return ['REMOTE_ADDR' => '10.50.'.(intdiv($n, 250) % 250).'.'.(($n % 250) + 1)];
    }

    private function cliente(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status' => 'ACTIVE',
            'activo' => true,
        ]);
        $user->assignRole('Cliente');

        return $user;
    }

    private function categoria(): Categoria
    {
        return Categoria::create(['nombre' => 'Limpieza '.uniqid()]);
    }

    private function producto(int $stock = 10, ?float $precio = null): Producto
    {
        return Producto::factory()->conExistencias($stock)->create(array_filter([
            'categoria_id' => $this->categoria()->id,
            'precio_venta' => $precio,
        ]));
    }

    private function detalleUrl(Producto $producto): string
    {
        return route('publica.productos.detalle', [
            'categoria' => $producto->categoria,
            'producto' => $producto,
        ]);
    }

    public function test_detalle_muestra_agregar_si_hay_stock(): void
    {
        $producto = $this->producto(5);

        $this->get($this->detalleUrl($producto))
            ->assertStatus(200)
            ->assertSee('Agregar al carrito')
            ->assertSee('name="producto_id"', false);
    }

    public function test_detalle_muestra_agotado_sin_boton(): void
    {
        $producto = $this->producto(0);

        $respuesta = $this->get($this->detalleUrl($producto))->assertStatus(200);

        $respuesta->assertSee('Agotado');
        $respuesta->assertDontSee('Agregar al carrito');
    }

    public function test_cards_muestran_boton_rapido(): void
    {
        $producto = $this->producto(5);
        $url = route('publica.productos.categoria', ['categoria' => $producto->categoria]);

        $this->get($url)
            ->assertStatus(200)
            ->assertSee('aria-label="Agregar al carrito"', false);
    }

    public function test_agregar_crea_carrito_con_snapshot(): void
    {
        $user = $this->cliente();
        $producto = $this->producto(10, 25.50);

        $this->withServerVariables($this->ip())->actingAs($user)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 2])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('carritos', ['user_id' => $user->id]);
        $this->assertDatabaseHas('carrito_items', [
            'producto_id' => $producto->id, 'cantidad' => 2, 'precio_unitario' => 25.50,
        ]);
    }

    public function test_agregar_suma_y_mantiene_snapshot(): void
    {
        $user = $this->cliente();
        $producto = $this->producto(10, 10.00);

        $this->withServerVariables($this->ip())->actingAs($user)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 2]);
        $producto->update(['precio_venta' => 99.99]);
        $this->withServerVariables($this->ip())->actingAs($user)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 1]);

        $this->assertDatabaseHas('carrito_items', [
            'producto_id' => $producto->id, 'cantidad' => 3, 'precio_unitario' => 10.00,
        ]);
    }

    public function test_agregar_rechaza_sobre_stock_e_inactivo(): void
    {
        $user = $this->cliente();
        $producto = $this->producto(2);

        $this->withServerVariables($this->ip())->actingAs($user)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 5])
            ->assertSessionHasErrors('cantidad');

        $producto->update(['activo' => false]);

        $this->withServerVariables($this->ip())->actingAs($user)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 1])
            ->assertSessionHasErrors('producto_id');

        $this->assertDatabaseMissing('carritos', ['user_id' => $user->id]);
    }

    public function test_guest_no_mutar_ni_ver(): void
    {
        $producto = $this->producto(5);

        $this->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 1])
            ->assertRedirect(route('cuenta.login'));

        $this->get(route('cuenta.carrito.index'))->assertRedirect(route('cuenta.login'));
        $this->get(route('cuenta.carrito.pdf'))->assertRedirect(route('cuenta.login'));

        $this->assertDatabaseMissing('carritos', []);
        $this->assertDatabaseMissing('carrito_items', []);
    }

    public function test_actualizar_quitar_vaciar(): void
    {
        $user = $this->cliente();
        $producto = $this->producto(10, 5.00);

        $this->withServerVariables($this->ip())->actingAs($user)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 2]);

        $item = $user->carrito->items()->firstOrFail();

        $this->withServerVariables($this->ip())->actingAs($user)
            ->patch(route('cuenta.carrito.actualizar', ['item' => $item]), ['cantidad' => 4])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('carrito_items', ['id' => $item->id, 'cantidad' => 4]);

        $this->withServerVariables($this->ip())->actingAs($user)
            ->patch(route('cuenta.carrito.actualizar', ['item' => $item]), ['cantidad' => 50])
            ->assertSessionHasErrors('cantidad');

        $this->actingAs($user)
            ->delete(route('cuenta.carrito.quitar', ['item' => $item]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('carrito_items', ['id' => $item->id]);

        $this->withServerVariables($this->ip())->actingAs($user)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 1]);
        $this->actingAs($user)->delete(route('cuenta.carrito.vaciar'))->assertSessionHas('success');

        $this->assertSame(0, $user->carrito->items()->count());
    }

    public function test_carrito_muestra_lineas_subtotales_y_total(): void
    {
        $user = $this->cliente();
        $p1 = $this->producto(10, 10.00);
        $p2 = $this->producto(10, 7.50);

        $this->withServerVariables($this->ip())->actingAs($user)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $p1->id, 'cantidad' => 2]);
        $this->withServerVariables($this->ip())->actingAs($user)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $p2->id, 'cantidad' => 3]);

        $this->actingAs($user)->get(route('cuenta.carrito.index'))
            ->assertStatus(200)
            ->assertViewIs('cuenta.carrito.index')
            ->assertSee('S/ 20.00')
            ->assertSee('S/ 22.50')
            ->assertSee('S/ 42.50');
    }

    public function test_ownership_otro_cliente_no_ve_ni_toca(): void
    {
        $a = $this->cliente();
        $b = $this->cliente();
        $producto = $this->producto(10, 3.00);

        $this->withServerVariables($this->ip())->actingAs($b)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 2]);

        $itemB = $b->carrito->items()->firstOrFail();

        $this->withServerVariables($this->ip())->actingAs($a)
            ->patch(route('cuenta.carrito.actualizar', ['item' => $itemB]), ['cantidad' => 1])
            ->assertNotFound();

        $this->actingAs($a)
            ->delete(route('cuenta.carrito.quitar', ['item' => $itemB]))
            ->assertNotFound();

        $this->actingAs($a)->get(route('cuenta.carrito.index'))
            ->assertStatus(200)
            ->assertSee('Tu carrito está vacío.');
    }

    public function test_header_muestra_conteo_segun_sesion(): void
    {
        $user = $this->cliente();
        $producto = $this->producto(10, 4.00);

        $this->withServerVariables($this->ip())->actingAs($user)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 3]);

        $this->actingAs($user)->get(route('inicio'))
            ->assertSee('aria-label="Carrito de compra (3 artículos)"', false);

        auth()->logout();

        $this->get(route('inicio'))
            ->assertSee('aria-label="Carrito de compra (0 artículos)"', false);
    }

    public function test_pdf_descarga_detalle_y_vacio_redirige(): void
    {
        $user = $this->cliente();

        $this->actingAs($user)->get(route('cuenta.carrito.pdf'))
            ->assertRedirect(route('cuenta.carrito.index'));

        $producto = $this->producto(10, 12.00);
        $this->withServerVariables($this->ip())->actingAs($user)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 2]);

        $respuesta = $this->actingAs($user)->get(route('cuenta.carrito.pdf'));

        $respuesta->assertStatus(200);
        $respuesta->assertHeader('content-type', 'application/pdf');
        $this->assertMatchesRegularExpression(
            '/attachment; filename="?carrito-\d{8}-\d{4}\.pdf"?/',
            $respuesta->headers->get('content-disposition')
        );
        $this->assertDatabaseHas('registros_auditoria', ['modulo' => 'cuenta', 'accion' => 'carrito_pdf']);
    }

    public function test_agregar_responde_json_si_se_pide(): void
    {
        $user = $this->cliente();
        $producto = $this->producto(10, 9.99);

        $this->withServerVariables($this->ip())->actingAs($user)
            ->postJson(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 1])
            ->assertOk()
            ->assertJson(['ok' => true, 'conteo' => 1, 'total' => 9.99]);
    }

    public function test_auditoria_sin_secretos_y_stock_intacto(): void
    {
        $user = $this->cliente();
        $producto = $this->producto(10, 9.99);

        $this->withServerVariables($this->ip())->actingAs($user)
            ->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 2]);

        $this->assertDatabaseHas('registros_auditoria', ['modulo' => 'cuenta', 'accion' => 'carrito_agregar']);
        $this->assertSame(10, $producto->fresh()->stock);
    }
}
