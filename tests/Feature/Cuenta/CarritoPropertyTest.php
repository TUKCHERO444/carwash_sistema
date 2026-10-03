<?php

namespace Tests\Feature\Cuenta;

use App\Models\CarritoItem;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Services\CarritoService;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

// Feature: carrito-publico, Property 1: agregar crea/actualiza con snapshot
// y nunca excede stock; Property 2: totales exactos; Property 3: snapshot
// estable; Property 4: ownership; Property 5: guest nunca muta;
// Property 6: PDF determinista; Property 7: el carrito nunca mueve stock.

/**
 * Propiedades del carrito ante entradas aleatorias.
 */
class CarritoPropertyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
    }

    private static int $ipSecuencia = 1000;

    private function ip(): array
    {
        self::$ipSecuencia++;
        $n = self::$ipSecuencia;

        return ['REMOTE_ADDR' => '10.60.'.(intdiv($n, 250) % 250).'.'.(($n % 250) + 1)];
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

    private function producto(?int $stock = null, ?float $precio = null): Producto
    {
        $categoria = Categoria::create(['nombre' => 'Cat '.uniqid()]);

        return Producto::factory()->create([
            'categoria_id' => $categoria->id,
            'stock' => $stock ?? fake()->numberBetween(1, 50),
            'inventario' => 100,
            'precio_venta' => $precio ?? fake()->randomFloat(2, 1, 500),
        ]);
    }

    /**
     * P1 + P7: agregar válido acumula con snapshot; inválido no persiste;
     * el stock jamás cambia.
     */
    public function test_agregar_snapshot_topes_y_stock_intacto(): void
    {
        $servicio = app(CarritoService::class);

        for ($i = 0; $i < 100; $i++) {
            $user = $this->cliente();
            $this->actingAs($user);
            $stock = fake()->numberBetween(1, 30);
            $precio = fake()->randomFloat(2, 1, 500);
            $producto = $this->producto($stock, $precio);

            $c1 = fake()->numberBetween(1, $stock);
            $item = $servicio->agregar($user, $producto->id, $c1);

            $this->assertSame($c1, $item->cantidad, "iter {$i}: cantidad");
            $this->assertEquals($precio, (float) $item->precio_unitario, "iter {$i}: snapshot");

            // Segunda suma que excede debe fallar sin persistir de más.
            $acumuladoAntes = $item->cantidad;

            try {
                $servicio->agregar($user, $producto->id, $stock);
                $this->assertLessThanOrEqual($stock, $item->fresh()->cantidad, "iter {$i}: tope");
            } catch (ValidationException) {
                $this->assertSame($acumuladoAntes, $item->fresh()->cantidad, "iter {$i}: sin cambio parcial");
            }

            // Producto inactivo siempre rechaza.
            $producto->update(['activo' => false]);

            try {
                $servicio->agregar($user, $producto->id, 1);
                $this->fail("iter {$i}: inactivo aceptado");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }

            $this->assertSame($stock, $producto->fresh()->stock, "iter {$i}: stock intacto");
        }
    }

    /**
     * P2 + P3: totales exactos y snapshot estable ante cambio de precio.
     */
    public function test_totales_exactos_y_precio_estable(): void
    {
        $servicio = app(CarritoService::class);

        for ($i = 0; $i < 100; $i++) {
            $user = $this->cliente();
            $this->actingAs($user);
            $esperadoTotal = 0.0;
            $esperadoConteo = 0;

            $lineas = fake()->numberBetween(1, 5);
            for ($j = 0; $j < $lineas; $j++) {
                $stock = fake()->numberBetween(5, 40);
                $precio = fake()->randomFloat(2, 1, 200);
                $cantidad = fake()->numberBetween(1, $stock);
                $producto = $this->producto($stock, $precio);

                $servicio->agregar($user, $producto->id, $cantidad);
                $esperadoTotal += $cantidad * $precio;
                $esperadoConteo += $cantidad;

                // El operador cambia el precio: el snapshot no se mueve.
                $producto->update(['precio_venta' => fake()->randomFloat(2, 1, 200)]);
            }

            $resumen = $servicio->resumen($user);

            $this->assertEquals(round($esperadoTotal, 2), $resumen['total'], "iter {$i}: total");
            $this->assertSame($esperadoConteo, $resumen['conteo'], "iter {$i}: conteo");
        }
    }

    /**
     * P4: ownership estricto entre clientes.
     */
    public function test_ownership_entre_clientes(): void
    {
        $servicio = app(CarritoService::class);

        for ($i = 0; $i < 100; $i++) {
            $a = $this->cliente();
            $b = $this->cliente();
            $this->actingAs($b);
            $producto = $this->producto(20, 10.00);
            $itemB = $servicio->agregar($b, $producto->id, 2);
            $this->actingAs($a);

            try {
                $servicio->actualizarCantidad($a, $itemB, 1);
                $this->fail("iter {$i}: A actualizó ítem de B");
            } catch (NotFoundHttpException) {
                $this->assertTrue(true);
            }

            try {
                $servicio->quitar($a, $itemB);
                $this->fail("iter {$i}: A quitó ítem de B");
            } catch (NotFoundHttpException) {
                $this->assertTrue(true);
            }

            $this->assertSame(2, $itemB->fresh()->cantidad, "iter {$i}: ítem de B intacto");
            $this->assertSame(0, $servicio->resumen($a)['conteo'], "iter {$i}: A sigue vacío");
        }
    }

    /**
     * P5 + P6: guest redirige sin mutar (varias rutas); PDF determinista.
     */
    public function test_guest_y_pdf_determinista(): void
    {
        for ($i = 0; $i < 20; $i++) {
            auth()->logout();
            $producto = $this->producto(10, 15.00);
            $antes = CarritoItem::count();

            $this->withServerVariables($this->ip())
                ->post(route('cuenta.carrito.agregar'), ['producto_id' => $producto->id, 'cantidad' => 1])
                ->assertRedirect(route('cuenta.login'));

            $this->get(route('cuenta.carrito.index'))->assertRedirect(route('cuenta.login'));
            $this->get(route('cuenta.carrito.pdf'))->assertRedirect(route('cuenta.login'));

            $this->assertSame($antes, CarritoItem::count(), "iter {$i}: nada persiste");

            // PDF: datos de la vista reflejan líneas × snapshot y total.
            $user = $this->cliente();
            $this->actingAs($user);
            $p = $this->producto(10, 20.00);
            app(CarritoService::class)->agregar($user, $p->id, 3);

            $vista = view('cuenta.carrito.pdf', [
                'user' => $user,
                'items' => $user->carrito->items,
                'total' => $user->carrito->total,
                'fecha' => now(),
            ])->render();

            $this->assertStringContainsString($p->nombre, $vista, "iter {$i}: línea en PDF");
            $this->assertStringContainsString('S/ 60.00', $vista, "iter {$i}: total en PDF");
            $this->assertStringContainsString('no reserva stock', $vista, "iter {$i}: leyenda");
        }
    }
}
