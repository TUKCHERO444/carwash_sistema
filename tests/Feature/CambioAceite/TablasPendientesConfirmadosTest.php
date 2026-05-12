<?php

namespace Tests\Feature\CambioAceite;

use App\Models\CambioAceite;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Requirements 2 (Tabla_Pendientes) and 7 (Tabla_Confirmados).
 */
class TablasPendientesConfirmadosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Trabajador $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->trabajador = Trabajador::create(['nombre' => 'Mecánico Test', 'estado' => true]);
    }

    // ─── Tabla_Pendientes ────────────────────────────────────────────────────

    /**
     * Req 2.1 — GET /cambio-aceite returns HTTP 200.
     */
    public function test_index_returns_http_200(): void
    {
        $response = $this->actingAs($this->user)->get('/cambio-aceite');

        $response->assertStatus(200);
    }

    /**
     * Req 2.1 — GET /cambio-aceite renders the pendientes view.
     */
    public function test_index_renders_pendientes_view(): void
    {
        $response = $this->actingAs($this->user)->get('/cambio-aceite');

        $response->assertViewIs('cambio-aceite.pendientes');
    }

    /**
     * Req 2.2 — Tabla_Pendientes shows only tickets with estado = 'pendiente'.
     */
    public function test_index_shows_only_pendiente_tickets(): void
    {
        $cliente = Cliente::create(['placa' => 'ABC123', 'nombre' => 'Juan']);

        $pendiente = CambioAceite::create([
            'cliente_id'    => $cliente->id,
            'trabajador_id' => $this->trabajador->id,
            'user_id'       => $this->user->id,
            'fecha'         => now()->toDateString(),
            'precio'        => 50.00,
            'total'         => 50.00,
            'estado'        => 'pendiente',
        ]);

        $confirmado = CambioAceite::create([
            'cliente_id'    => $cliente->id,
            'trabajador_id' => $this->trabajador->id,
            'user_id'       => $this->user->id,
            'fecha'         => now()->toDateString(),
            'precio'        => 80.00,
            'total'         => 80.00,
            'estado'        => 'confirmado',
            'metodo_pago'   => 'efectivo',
        ]);

        $response = $this->actingAs($this->user)->get('/cambio-aceite');

        $response->assertViewHas('cambioAceites', function ($collection) use ($pendiente, $confirmado) {
            $ids = $collection->pluck('id');
            return $ids->contains($pendiente->id) && !$ids->contains($confirmado->id);
        });
    }

    /**
     * Req 2.7 — Tabla_Pendientes shows empty message when no pending tickets.
     */
    public function test_index_shows_empty_message_when_no_pendientes(): void
    {
        $response = $this->actingAs($this->user)->get('/cambio-aceite');

        $response->assertSee('No hay cambios de aceite pendientes');
    }

    /**
     * Req 2.4 — Tabla_Pendientes has a link to Tabla_Confirmados.
     */
    public function test_index_has_link_to_confirmados(): void
    {
        $response = $this->actingAs($this->user)->get('/cambio-aceite');

        $response->assertSee(route('cambio-aceite.confirmados'), false);
    }

    /**
     * Req 2.6 — Tabla_Pendientes has a link to create a new ticket.
     */
    public function test_index_has_link_to_create(): void
    {
        $response = $this->actingAs($this->user)->get('/cambio-aceite');

        $response->assertSee(route('cambio-aceite.create'), false);
    }

    /**
     * Req 2.5 — Each row in Tabla_Pendientes has an "Abrir ticket" link.
     */
    public function test_index_shows_abrir_ticket_link_for_each_pendiente(): void
    {
        $cliente = Cliente::create(['placa' => 'XYZ999', 'nombre' => 'Pedro']);

        $pendiente = CambioAceite::create([
            'cliente_id'    => $cliente->id,
            'trabajador_id' => $this->trabajador->id,
            'user_id'       => $this->user->id,
            'fecha'         => now()->toDateString(),
            'precio'        => 60.00,
            'total'         => 60.00,
            'estado'        => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)->get('/cambio-aceite');

        $response->assertSee(route('cambio-aceite.confirmar', $pendiente), false);
    }

    // ─── Tabla_Confirmados ───────────────────────────────────────────────────

    /**
     * Req 7.1 — GET /cambio-aceite/confirmados returns HTTP 200.
     */
    public function test_confirmados_returns_http_200(): void
    {
        $response = $this->actingAs($this->user)->get('/cambio-aceite/confirmados');

        $response->assertStatus(200);
    }

    /**
     * Req 7.1 — GET /cambio-aceite/confirmados renders the confirmados view.
     */
    public function test_confirmados_renders_confirmados_view(): void
    {
        $response = $this->actingAs($this->user)->get('/cambio-aceite/confirmados');

        $response->assertViewIs('cambio-aceite.confirmados');
    }

    /**
     * Req 7.2 — Tabla_Confirmados shows only tickets with estado = 'confirmado'.
     */
    public function test_confirmados_shows_only_confirmado_tickets(): void
    {
        $cliente = Cliente::create(['placa' => 'DEF456', 'nombre' => 'María']);

        $pendiente = CambioAceite::create([
            'cliente_id'    => $cliente->id,
            'trabajador_id' => $this->trabajador->id,
            'user_id'       => $this->user->id,
            'fecha'         => now()->toDateString(),
            'precio'        => 50.00,
            'total'         => 50.00,
            'estado'        => 'pendiente',
        ]);

        $confirmado = CambioAceite::create([
            'cliente_id'    => $cliente->id,
            'trabajador_id' => $this->trabajador->id,
            'user_id'       => $this->user->id,
            'fecha'         => now()->toDateString(),
            'precio'        => 80.00,
            'total'         => 80.00,
            'estado'        => 'confirmado',
            'metodo_pago'   => 'efectivo',
        ]);

        $response = $this->actingAs($this->user)->get('/cambio-aceite/confirmados');

        $response->assertViewHas('cambioAceites', function ($collection) use ($pendiente, $confirmado) {
            $ids = $collection->pluck('id');
            return $ids->contains($confirmado->id) && !$ids->contains($pendiente->id);
        });
    }

    /**
     * Req 7.4 — Tabla_Confirmados has a "Volver a pendientes" link.
     */
    public function test_confirmados_has_link_back_to_pendientes(): void
    {
        $response = $this->actingAs($this->user)->get('/cambio-aceite/confirmados');

        $response->assertSee(route('cambio-aceite.index'), false);
    }

    /**
     * Unauthenticated users are redirected to login.
     */
    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/cambio-aceite')->assertRedirect('/login');
        $this->get('/cambio-aceite/confirmados')->assertRedirect('/login');
    }
}
