<?php

namespace Tests\Feature\Lavado;

use App\Models\TipoVehiculo;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Registro del ticket de lavado en estado pendiente.
 *
 * Regresión: el alta del automotor (placa como PK string) disparaba el
 * observer de auditoría y `registros_auditoria.auditable_id` era entero,
 * lo que revertía la transacción en MySQL. La columna ahora es string.
 */
class StoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private TipoVehiculo $tipoVehiculo;

    private Trabajador $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-ventas', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-ventas');
        $this->tipoVehiculo = TipoVehiculo::factory()->create(['precio' => 28000]);
        $this->trabajador = Trabajador::factory()->create();
    }

    private function payloadBase(array $overrides = []): array
    {
        return array_merge([
            'tipo_vehiculo_id' => $this->tipoVehiculo->id,
            'placa' => 'ABC123',
            'fecha' => now()->toDateString(),
            'trabajadores_ids' => [$this->trabajador->id],
        ], $overrides);
    }

    public function test_store_con_placa_no_registrada_crea_ticket_y_audita_automotor(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('lavados.store'), $this->payloadBase());

        $response->assertRedirect(route('lavados.index'));

        $this->assertDatabaseHas('lavados', ['vehiculo_id' => 'ABC123']);
        $this->assertDatabaseHas('vehiculos', ['placa' => 'ABC123']);
        $this->assertDatabaseHas('registros_auditoria', [
            'auditable_type' => Vehiculo::class,
            'auditable_id' => 'ABC123',
        ]);
    }

    public function test_store_con_placa_registrada_reutiliza_automotor(): void
    {
        $this->actingAs($this->user)
            ->post(route('lavados.store'), $this->payloadBase());

        $response = $this->actingAs($this->user)
            ->post(route('lavados.store'), $this->payloadBase());

        $response->assertRedirect(route('lavados.index'));

        $this->assertEquals(1, Vehiculo::where('placa', 'ABC123')->count());
        $this->assertDatabaseCount('lavados', 2);
    }
}
