<?php

namespace Tests\Feature;

use App\Models\Automotor;
use App\Models\Cliente;
use App\Models\User;
use App\Services\AutomotorApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Soportes de búsqueda de placa en los tickets (lavado / cambio de aceite):
 * `clientes.buscar-por-placa` (local, incluye dni para autofill completo)
 * y `tickets.consultarPlacaApi` (local → API, alcanzable con acceso-ventas).
 */
class TicketsConsultaPlacaApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $userSinPermiso;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-ventas', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-ventas');
        $this->userSinPermiso = User::factory()->create();
        $this->cliente = Cliente::factory()->create(['dni' => '12345678', 'telefono' => '987654321']);
    }

    public function test_buscar_por_placa_returns_client_and_vehicle_with_dni(): void
    {
        Automotor::create([
            'placa' => 'ABC123',
            'cliente_id' => $this->cliente->id,
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'color' => 'Rojo',
            'motor' => 'MOTOR1',
        ]);

        $this->actingAs($this->user)
            ->get(route('clientes.buscar-por-placa', ['placa' => 'abc123']))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'cliente' => [
                    'id' => $this->cliente->id,
                    'placa' => 'ABC123',
                    'dni' => '12345678',
                    'telefono' => '987654321',
                    'lavados_count' => 0,
                    'cambios_aceite_count' => 0,
                ],
                'automotor' => [
                    'placa' => 'ABC123',
                    'marca' => 'Toyota',
                    'modelo' => 'Corolla',
                    'color' => 'Rojo',
                    'motor' => 'MOTOR1',
                ],
            ]);
    }

    public function test_buscar_por_placa_returns_success_false_when_placa_not_registered(): void
    {
        $this->actingAs($this->user)
            ->get(route('clientes.buscar-por-placa', ['placa' => 'ZZZ999']))
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    public function test_consultar_placa_api_returns_local_data_when_automotor_exists(): void
    {
        Automotor::create([
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
            ->get(route('tickets.consultarPlacaApi', ['placa' => 'ABC123']))
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

    public function test_consultar_placa_api_returns_api_data_when_placa_not_registered(): void
    {
        $this->mock(AutomotorApiService::class, function ($mock) {
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
            ->get(route('tickets.consultarPlacaApi', ['placa' => 'abc123']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.placa', 'ABC123')
            ->assertJsonPath('data.marca', 'Nissan')
            ->assertJsonPath('data.vin', 'VINAPI');
    }

    public function test_consultar_placa_api_never_blocks_when_api_is_down(): void
    {
        $this->mock(AutomotorApiService::class, function ($mock) {
            $mock->shouldReceive('buscarPorPlaca')
                ->once()
                ->with('ABC123')
                ->andReturn([]);
        });

        $this->actingAs($this->user)
            ->get(route('tickets.consultarPlacaApi', ['placa' => 'abc123']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.placa', 'ABC123');
    }

    public function test_consultar_placa_api_requires_a_valid_placa(): void
    {
        $this->actingAs($this->user)
            ->get(route('tickets.consultarPlacaApi', ['placa' => 'ABC']))
            ->assertSessionHasErrors('placa');
    }

    public function test_consultar_placa_api_returns_403_without_permission(): void
    {
        $this->actingAs($this->userSinPermiso)
            ->getJson(route('tickets.consultarPlacaApi', ['placa' => 'ABC123']))
            ->assertStatus(403)
            ->assertJson(['message' => 'No tienes permisos para realizar esta acción.']);
    }

    public function test_consultar_placa_api_redirects_unauthenticated_user(): void
    {
        $this->get(route('tickets.consultarPlacaApi', ['placa' => 'ABC123']))
            ->assertRedirect(route('login'));
    }
}
