<?php

namespace Tests\Feature\CambioAceite;

use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Requirement 1: Registro del Ticket en Estado Pendiente.
 */
class StoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Trabajador $trabajador;
    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->trabajador = Trabajador::create(['nombre' => 'Mecánico Test', 'estado' => true]);
        $this->producto = Producto::create([
            'nombre'        => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta'  => 35.00,
            'stock'         => 10,
            'inventario'    => 10,
            'activo'        => true,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'placa'        => 'ABC123',
            'nombre'       => 'Juan Pérez',
            'trabajador_id' => $this->trabajador->id,
            'fecha'        => now()->toDateString(),
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
     * Req 1.2 — store() creates ticket with estado = 'pendiente' and redirects to index.
     */
    public function test_store_creates_pendiente_ticket_and_redirects_to_index(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/cambio-aceite', $this->validPayload());

        $response->assertRedirect(route('cambio-aceite.index'));

        $this->assertDatabaseHas('cambio_aceites', [
            'estado' => 'pendiente',
        ]);
    }

    /**
     * Req 1.6 — store() does NOT require payment fields (metodo_pago, total, precio).
     * Sending without them should succeed.
     */
    public function test_store_succeeds_without_payment_fields(): void
    {
        $payload = $this->validPayload();
        // Explicitly ensure no payment fields are present
        unset($payload['metodo_pago'], $payload['total'], $payload['precio']);

        $response = $this->actingAs($this->user)
            ->post('/cambio-aceite', $payload);

        $response->assertRedirect(route('cambio-aceite.index'));
        $this->assertDatabaseHas('cambio_aceites', ['estado' => 'pendiente']);
    }

    /**
     * Req 1.7 — store() calculates precio server-side as sum of (cantidad × precio).
     */
    public function test_store_calculates_precio_server_side(): void
    {
        $this->actingAs($this->user)
            ->post('/cambio-aceite', $this->validPayload());

        // 2 × 35.00 = 70.00
        $this->assertDatabaseHas('cambio_aceites', [
            'precio' => 70.00,
            'total'  => 70.00,
        ]);
    }

    /**
     * Req 1.8 — store() persists each product line in cambio_productos.
     */
    public function test_store_persists_product_lines_in_cambio_productos(): void
    {
        $this->actingAs($this->user)
            ->post('/cambio-aceite', $this->validPayload());

        $this->assertDatabaseHas('cambio_productos', [
            'producto_id' => $this->producto->id,
            'cantidad'    => 2,
            'precio'      => 35.00,
            'total'       => 70.00,
        ]);
    }

    /**
     * Req 1.9 — store() decrements product stock within a transaction.
     */
    public function test_store_decrements_product_stock(): void
    {
        $stockBefore = $this->producto->stock; // 10

        $this->actingAs($this->user)
            ->post('/cambio-aceite', $this->validPayload());

        $this->producto->refresh();
        $this->assertEquals($stockBefore - 2, $this->producto->stock);
    }

    /**
     * Req 1.3 — store() requires placa, fecha, trabajador_id, and at least one product.
     */
    public function test_store_requires_placa(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/cambio-aceite', $this->validPayload(['placa' => '']));

        $response->assertSessionHasErrors('placa');
    }

    /**
     * Req 1.3 — store() requires trabajador_id.
     */
    public function test_store_requires_trabajador_id(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/cambio-aceite', $this->validPayload(['trabajador_id' => '']));

        $response->assertSessionHasErrors('trabajador_id');
    }

    /**
     * Req 1.3 — store() requires at least one product.
     */
    public function test_store_requires_at_least_one_product(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/cambio-aceite', $this->validPayload(['productos' => []]));

        $response->assertSessionHasErrors('productos');
    }

    /**
     * Req 1.5 — store() returns validation errors without creating the ticket.
     */
    public function test_store_with_missing_required_fields_does_not_create_ticket(): void
    {
        $this->actingAs($this->user)
            ->post('/cambio-aceite', ['placa' => '']);

        $this->assertDatabaseCount('cambio_aceites', 0);
    }
}
