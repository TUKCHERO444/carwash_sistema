<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VehiculoCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-vehiculos', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-vehiculos');
        $this->cliente = Cliente::factory()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'placa' => 'ABC123',
            'cliente_id' => $this->cliente->id,
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'serie' => 'SERIE001',
            'color' => 'Rojo',
            'motor' => 'MOTOR1',
            'vin' => 'VIN12345',
        ], $overrides);
    }

    public function test_index_renders_vehiculos_list(): void
    {
        Vehiculo::create($this->validPayload());

        $this->actingAs($this->user)
            ->get(route('vehiculos.index'))
            ->assertOk()
            ->assertSee('ABC123')
            ->assertSee($this->cliente->nombre);
    }

    public function test_store_creates_automotor_and_redirects(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('vehiculos.store'), $this->validPayload());

        $response->assertRedirect(route('vehiculos.index'));

        $this->assertDatabaseHas('vehiculos', [
            'placa' => 'ABC123',
            'cliente_id' => $this->cliente->id,
            'marca' => 'Toyota',
            'vin' => 'VIN12345',
        ]);
    }

    public function test_store_normalizes_placa_to_uppercase(): void
    {
        $this->actingAs($this->user)
            ->post(route('vehiculos.store'), $this->validPayload(['placa' => 'abc123']));

        $this->assertDatabaseHas('vehiculos', ['placa' => 'ABC123']);
    }

    public function test_store_requires_placa(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('vehiculos.store'), $this->validPayload(['placa' => '']));

        $response->assertSessionHasErrors('placa');
    }

    public function test_store_requires_cliente(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('vehiculos.store'), $this->validPayload(['cliente_id' => null]));

        $response->assertSessionHasErrors('cliente_id');
    }

    public function test_placa_is_unique(): void
    {
        Vehiculo::create($this->validPayload());

        $response = $this->actingAs($this->user)
            ->post(route('vehiculos.store'), $this->validPayload());

        $response->assertSessionHasErrors('placa');
        $this->assertDatabaseCount('vehiculos', 1);
    }

    public function test_vin_is_unique(): void
    {
        Vehiculo::create($this->validPayload());

        $response = $this->actingAs($this->user)
            ->post(route('vehiculos.store'), $this->validPayload(['placa' => 'XYZ999']));

        $response->assertSessionHasErrors('vin');
        $this->assertDatabaseCount('vehiculos', 1);
    }

    public function test_update_modifies_automotor_fields(): void
    {
        $vehiculo = Vehiculo::create($this->validPayload());

        $this->actingAs($this->user)
            ->put(route('vehiculos.update', $vehiculo), $this->validPayload([
                'marca' => 'Nissan',
                'modelo' => 'Sentra',
                'vin' => 'VIN99999',
            ]));

        $this->assertDatabaseHas('vehiculos', [
            'placa' => 'ABC123',
            'marca' => 'Nissan',
            'modelo' => 'Sentra',
            'vin' => 'VIN99999',
        ]);
    }

    public function test_destroy_deletes_automotor(): void
    {
        $vehiculo = Vehiculo::create($this->validPayload());

        $this->actingAs($this->user)
            ->delete(route('vehiculos.destroy', $vehiculo))
            ->assertRedirect(route('vehiculos.index'));

        $this->assertDatabaseMissing('vehiculos', ['placa' => 'ABC123']);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('vehiculos.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_is_redirected(): void
    {
        $noPerm = User::factory()->create();

        $this->actingAs($noPerm)
            ->get(route('vehiculos.index'))
            ->assertRedirect(route('dashboard'));
    }
}
