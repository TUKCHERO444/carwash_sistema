<?php

namespace Tests\Feature\CambioAceite;

use App\Models\Caja;
use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\Producto;
use App\Models\TipoVehiculo;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regresión: los rechazos del controlador (stock excedido, paridad
 * precio/suma, claves anidadas como productos.0.producto_id) deben llegar
 * VISIBLES a la vista.
 *
 * Antes solo existía `@error('productos')` (sin punto) y `confirmar` ni
 * siquiera imprimía `precio`, así que la operación fallaba en silencio:
 * 302 de vuelta al formulario con 0 mensajes y la consola sin indicar nada.
 */
class ErroresVisiblesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Trabajador $trabajador;

    private Cliente $cliente;

    private Producto $producto;

    private CambioAceite $pendiente;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-ventas', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-ventas');
        $this->trabajador = Trabajador::create(['nombre' => 'Mecánico Test', 'estado' => true]);
        $this->cliente = Cliente::create(['placa' => 'ABC123', 'nombre' => 'Juan']);
        $this->producto = Producto::factory()->create([
            'nombre' => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta' => 35.00,
            'stock' => 10,
            'inventario' => 10,
            'activo' => true,
        ]);

        $this->pendiente = CambioAceite::create([
            'cliente_id' => $this->cliente->id,
            'user_id' => $this->user->id,
            'fecha' => now()->toDateString(),
            'precio' => 70.00,
            'total' => 70.00,
            'estado' => 'pendiente',
        ]);

        $this->pendiente->productos()->attach($this->producto->id, [
            'cantidad' => 2,
            'precio' => 35.00,
            'total' => 70.00,
        ]);
    }

    private function payloadTicket(array $overrides = []): array
    {
        return array_merge([
            'placa' => 'ABC123',
            'nombre' => 'Juan',
            'trabajadores_ids' => [$this->trabajador->id],
            'fecha' => now()->toDateString(),
            'precio' => 70.00,
            'total' => 70.00,
            'metodo_pago' => 'efectivo',
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 2,
                    'precio' => 35.00,
                    'total' => 70.00,
                ],
            ],
        ], $overrides);
    }

    /**
     * update() rechaza cantidad > stock real y el error es visible en edit.
     * La fórmula vieja (stock + cantidad anterior = 12) habría aceptado 12.
     */
    public function test_update_con_stock_excedido_muestra_el_error_en_edit(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/cambio-aceite/{$this->pendiente->id}", $this->payloadTicket([
                'precio' => 420.00,
                'total' => 420.00,
                'productos' => [
                    [
                        'producto_id' => $this->producto->id,
                        'cantidad' => 12,
                        'precio' => 35.00,
                        'total' => 420.00,
                    ],
                ],
            ]));

        $response->assertSessionHasErrors("productos.{$this->producto->id}.cantidad");

        $this->withoutVite()
            ->actingAs($this->user)
            ->get("/cambio-aceite/{$this->pendiente->id}/edit")
            ->assertStatus(200)
            ->assertSee('excede el stock disponible');
    }

    /**
     * actualizarTicket() rechaza cantidad > stock real y el error es visible
     * en el panel de confirmación (la fórmula vieja habría aceptado 12).
     */
    public function test_actualizar_ticket_con_stock_excedido_muestra_el_error_en_confirmar(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/cambio-aceite/{$this->pendiente->id}/actualizar-ticket", $this->payloadTicket([
                'productos' => [
                    [
                        'producto_id' => $this->producto->id,
                        'cantidad' => 12,
                        'precio' => 35.00,
                        'total' => 420.00,
                    ],
                ],
            ]));

        $response->assertSessionHasErrors("productos.{$this->producto->id}.cantidad");

        $this->withoutVite()
            ->actingAs($this->user)
            ->get("/cambio-aceite/{$this->pendiente->id}/confirmar")
            ->assertStatus(200)
            ->assertSee('excede el stock disponible');
    }

    /**
     * procesarConfirmacion() rechaza cantidad > stock real (disponibilidad =
     * stock real, no stock + cantidad anterior) y el error es visible en
     * el panel de confirmación.
     */
    public function test_confirmar_con_stock_excedido_muestra_el_error_en_confirmar(): void
    {
        Caja::factory()->abierta()->create();

        $response = $this->actingAs($this->user)
            ->post("/cambio-aceite/{$this->pendiente->id}/confirmar", $this->payloadTicket([
                'precio' => 385.00,
                'total' => 385.00,
                'productos' => [
                    [
                        'producto_id' => $this->producto->id,
                        'cantidad' => 11,
                        'precio' => 35.00,
                        'total' => 385.00,
                    ],
                ],
            ]));

        $response->assertSessionHasErrors("productos.{$this->producto->id}.cantidad");

        $this->withoutVite()
            ->actingAs($this->user)
            ->get("/cambio-aceite/{$this->pendiente->id}/confirmar")
            ->assertStatus(200)
            ->assertSee('excede el stock disponible');
    }

    /**
     * confirmar.blade.php no imprimía @error('precio'): la paridad
     * precio == suma del detalle rechazaba en silencio. Ahora es visible.
     */
    public function test_confirmar_con_precio_incoherente_muestra_el_error_en_confirmar(): void
    {
        Caja::factory()->abierta()->create();

        $response = $this->actingAs($this->user)
            ->post("/cambio-aceite/{$this->pendiente->id}/confirmar", $this->payloadTicket([
                'precio' => 60.00,
                'total' => 60.00,
            ]));

        $response->assertSessionHasErrors('precio');

        $this->withoutVite()
            ->actingAs($this->user)
            ->get("/cambio-aceite/{$this->pendiente->id}/confirmar")
            ->assertStatus(200)
            ->assertSee('El valor no coincide con la suma del detalle.');
    }

    /**
     * Clave anidada (productos.0.producto_id): ninguna vista imprime
     *
     * @error con puntos, así que el partial debe renderizarla en create.
     */
    public function test_store_con_producto_inexistente_muestra_el_error_anidado_en_create(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/cambio-aceite', $this->payloadTicket([
                'productos' => [
                    [
                        'producto_id' => 999999,
                        'cantidad' => 1,
                        'precio' => 35.00,
                        'total' => 35.00,
                    ],
                ],
            ]));

        $response->assertSessionHasErrors('productos.0.producto_id');
        $mensaje = session('errors')->first('productos.0.producto_id');

        $this->withoutVite()
            ->actingAs($this->user)
            ->get('/cambio-aceite/create')
            ->assertStatus(200)
            ->assertSee($mensaje);
    }

    /**
     * Clave anidada de lavados (servicios.0.servicio_id) visible en create.
     */
    public function test_store_lavado_con_servicio_inexistente_muestra_el_error_anidado_en_create(): void
    {
        $tipoVehiculo = TipoVehiculo::factory()->create(['precio' => 28000]);

        $response = $this->actingAs($this->user)
            ->post('/lavados', [
                'tipo_vehiculo_id' => $tipoVehiculo->id,
                'placa' => 'ABC123',
                'fecha' => now()->toDateString(),
                'trabajadores_ids' => [$this->trabajador->id],
                'servicios' => [
                    ['servicio_id' => 999999],
                ],
            ]);

        $response->assertSessionHasErrors('servicios.0.servicio_id');
        $mensaje = session('errors')->first('servicios.0.servicio_id');

        $this->withoutVite()
            ->actingAs($this->user)
            ->get('/lavados/create')
            ->assertStatus(200)
            ->assertSee($mensaje);
    }

    /**
     * lavados/confirmar.blade.php no imprimía @error('precio'): el rechazo
     * era invisible. Ahora el partial lo muestra.
     */
    public function test_confirmar_lavado_con_precio_invalido_muestra_el_error_en_confirmar(): void
    {
        Caja::factory()->abierta()->create();
        $tipoVehiculo = TipoVehiculo::factory()->create(['precio' => 28000]);
        $lavado = Lavado::factory()->pendiente()->create([
            'tipo_vehiculo_id' => $tipoVehiculo->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post("/lavados/{$lavado->id}/confirmar", [
                'placa' => 'ABC123',
                'tipo_vehiculo_id' => $tipoVehiculo->id,
                'fecha' => now()->toDateString(),
                'trabajadores_ids' => [$this->trabajador->id],
                'precio' => -1,
                'total' => 50.00,
                'metodo_pago' => 'efectivo',
            ]);

        $response->assertSessionHasErrors('precio');
        $mensaje = session('errors')->first('precio');

        $this->withoutVite()
            ->actingAs($this->user)
            ->get("/lavados/{$lavado->id}/confirmar")
            ->assertStatus(200)
            ->assertSee($mensaje);
    }
}
