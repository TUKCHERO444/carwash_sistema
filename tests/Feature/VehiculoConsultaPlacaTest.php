<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\VehiculoApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VehiculoConsultaPlacaTest extends TestCase
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

    public function test_returns_local_data_when_placa_already_registered(): void
    {
        Vehiculo::create([
            'placa' => 'ABC123',
            'cliente_id' => $this->cliente->id,
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'serie' => 'SERIE001',
            'color' => 'Rojo',
            'motor' => 'MOTOR1',
            'vin' => 'VINLOCAL',
        ]);

        $this->actingAs($this->user)
            ->get(route('vehiculos.consultarPlaca', ['placa' => 'ABC123']))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'placa' => 'ABC123',
                    'marca' => 'Toyota',
                    'modelo' => 'Corolla',
                    'serie' => 'SERIE001',
                    'color' => 'Rojo',
                    'motor' => 'MOTOR1',
                    'vin' => 'VINLOCAL',
                ],
            ]);
    }

    public function test_returns_api_data_when_placa_not_registered(): void
    {
        $this->mock(VehiculoApiService::class, function ($mock) {
            $mock->shouldReceive('buscarPorPlaca')
                ->once()
                ->with('ABC123')
                ->andReturn([
                    'marca' => 'Nissan',
                    'modelo' => 'Sentra',
                    'serie' => 'SERIEAPI',
                    'color' => 'Azul',
                    'motor' => 'MOTORAPI',
                    'vin' => 'VINAPI',
                ]);
        });

        $this->actingAs($this->user)
            ->get(route('vehiculos.consultarPlaca', ['placa' => 'abc123']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.placa', 'ABC123')
            ->assertJsonPath('data.marca', 'Nissan')
            ->assertJsonPath('data.vin', 'VINAPI');
    }

    public function test_requires_a_valid_placa(): void
    {
        $this->actingAs($this->user)
            ->get(route('vehiculos.consultarPlaca', ['placa' => 'ABC']))
            ->assertSessionHasErrors('placa');
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('vehiculos.consultarPlaca', ['placa' => 'ABC123']))
            ->assertRedirect(route('login'));
    }
}
