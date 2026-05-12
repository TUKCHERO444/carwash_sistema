<?php

namespace Tests\Feature\CambioAceite;

use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for Requirement 6: Eliminación del Ticket Pendiente.
 */
class DestroyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Trabajador $trabajador;
    private Cliente $cliente;
    private Producto $producto;

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
            'stock'         => 8,  // already decremented (2 used)
            'inventario'    => 10,
            'activo'        => true,
        ]);
    }

    private function createPendienteWithProduct(): CambioAceite
    {
        $ticket = CambioAceite::create([
            'cliente_id'    => $this->cliente->id,
            'trabajador_id' => $this->trabajador->id,
            'user_id'       => $this->user->id,
            'fecha'         => now()->toDateString(),
            'precio'        => 70.00,
            'total'         => 70.00,
            'estado'        => 'pendiente',
        ]);

        $ticket->productos()->attach($this->producto->id, [
            'cantidad' => 2,
            'precio'   => 35.00,
            'total'    => 70.00,
        ]);

        return $ticket;
    }

    /**
     * Req 6.3 — destroy() on success redirects to cambio-aceite.index (Tabla_Pendientes).
     */
    public function test_destroy_redirects_to_index_on_success(): void
    {
        $ticket = $this->createPendienteWithProduct();

        $response = $this->actingAs($this->user)
            ->delete("/cambio-aceite/{$ticket->id}");

        $response->assertRedirect(route('cambio-aceite.index'));
    }

    /**
     * Req 6.2 — destroy() restores stock of all associated products.
     */
    public function test_destroy_restores_product_stock(): void
    {
        $ticket = $this->createPendienteWithProduct();
        $stockBefore = $this->producto->fresh()->stock; // 8

        $this->actingAs($this->user)
            ->delete("/cambio-aceite/{$ticket->id}");

        $this->producto->refresh();
        $this->assertEquals($stockBefore + 2, $this->producto->stock); // 10
    }

    /**
     * Req 6.2 — destroy() deletes the ticket from the database.
     */
    public function test_destroy_deletes_ticket_from_database(): void
    {
        $ticket = $this->createPendienteWithProduct();

        $this->actingAs($this->user)
            ->delete("/cambio-aceite/{$ticket->id}");

        $this->assertDatabaseMissing('cambio_aceites', ['id' => $ticket->id]);
    }

    /**
     * Req 6.4 — destroy() deletes the photo file from storage when present.
     */
    public function test_destroy_deletes_photo_from_storage(): void
    {
        Storage::fake('public');

        // Create a fake photo file
        $fotoPath = 'cambio-aceites/test-foto.jpg';
        Storage::disk('public')->put($fotoPath, 'fake image content');

        $ticket = CambioAceite::create([
            'cliente_id'    => $this->cliente->id,
            'trabajador_id' => $this->trabajador->id,
            'user_id'       => $this->user->id,
            'fecha'         => now()->toDateString(),
            'precio'        => 70.00,
            'total'         => 70.00,
            'estado'        => 'pendiente',
            'foto'          => $fotoPath,
        ]);

        $ticket->productos()->attach($this->producto->id, [
            'cantidad' => 2,
            'precio'   => 35.00,
            'total'    => 70.00,
        ]);

        $this->actingAs($this->user)
            ->delete("/cambio-aceite/{$ticket->id}");

        Storage::disk('public')->assertMissing($fotoPath);
    }

    /**
     * Req 6.3 — destroy() success message is shown.
     */
    public function test_destroy_shows_success_message(): void
    {
        $ticket = $this->createPendienteWithProduct();

        $response = $this->actingAs($this->user)
            ->delete("/cambio-aceite/{$ticket->id}");

        $response->assertSessionHas('success');
    }
}
