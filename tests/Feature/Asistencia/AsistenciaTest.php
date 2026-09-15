<?php

namespace Tests\Feature\Asistencia;

use App\Models\Asistencia;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-asistencia', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-asistencia');
    }

    private function crearTrabajador(array $overrides = []): Trabajador
    {
        return Trabajador::create(array_merge([
            'dni' => fake()->unique()->numerify('########'),
            'nombre' => fake()->firstName(),
            'apellido_paterno' => fake()->lastName(),
            'apellido_materno' => fake()->lastName(),
            'estado' => true,
        ], $overrides));
    }

    private function hoy(): string
    {
        return now()->toDateString();
    }

    public function test_index_guest_redirected_to_login(): void
    {
        $this->get(route('asistencia.index'))
            ->assertRedirect(route('login'));
    }

    public function test_index_requires_permission(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->get(route('asistencia.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_json_endpoints_forbidden_without_permission(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('asistencia.porFecha', ['fecha' => $this->hoy()]))
            ->assertStatus(403)
            ->assertJson(['message' => 'No tienes permisos para realizar esta acción.']);
    }

    public function test_index_renders_calendar(): void
    {
        $this->actingAs($this->user)
            ->get(route('asistencia.index'))
            ->assertOk()
            ->assertSee('Asistencia')
            ->assertSee('asistencia-calendario');
    }

    public function test_por_fecha_returns_resumen_with_counts(): void
    {
        $trabajador = $this->crearTrabajador();
        $this->crearTrabajador();
        Asistencia::create([
            'trabajador_id' => $trabajador->id,
            'fecha' => $this->hoy(),
            'hora_entrada' => '08:15',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('asistencia.porFecha', ['fecha' => $this->hoy()]))
            ->assertOk()
            ->assertJson([
                'total_activos' => 2,
                'asistieron' => 1,
                'no_asistieron' => 1,
            ]);

        $response->assertJsonPath('asistentes.0.nombre_completo', $trabajador->nombre_completo);
        $response->assertJsonPath('asistentes.0.hora_entrada', '08:15');
        $this->assertCount(1, $response->json('no_asistentes'));
    }

    public function test_por_fecha_excludes_inactive_workers(): void
    {
        $activo1 = $this->crearTrabajador();
        $activo2 = $this->crearTrabajador();
        $this->crearTrabajador(['estado' => false]);

        Asistencia::create([
            'trabajador_id' => $activo1->id,
            'fecha' => $this->hoy(),
            'hora_entrada' => '08:00',
        ]);
        Asistencia::create([
            'trabajador_id' => $activo2->id,
            'fecha' => $this->hoy(),
            'hora_entrada' => '08:05',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('asistencia.porFecha', ['fecha' => $this->hoy()]))
            ->assertOk()
            ->assertJson([
                'total_activos' => 2,
                'asistieron' => 2,
                'no_asistieron' => 0,
            ]);

        $this->assertCount(2, $response->json('asistentes'));
        $this->assertCount(0, $response->json('no_asistentes'));
    }

    public function test_por_fecha_orders_asistentes_by_hora(): void
    {
        $t1 = $this->crearTrabajador(['nombre' => 'Ana']);
        $t2 = $this->crearTrabajador(['nombre' => 'Beto']);

        Asistencia::create(['trabajador_id' => $t2->id, 'fecha' => $this->hoy(), 'hora_entrada' => '08:30']);
        Asistencia::create(['trabajador_id' => $t1->id, 'fecha' => $this->hoy(), 'hora_entrada' => '08:10']);

        $this->actingAs($this->user)
            ->getJson(route('asistencia.porFecha', ['fecha' => $this->hoy()]))
            ->assertOk()
            ->assertJsonPath('asistentes.0.nombre_completo', $t1->nombre_completo)
            ->assertJsonPath('asistentes.1.nombre_completo', $t2->nombre_completo);
    }

    public function test_por_fecha_rejects_future_date(): void
    {
        $manana = now()->addDay()->toDateString();

        $this->actingAs($this->user)
            ->getJson(route('asistencia.porFecha', ['fecha' => $manana]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha');
    }

    public function test_por_fecha_rejects_invalid_format(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('asistencia.porFecha', ['fecha' => '12/09/2026']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha');
    }

    public function test_por_mes_returns_only_days_with_marks(): void
    {
        $trabajador = $this->crearTrabajador();
        $dia1 = now()->startOfMonth()->toDateString();
        $dia2 = now()->startOfMonth()->addDay()->toDateString();

        Asistencia::create(['trabajador_id' => $trabajador->id, 'fecha' => $dia1, 'hora_entrada' => '08:00']);
        Asistencia::create(['trabajador_id' => $trabajador->id, 'fecha' => $dia2, 'hora_entrada' => '08:30']);

        $mes = now()->format('Y-m');

        $this->actingAs($this->user)
            ->getJson(route('asistencia.porMes', ['mes' => $mes]))
            ->assertOk()
            ->assertJsonPath('total_activos', 1)
            ->assertJsonCount(2, 'dias')
            ->assertJsonPath("dias.$dia1.asistentes", 1)
            ->assertJsonPath("dias.$dia2.asistentes", 1);
    }

    public function test_por_mes_rejects_invalid_month(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('asistencia.porMes', ['mes' => '13-2026']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('mes');
    }

    public function test_marcar_syncs_marks_full_sync(): void
    {
        $t1 = $this->crearTrabajador();
        $t2 = $this->crearTrabajador();
        $this->crearTrabajador();

        Asistencia::create(['trabajador_id' => $t1->id, 'fecha' => $this->hoy(), 'hora_entrada' => '07:45']);

        $this->actingAs($this->user)
            ->postJson(route('asistencia.marcar'), [
                'fecha' => $this->hoy(),
                'marcas' => [
                    $t1->id => '08:00',
                    $t2->id => '08:15',
                ],
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'total_activos' => 3,
                'asistieron' => 2,
                'no_asistieron' => 1,
            ]);

        $marcas = Asistencia::where('fecha', $this->hoy())->get();

        $this->assertCount(2, $marcas);
        $this->assertDatabaseHas('asistencias', [
            'fecha' => $this->hoy(),
            'trabajador_id' => $t1->id,
            'hora_entrada' => '08:00',
        ]);
        $this->assertDatabaseHas('asistencias', [
            'fecha' => $this->hoy(),
            'trabajador_id' => $t2->id,
            'hora_entrada' => '08:15',
        ]);
    }

    public function test_marcar_is_idempotent(): void
    {
        $t1 = $this->crearTrabajador();
        $t2 = $this->crearTrabajador();

        $payload = [
            'fecha' => $this->hoy(),
            'marcas' => [$t1->id => '08:00', $t2->id => '08:30'],
        ];

        $this->actingAs($this->user)->postJson(route('asistencia.marcar'), $payload)->assertOk();
        $this->actingAs($this->user)->postJson(route('asistencia.marcar'), $payload)->assertOk();

        $this->assertDatabaseCount('asistencias', 2);
        $this->assertDatabaseHas('asistencias', [
            'fecha' => $this->hoy(),
            'trabajador_id' => $t1->id,
            'hora_entrada' => '08:00',
        ]);
    }

    public function test_marcar_round_trip_uses_h_i_format(): void
    {
        $t1 = $this->crearTrabajador();
        $t2 = $this->crearTrabajador();

        $this->actingAs($this->user)
            ->postJson(route('asistencia.marcar'), [
                'fecha' => $this->hoy(),
                'marcas' => [$t1->id => '08:00'],
            ])
            ->assertOk()
            ->assertJsonPath('asistentes.0.hora_entrada', '08:00');

        $resumen = $this->actingAs($this->user)
            ->getJson(route('asistencia.porFecha', ['fecha' => $this->hoy()]))
            ->assertOk()
            ->json();

        $horasLeidas = [];
        foreach ($resumen['asistentes'] as $asistente) {
            $this->assertMatchesRegularExpression(
                '/^(2[0-3]|[01][0-9]):[0-5][0-9]$/',
                $asistente['hora_entrada'],
                'la hora devuelta por el servidor debe estar en formato H:i'
            );
            $horasLeidas[$asistente['trabajador_id']] = $asistente['hora_entrada'];
        }

        $horasLeidas[$t2->id] = '08:45';

        $this->actingAs($this->user)
            ->postJson(route('asistencia.marcar'), [
                'fecha' => $this->hoy(),
                'marcas' => $horasLeidas,
            ])
            ->assertOk()
            ->assertJson(['asistieron' => 2]);

        $this->assertDatabaseCount('asistencias', 2);
    }

    public function test_marcar_removes_omitted_workers(): void
    {
        $t1 = $this->crearTrabajador();
        $t2 = $this->crearTrabajador();

        Asistencia::create(['trabajador_id' => $t1->id, 'fecha' => $this->hoy(), 'hora_entrada' => '08:00']);
        Asistencia::create(['trabajador_id' => $t2->id, 'fecha' => $this->hoy(), 'hora_entrada' => '08:10']);

        $this->actingAs($this->user)
            ->postJson(route('asistencia.marcar'), [
                'fecha' => $this->hoy(),
                'marcas' => [$t1->id => '08:05'],
            ])
            ->assertOk()
            ->assertJson(['asistieron' => 1]);

        $this->assertDatabaseCount('asistencias', 1);
        $this->assertDatabaseHas('asistencias', [
            'fecha' => $this->hoy(),
            'trabajador_id' => $t1->id,
            'hora_entrada' => '08:05',
        ]);
    }

    public function test_marcar_with_empty_marcas_deletes_all(): void
    {
        $t1 = $this->crearTrabajador();
        Asistencia::create(['trabajador_id' => $t1->id, 'fecha' => $this->hoy(), 'hora_entrada' => '08:00']);

        $this->actingAs($this->user)
            ->postJson(route('asistencia.marcar'), ['fecha' => $this->hoy(), 'marcas' => []])
            ->assertOk()
            ->assertJson(['asistieron' => 0, 'no_asistieron' => 1]);

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_marcar_ignores_inactive_workers(): void
    {
        $activo = $this->crearTrabajador();
        $inactivo = $this->crearTrabajador(['estado' => false]);

        $this->actingAs($this->user)
            ->postJson(route('asistencia.marcar'), [
                'fecha' => $this->hoy(),
                'marcas' => [$activo->id => '08:00', $inactivo->id => '08:00'],
            ])
            ->assertOk()
            ->assertJson(['asistieron' => 1]);

        $this->assertDatabaseMissing('asistencias', ['trabajador_id' => $inactivo->id]);
    }

    public function test_marcar_rejects_future_date(): void
    {
        $trabajador = $this->crearTrabajador();
        $manana = now()->addDay()->toDateString();

        $this->actingAs($this->user)
            ->postJson(route('asistencia.marcar'), [
                'fecha' => $manana,
                'marcas' => [$trabajador->id => '08:00'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_marcar_rejects_invalid_hour(): void
    {
        $trabajador = $this->crearTrabajador();

        $this->actingAs($this->user)
            ->postJson(route('asistencia.marcar'), [
                'fecha' => $this->hoy(),
                'marcas' => [$trabajador->id => '24:99'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('marcas.'.$trabajador->id);

        $this->assertDatabaseCount('asistencias', 0);
    }
}
