<?php

namespace Tests\Feature\CambioAceite;

use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tests for Requirement 5: Actualización del Ticket Pendiente.
 */
class ActualizarTicketTest extends TestCase
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
        $this->producto = Producto::create([
            'nombre' => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta' => 35.00,
            'stock' => 10,
            'inventario' => 10,
            'activo' => true,
        ]);

        $this->pendiente = CambioAceite::create([
            'cliente_id' => $this->cliente->id,
            'trabajador_id' => $this->trabajador->id,
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

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'placa' => 'ABC123',
            'nombre' => 'Juan',
            'trabajadores_ids' => [$this->trabajador->id],
            'fecha' => now()->toDateString(),
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 3,
                    'precio' => 35.00,
                    'total' => 105.00,
                ],
            ],
        ], $overrides);
    }

    /**
     * Req 5.1, 5.2 — actualizarTicket() keeps estado = 'pendiente' and redirects back to confirmar.
     */
    public function test_actualizar_ticket_keeps_estado_pendiente_and_redirects_to_confirmar(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/cambio-aceite/{$this->pendiente->id}/actualizar-ticket", $this->updatePayload());

        $response->assertRedirect(route('cambio-aceite.confirmar', $this->pendiente));

        $this->pendiente->refresh();
        $this->assertEquals('pendiente', $this->pendiente->estado);
    }

    /**
     * Req 5.7 — actualizarTicket() recalculates precio server-side.
     */
    public function test_actualizar_ticket_recalculates_precio(): void
    {
        $this->actingAs($this->user)
            ->put("/cambio-aceite/{$this->pendiente->id}/actualizar-ticket", $this->updatePayload());

        // 3 × 35.00 = 105.00
        $this->assertDatabaseHas('cambio_aceites', [
            'id' => $this->pendiente->id,
            'precio' => 105.00,
            'total' => 105.00,
        ]);
    }

    /**
     * actualizarTicket() edita un ticket pendiente y NO debe tocar el stock:
     * este solo se descuenta al confirmar.
     */
    public function test_actualizar_ticket_does_not_change_product_stock(): void
    {
        $stockBefore = $this->producto->fresh()->stock; // 10

        $this->actingAs($this->user)
            ->put("/cambio-aceite/{$this->pendiente->id}/actualizar-ticket", $this->updatePayload());

        $this->producto->refresh();
        $this->assertEquals($stockBefore, $this->producto->stock);
    }

    /**
     * Req 5.3 — actualizarTicket() without trabajadores_ids returns validation error.
     */
    public function test_actualizar_ticket_requires_trabajador_id(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/cambio-aceite/{$this->pendiente->id}/actualizar-ticket", $this->updatePayload(['trabajadores_ids' => []]));

        $response->assertSessionHasErrors('trabajadores_ids');
    }

    /**
     * Req 5.4 — actualizarTicket() without products returns validation error.
     */
    public function test_actualizar_ticket_requires_at_least_one_product(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/cambio-aceite/{$this->pendiente->id}/actualizar-ticket", $this->updatePayload(['productos' => []]));

        $response->assertSessionHasErrors('productos');
    }

    /**
     * Req 5.5, 5.6 — actualizarTicket() allows changing products.
     */
    public function test_actualizar_ticket_allows_changing_products(): void
    {
        $newProducto = Producto::create([
            'nombre' => 'Filtro de aceite',
            'precio_compra' => 10.00,
            'precio_venta' => 20.00,
            'stock' => 5,
            'inventario' => 5,
            'activo' => true,
        ]);

        $payload = $this->updatePayload([
            'productos' => [
                [
                    'producto_id' => $newProducto->id,
                    'cantidad' => 1,
                    'precio' => 20.00,
                    'total' => 20.00,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->put("/cambio-aceite/{$this->pendiente->id}/actualizar-ticket", $payload);

        $response->assertRedirect(route('cambio-aceite.confirmar', $this->pendiente));

        $this->assertDatabaseHas('cambio_productos', [
            'cambio_aceite_id' => $this->pendiente->id,
            'producto_id' => $newProducto->id,
        ]);
    }
}
