<?php

namespace Tests\Feature\Asistencia;

use App\Models\Asistencia;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\AsistenciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AsistenciaPropertyTest extends TestCase
{
    use RefreshDatabase;

    private const ITERACIONES = 100;

    private User $user;

    private array $activos = [];

    private array $inactivos = [];

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-asistencia', 'guard_name' => 'web']);
        $this->user = User::factory()->create();

        foreach (range(1, 6) as $i) {
            $this->activos[] = Trabajador::create([
                'dni' => fake()->unique()->numerify('########'),
                'nombre' => fake()->firstName(),
                'apellido_paterno' => fake()->lastName(),
                'apellido_materno' => fake()->lastName(),
                'estado' => true,
            ]);
        }

        foreach (range(1, 2) as $i) {
            $this->inactivos[] = Trabajador::create([
                'dni' => fake()->unique()->numerify('########'),
                'nombre' => fake()->firstName(),
                'apellido_paterno' => fake()->lastName(),
                'apellido_materno' => fake()->lastName(),
                'estado' => false,
            ]);
        }
    }

    private function servicio(): AsistenciaService
    {
        return app(AsistenciaService::class);
    }

    private function horaAleatoria(): string
    {
        return sprintf('%02d:%02d', mt_rand(0, 23), mt_rand(0, 59));
    }

    private function payloadAleatorio(): array
    {
        $marcas = [];
        foreach ($this->activos as $trabajador) {
            if (mt_rand(0, 1) === 1) {
                $marcas[$trabajador->id] = $this->horaAleatoria();
            }
        }
        if ($this->inactivos && mt_rand(0, 1) === 1) {
            $inactivo = $this->inactivos[array_rand($this->inactivos)];
            $marcas[$inactivo->id] = $this->horaAleatoria();
        }

        return $marcas;
    }

    private function estadoDeTabla(string $fecha): array
    {
        return Asistencia::where('fecha', $fecha)
            ->get(['trabajador_id', 'hora_entrada'])
            ->mapWithKeys(fn ($m) => [(int) $m->trabajador_id => $m->hora_entrada])
            ->all();
    }

    /**
     * Feature: asistencia, Property 1: Los conteos particionan el universo de activos
     *
     * For any subset of active workers marked on a date, the resume must satisfy
     * asistieron + no_asistieron = total_activos, and the union of both lists must
     * be exactly the set of active workers. Inactive workers never appear.
     *
     * **Valida: Requisitos 5.4, 6.1, 6.2, 6.3**
     */
    public function test_property_1_counts_partition_active_universe(): void
    {
        $fecha = now()->toDateString();

        for ($i = 0; $i < self::ITERACIONES; $i++) {
            $marcas = $this->payloadAleatorio();
            $this->servicio()->sincronizarMarca($fecha, $marcas);

            $resumen = $this->servicio()->resumenDeFecha($fecha);

            $this->assertSame(
                count($this->activos),
                $resumen['total_activos'],
                "Property 1 (iteración {$i}): total_activos debe ser el conteo de activos"
            );
            $this->assertSame(
                $resumen['asistieron'] + $resumen['no_asistieron'],
                $resumen['total_activos'],
                "Property 1 (iteración {$i}): asistieron + no_asistieron = total_activos"
            );

            $idsResumen = collect($resumen['asistentes'])
                ->pluck('trabajador_id')
                ->merge(collect($resumen['no_asistentes'])->pluck('trabajador_id'))
                ->sort()
                ->values()
                ->all();

            $idsActivos = collect($this->activos)->pluck('id')->sort()->values()->all();

            $this->assertSame(
                $idsActivos,
                $idsResumen,
                "Property 1 (iteración {$i}): las listas particionan exactamente el universo activo"
            );

            $idsInactivos = collect($this->inactivos)->pluck('id')->all();
            $this->assertSame([], array_intersect($idsActivos, $idsInactivos));
            Asistencia::where('fecha', $fecha)->delete();
        }
    }

    /**
     * Feature: asistencia, Property 2: El guardado full-sync refleja exactamente el payload
     *
     * For any date and any payload (including inactive ids), after sincronizarMarca
     * the table contains exactly one row per active id in the payload (with its hour)
     * and zero rows for omitted active workers or inactive workers.
     *
     * **Valida: Requisitos 7.4, 7.6**
     */
    public function test_property_2_full_sync_reflects_payload_exactly(): void
    {
        $fecha = now()->toDateString();

        for ($i = 0; $i < self::ITERACIONES; $i++) {
            $marcas = $this->payloadAleatorio();
            Asistencia::where('fecha', $fecha)->delete();

            $this->servicio()->sincronizarMarca($fecha, $marcas);

            $marcasEsperadas = array_filter(
                $marcas,
                fn ($id) => in_array($id, collect($this->activos)->pluck('id')->all(), true),
                ARRAY_FILTER_USE_KEY
            );

            $this->assertSame(
                $marcasEsperadas,
                $this->estadoDeTabla($fecha),
                "Property 2 (iteración {$i}): la tabla debe contener exactamente el payload filtrado a activos"
            );
            Asistencia::where('fecha', $fecha)->delete();
        }
    }

    /**
     * Feature: asistencia, Property 3: El guardado es idempotente
     *
     * For any payload, applying sincronizarMarca twice consecutively on the same
     * date produces the same database state as a single application.
     *
     * **Valida: Requisitos 7.5**
     */
    public function test_property_3_sync_is_idempotent(): void
    {
        $fecha = now()->toDateString();

        for ($i = 0; $i < self::ITERACIONES; $i++) {
            $marcas = $this->payloadAleatorio();

            $this->servicio()->sincronizarMarca($fecha, $marcas);
            $primerEstado = $this->estadoDeTabla($fecha);

            $this->servicio()->sincronizarMarca($fecha, $marcas);
            $segundoEstado = $this->estadoDeTabla($fecha);

            $this->assertSame(
                $primerEstado,
                $segundoEstado,
                "Property 3 (iteración {$i}): reaplicar el mismo payload no altera el estado"
            );
            Asistencia::where('fecha', $fecha)->delete();
        }
    }

    /**
     * Feature: asistencia, Property 4: Todas las rutas del módulo requieren autenticación
     *
     * For any route of the module, a request without session redirects to /login.
     *
     * **Valida: Requisito 8.5**
     */
    public function test_property_4_all_routes_require_authentication(): void
    {
        $fecha = now()->toDateString();
        $mes = now()->format('Y-m');

        $rutas = [
            fn () => $this->get(route('asistencia.index')),
            fn () => $this->get(route('asistencia.porFecha', ['fecha' => $fecha])),
            fn () => $this->get(route('asistencia.porMes', ['mes' => $mes])),
            fn () => $this->post(route('asistencia.marcar'), ['fecha' => $fecha, 'marcas' => []]),
        ];

        for ($i = 0; $i < self::ITERACIONES; $i++) {
            $respuesta = $rutas[$i % count($rutas)]();
            $respuesta->assertRedirect(route('login'));
        }
    }

    /**
     * Feature: asistencia, Property 5: Todas las rutas requieren el permiso acceso-asistencia
     *
     * For any route of the module, an authenticated user without the permission
     * is redirected to the dashboard (convención del proyecto: el handler de
     * UnauthorizedException en bootstrap/app.php redirige las peticiones no JSON).
     *
     * **Valida: Requisito 8.6**
     */
    public function test_property_5_all_routes_require_permission(): void
    {
        $sinPermiso = User::factory()->create();
        $fecha = now()->toDateString();
        $mes = now()->format('Y-m');

        $rutas = [
            fn () => $this->actingAs($sinPermiso)->get(route('asistencia.index')),
            fn () => $this->actingAs($sinPermiso)->get(route('asistencia.porFecha', ['fecha' => $fecha])),
            fn () => $this->actingAs($sinPermiso)->get(route('asistencia.porMes', ['mes' => $mes])),
            fn () => $this->actingAs($sinPermiso)->post(route('asistencia.marcar'), ['fecha' => $fecha, 'marcas' => []]),
        ];

        for ($i = 0; $i < self::ITERACIONES; $i++) {
            $rutas[$i % count($rutas)]()->assertRedirect(route('dashboard'));
        }
    }
}
