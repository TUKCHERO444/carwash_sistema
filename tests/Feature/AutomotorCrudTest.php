<?php

namespace Tests\Feature;

use App\Models\Automotor;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AutomotorCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-automotores', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-automotores');
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

    public function test_index_renders_automotores_list(): void
    {
        Automotor::create($this->validPayload());

        $this->actingAs($this->user)
            ->get(route('automotores.index'))
            ->assertOk()
            ->assertSee('ABC123')
            ->assertSee($this->cliente->nombre);
    }

    public function test_store_creates_automotor_and_redirects(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('automotores.store'), $this->validPayload());

        $response->assertRedirect(route('automotores.index'));

        $this->assertDatabaseHas('automotores', [
            'placa' => 'ABC123',
            'cliente_id' => $this->cliente->id,
            'marca' => 'Toyota',
            'vin' => 'VIN12345',
        ]);
    }

    public function test_store_normalizes_placa_to_uppercase(): void
    {
        $this->actingAs($this->user)
            ->post(route('automotores.store'), $this->validPayload(['placa' => 'abc123']));

        $this->assertDatabaseHas('automotores', ['placa' => 'ABC123']);
    }

    public function test_store_requires_placa(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('automotores.store'), $this->validPayload(['placa' => '']));

        $response->assertSessionHasErrors('placa');
    }

    public function test_store_requires_cliente(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('automotores.store'), $this->validPayload(['cliente_id' => null]));

        $response->assertSessionHasErrors('cliente_id');
    }

    public function test_placa_is_unique(): void
    {
        Automotor::create($this->validPayload());

        $response = $this->actingAs($this->user)
            ->post(route('automotores.store'), $this->validPayload());

        $response->assertSessionHasErrors('placa');
        $this->assertDatabaseCount('automotores', 1);
    }

    public function test_update_modifies_automotor_fields(): void
    {
        $automotor = Automotor::create($this->validPayload());

        $this->actingAs($this->user)
            ->put(route('automotores.update', $automotor), $this->validPayload([
                'marca' => 'Nissan',
                'modelo' => 'Sentra',
                'vin' => 'VIN99999',
            ]));

        $this->assertDatabaseHas('automotores', [
            'placa' => 'ABC123',
            'marca' => 'Nissan',
            'modelo' => 'Sentra',
            'vin' => 'VIN99999',
        ]);
    }

    public function test_destroy_deletes_automotor(): void
    {
        $automotor = Automotor::create($this->validPayload());

        $this->actingAs($this->user)
            ->delete(route('automotores.destroy', $automotor))
            ->assertRedirect(route('automotores.index'));

        $this->assertDatabaseMissing('automotores', ['placa' => 'ABC123']);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('automotores.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_is_redirected(): void
    {
        $noPerm = User::factory()->create();

        $this->actingAs($noPerm)
            ->get(route('automotores.index'))
            ->assertRedirect(route('dashboard'));
    }
}
