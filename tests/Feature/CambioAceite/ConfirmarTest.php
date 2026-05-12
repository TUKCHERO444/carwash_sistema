<?php

namespace Tests\Feature\CambioAceite;

use App\Models\Caja;
use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Requirements 3 (Panel_Confirmacion) and 4 (Confirmación del Cambio de Aceite).
 */
class ConfirmarTest extends TestCase
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

        $this->user = User::factory()->create();
        $this->trabajador = Trabajador::create(['nombre' => 'Mecánico Test', 'estado' => true]);
        $this->cliente = Cliente::create(['placa' => 'ABC123', 'nombre' => 'Juan']);
        $this->producto = Producto::create([
            'nombre'        => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta'  => 35.00,
            'stock'         => 10,
            'inventario'    => 10,
            'activo'        => true,
        ]);

        $this->pendiente = CambioAceite::create([
            'cliente_id'    => $this->cliente->id,
            'trabajador_id' => $this->trabajador->id,
            'user_id'       => $this->user->id,
            'fecha'         => now()->toDateString(),
            'precio'        => 70.00,
            'total'         => 70.00,
            'estado'        => 'pendiente',
        ]);

        // Associate product with the ticket
        $this->pendiente->productos()->attach($this->producto->id, [
            'cantidad' => 2,
            'precio'   => 35.00,
            'total'    => 70.00,
        ]);
    }

    // ─── confirmar() — Panel_Confirmacion ────────────────────────────────────

    /**
     * Req 3.1 — GET confirmar returns HTTP 200 for a pendiente ticket.
     */
    public function test_confirmar_returns_http_200_for_pendiente(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get("/cambio-aceite/{$this->pendiente->id}/confirmar");

        $response->assertStatus(200);
    }

    /**
     * Req 3.1 — GET confirmar renders the confirmar view.
     */
    public function test_confirmar_renders_confirmar_view(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get("/cambio-aceite/{$this->pendiente->id}/confirmar");

        $response->assertViewIs('cambio-aceite.confirmar');
    }

    /**
     * Req 3.7 — confirmar() redirects to confirmados if ticket is already confirmed.
     */
    public function test_confirmar_redirects_to_confirmados_if_already_confirmed(): void
    {
        $confirmado = CambioAceite::create([
            'cliente_id'    => $this->cliente->id,
            'trabajador_id' => $this->trabajador->id,
            'user_id'       => $this->user->id,
            'fecha'         => now()->toDateString(),
            'precio'        => 70.00,
            'total'         => 70.00,
            'estado'        => 'confirmado',
            'metodo_pago'   => 'efectivo',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/cambio-aceite/{$confirmado->id}/confirmar");

        $response->assertRedirect(route('cambio-aceite.confirmados'));
    }

    // ─── procesarConfirmacion() ───────────────────────────────────────────────

    private function confirmPayload(array $overrides = []): array
    {
        return array_merge([
            'placa'        => 'ABC123',
            'nombre'       => 'Juan',
            'trabajador_id' => $this->trabajador->id,
            'fecha'        => now()->toDateString(),
            'precio'       => 70.00,
            'total'        => 70.00,
            'metodo_pago'  => 'efectivo',
            'productos'    => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad'    => 2,
                    'precio'      => 35.00,
                    'total'       => 70.00,
                ],
            ],
        ], $overrides);
    }

    /**
     * Req 4.7 — procesarConfirmacion() without active caja returns error_caja and does not change estado.
     */
    public function test_procesarConfirmacion_without_caja_returns_error_caja(): void
    {
        // No caja created — no active session

        $response = $this->actingAs($this->user)
            ->post("/cambio-aceite/{$this->pendiente->id}/confirmar", $this->confirmPayload());

        $response->assertSessionHas('error_caja', true);

        $this->pendiente->refresh();
        $this->assertEquals('pendiente', $this->pendiente->estado);
    }

    /**
     * Req 4.3 — procesarConfirmacion() with active caja changes estado to 'confirmado' and redirects to index.
     */
    public function test_procesarConfirmacion_with_caja_confirms_ticket_and_redirects_to_index(): void
    {
        Caja::factory()->abierta()->create();

        $response = $this->actingAs($this->user)
            ->post("/cambio-aceite/{$this->pendiente->id}/confirmar", $this->confirmPayload());

        $response->assertRedirect(route('cambio-aceite.index'));

        $this->pendiente->refresh();
        $this->assertEquals('confirmado', $this->pendiente->estado);
    }

    /**
     * Req 4.2 — procesarConfirmacion() stores payment data on confirmation.
     */
    public function test_procesarConfirmacion_stores_payment_data(): void
    {
        $caja = Caja::factory()->abierta()->create();

        $this->actingAs($this->user)
            ->post("/cambio-aceite/{$this->pendiente->id}/confirmar", $this->confirmPayload());

        $this->assertDatabaseHas('cambio_aceites', [
            'id'          => $this->pendiente->id,
            'estado'      => 'confirmado',
            'metodo_pago' => 'efectivo',
            'caja_id'     => $caja->id,
        ]);
    }

    /**
     * Req 4.4 — procesarConfirmacion() with total <= 0 returns validation error.
     */
    public function test_procesarConfirmacion_with_zero_total_returns_validation_error(): void
    {
        Caja::factory()->abierta()->create();

        $response = $this->actingAs($this->user)
            ->post("/cambio-aceite/{$this->pendiente->id}/confirmar", $this->confirmPayload(['total' => 0]));

        $response->assertSessionHasErrors('total');

        $this->pendiente->refresh();
        $this->assertEquals('pendiente', $this->pendiente->estado);
    }

    /**
     * Req 4.1 — procesarConfirmacion() requires metodo_pago.
     */
    public function test_procesarConfirmacion_requires_metodo_pago(): void
    {
        Caja::factory()->abierta()->create();

        $response = $this->actingAs($this->user)
            ->post("/cambio-aceite/{$this->pendiente->id}/confirmar", $this->confirmPayload(['metodo_pago' => '']));

        $response->assertSessionHasErrors('metodo_pago');
    }

    /**
     * Req 4.6 — procesarConfirmacion() accepts all valid payment methods.
     */
    public function test_procesarConfirmacion_accepts_all_valid_payment_methods(): void
    {
        foreach (['efectivo', 'yape', 'izipay'] as $metodo) {
            // Reset ticket state
            $this->pendiente->update(['estado' => 'pendiente']);

            Caja::factory()->abierta()->create();

            $response = $this->actingAs($this->user)
                ->post("/cambio-aceite/{$this->pendiente->id}/confirmar", $this->confirmPayload(['metodo_pago' => $metodo]));

            $response->assertRedirect(route('cambio-aceite.index'));

            $this->pendiente->refresh();
            $this->assertEquals('confirmado', $this->pendiente->estado);

            // Close all cajas for next iteration
            Caja::where('estado', 'abierta')->update(['estado' => 'cerrada', 'fecha_cierre' => now()]);
            $this->pendiente->update(['estado' => 'pendiente']);
        }
    }
}
