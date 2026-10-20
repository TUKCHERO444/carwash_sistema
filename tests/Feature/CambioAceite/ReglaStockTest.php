<?php

namespace Tests\Feature\CambioAceite;

use App\Models\Caja;
use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regla de stock del ticket de cambio de aceite:
 *
 * - Un ticket PENDIENTE nunca descuenta inventario (el único descuento es la
 *   confirmación), así que la disponibilidad al editar/confirmar es el stock
 *   REAL (la fórmula vieja "stock + cantidad anterior" era residuo del
 *   diseño anterior y generaba stock negativo).
 * - update() solo restaura/decrementa stock si el ticket está confirmado.
 * - Guardas anti doble-confirmación.
 */
class ReglaStockTest extends TestCase
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

    // ── update() (PUT /cambio-aceite/{id}) ───────────────────────────────────

    /**
     * Editar un pendiente NO debe tocar el stock (bug original: restaurar +
     * decrementar dejaba stock negativo; con la fórmula vieja 10+2-3 = 9).
     */
    public function test_update_de_pendiente_no_modifica_stock(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/cambio-aceite/{$this->pendiente->id}", $this->payloadTicket([
                'precio' => 105.00,
                'total' => 105.00,
                'productos' => [
                    [
                        'producto_id' => $this->producto->id,
                        'cantidad' => 3,
                        'precio' => 35.00,
                        'total' => 105.00,
                    ],
                ],
            ]));

        $response->assertRedirect(route('cambio-aceite.show', $this->pendiente));

        $this->assertEquals(10, $this->producto->fresh()->stock);
    }

    /**
     * Editar un CONFIRMADO sí ajusta inventario: restaura la cantidad anterior
     * y decrementa la nueva (stock 8 + 2 - 3 = 7).
     */
    public function test_update_de_confirmado_ajusta_stock_con_las_nuevas_cantidades(): void
    {
        $caja = Caja::factory()->abierta()->create();
        $confirmado = CambioAceite::create([
            'cliente_id' => $this->cliente->id,
            'user_id' => $this->user->id,
            'fecha' => now()->toDateString(),
            'precio' => 70.00,
            'total' => 70.00,
            'estado' => 'confirmado',
            'metodo_pago' => 'efectivo',
            'caja_id' => $caja->id,
        ]);
        $confirmado->productos()->attach($this->producto->id, [
            'cantidad' => 2,
            'precio' => 35.00,
            'total' => 70.00,
        ]);
        // Salida ya aplicada al confirmar: 10 - 2 = 8
        $this->producto->decrement('stock', 2);
        $this->assertEquals(8, $this->producto->fresh()->stock);

        $response = $this->actingAs($this->user)
            ->put("/cambio-aceite/{$confirmado->id}", $this->payloadTicket([
                'precio' => 105.00,
                'total' => 105.00,
                'productos' => [
                    [
                        'producto_id' => $this->producto->id,
                        'cantidad' => 3,
                        'precio' => 35.00,
                        'total' => 105.00,
                    ],
                ],
            ]));

        $response->assertRedirect(route('cambio-aceite.show', $confirmado));

        $this->assertEquals(7, $this->producto->fresh()->stock);
        $this->assertDatabaseHas('cambio_productos', [
            'cambio_aceite_id' => $confirmado->id,
            'producto_id' => $this->producto->id,
            'cantidad' => 3,
        ]);
    }

    /**
     * Disponibilidad al editar un pendiente = stock real (10): la fórmula
     * vieja (10 + 2 = 12) habría aceptado 12 y dejado el stock en -2 al
     * confirmar.
     */
    public function test_update_de_pendiente_rechaza_cantidad_mayor_al_stock_real(): void
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
        $this->assertEquals(10, $this->producto->fresh()->stock);
    }

    // ── actualizarTicket() (panel de confirmación) ───────────────────────────

    /**
     * Disponibilidad = stock real también en el panel de confirmación.
     */
    public function test_actualizar_ticket_rechaza_cantidad_mayor_al_stock_real(): void
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
        $this->assertEquals(10, $this->producto->fresh()->stock);
    }

    /**
     * El panel solo edita pendientes: un ticket ya confirmado redirige.
     */
    public function test_actualizar_ticket_de_confirmado_redirige_a_confirmados(): void
    {
        $this->pendiente->update(['estado' => 'confirmado', 'metodo_pago' => 'efectivo']);

        $response = $this->actingAs($this->user)
            ->put("/cambio-aceite/{$this->pendiente->id}/actualizar-ticket", $this->payloadTicket());

        $response->assertRedirect(route('cambio-aceite.confirmados'));
        $response->assertSessionHas('info');
    }

    // ── procesarConfirmacion() ───────────────────────────────────────────────

    /**
     * Único punto de descuento: la confirmación descuenta el stock real.
     */
    public function test_confirmar_descuenta_stock_real(): void
    {
        Caja::factory()->abierta()->create();

        $this->actingAs($this->user)
            ->post("/cambio-aceite/{$this->pendiente->id}/confirmar", $this->payloadTicket())
            ->assertRedirect(route('cambio-aceite.index'));

        $this->assertEquals(8, $this->producto->fresh()->stock);
    }

    /**
     * Disponibilidad al confirmar = stock real: la fórmula vieja
     * (10 + 2 = 12) habría aceptado 11 y dejado stock en -1.
     */
    public function test_confirmar_rechaza_cantidad_mayor_al_stock_real(): void
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

        $this->pendiente->refresh();
        $this->assertEquals('pendiente', $this->pendiente->estado);
        $this->assertEquals(10, $this->producto->fresh()->stock);
    }

    /**
     * Doble envío del formulario (doble clic / refresh POST): la segunda
     * confirmación redirige sin descontar stock dos veces ni duplicar pagos.
     */
    public function test_doble_confirmacion_no_descuenta_stock_dos_veces(): void
    {
        Caja::factory()->abierta()->create();

        $this->actingAs($this->user)
            ->post("/cambio-aceite/{$this->pendiente->id}/confirmar", $this->payloadTicket())
            ->assertRedirect(route('cambio-aceite.index'));

        $this->assertEquals(8, $this->producto->fresh()->stock);

        $segunda = $this->actingAs($this->user)
            ->post("/cambio-aceite/{$this->pendiente->id}/confirmar", $this->payloadTicket());

        $segunda->assertRedirect(route('cambio-aceite.confirmados'));
        $segunda->assertSessionHas('info');

        $this->assertEquals(8, $this->producto->fresh()->stock);
        $this->assertEquals(1, $this->pendiente->fresh()->pagos()->count());
    }
}
