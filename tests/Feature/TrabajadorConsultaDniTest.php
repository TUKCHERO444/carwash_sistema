<?php

namespace Tests\Feature;

use App\Models\Trabajador;
use App\Models\User;
use App\Services\DniApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TrabajadorConsultaDniTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-trabajadores', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-trabajadores');
    }

    public function test_returns_local_data_when_dni_already_registered(): void
    {
        Trabajador::create([
            'dni' => '27427864',
            'nombre' => 'JOSE PEDRO',
            'apellido_paterno' => 'CASTILLO',
            'apellido_materno' => 'TERRONES',
            'estado' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('trabajadores.consultarDni', ['dni' => '27427864']))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'numero' => '27427864',
                    'nombres' => 'JOSE PEDRO',
                    'apellido_paterno' => 'CASTILLO',
                    'apellido_materno' => 'TERRONES',
                ],
            ]);
    }

    public function test_returns_api_data_when_dni_not_registered(): void
    {
        $this->mock(DniApiService::class, function ($mock) {
            $mock->shouldReceive('buscarPorDni')
                ->once()
                ->with('27427864')
                ->andReturn([
                    'numero' => '27427864',
                    'nombres' => 'JUAN CARLOS',
                    'apellido_paterno' => 'PEREZ',
                    'apellido_materno' => 'GOMEZ',
                ]);
        });

        $this->actingAs($this->user)
            ->get(route('trabajadores.consultarDni', ['dni' => '27427864']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nombres', 'JUAN CARLOS')
            ->assertJsonPath('data.apellido_paterno', 'PEREZ');
    }

    public function test_requires_a_valid_dni(): void
    {
        $this->actingAs($this->user)
            ->get(route('trabajadores.consultarDni', ['dni' => 'abc']))
            ->assertSessionHasErrors('dni');
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('trabajadores.consultarDni', ['dni' => '27427864']))
            ->assertRedirect(route('login'));
    }
}
