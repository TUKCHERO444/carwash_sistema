<?php

namespace Tests\Feature\Cuenta;

use App\Models\Automotor;
use App\Models\Cliente;
use App\Models\CustomerUserLink;
use App\Models\User;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mis vehículos con ownership por vínculo (Req. 7).
 */
class CuentaVehiculosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
    }

    private function clienteConVehiculo(string $email, string $placa): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
            'status' => 'ACTIVE',
            'activo' => true,
        ]);
        $user->assignRole('Cliente');

        $cliente = Cliente::create(['nombre' => 'Dueño', 'dni' => null, 'telefono' => null]);
        CustomerUserLink::create(['user_id' => $user->id, 'cliente_id' => $cliente->id, 'estado' => 'ACTIVO']);
        $vehiculo = Automotor::create(['placa' => $placa, 'cliente_id' => $cliente->id, 'marca' => 'Toyota']);

        return [$user, $vehiculo];
    }

    public function test_lista_solo_mis_vehiculos_paginada(): void
    {
        [$user] = $this->clienteConVehiculo('a@example.com', 'ABC123');
        $this->clienteConVehiculo('b@example.com', 'XYZ999');

        $respuesta = $this->actingAs($user)->get(route('cuenta.vehiculos.index'));

        $respuesta->assertStatus(200)->assertViewIs('cuenta.vehiculos.index');
        $respuesta->assertSee('ABC123');
        $respuesta->assertDontSee('XYZ999');
        $this->assertCount(1, $respuesta->viewData('vehiculos'));
    }

    public function test_detalle_propio_200_y_ajeno_403(): void
    {
        [$user, $mio] = $this->clienteConVehiculo('a@example.com', 'ABC123');
        [, $ajeno] = $this->clienteConVehiculo('b@example.com', 'XYZ999');

        $this->actingAs($user)
            ->get(route('cuenta.vehiculos.show', ['vehiculo' => $mio->placa]))
            ->assertStatus(200)
            ->assertSee('ABC123');

        $this->actingAs($user)
            ->get(route('cuenta.vehiculos.show', ['vehiculo' => $ajeno->placa]))
            ->assertForbidden();
    }

    public function test_guest_no_ve_vehiculos(): void
    {
        $this->get(route('cuenta.vehiculos.index'))->assertRedirect(route('cuenta.login'));
    }

    public function test_operador_sin_rol_cliente_no_ve_vehiculos(): void
    {
        [$operador] = $this->clienteConVehiculo('op@example.com', 'OPQ111');
        $operador->removeRole('Cliente');

        $this->actingAs($operador)->get(route('cuenta.vehiculos.index'))
            ->assertRedirect(route('cuenta.login'));
    }
}
