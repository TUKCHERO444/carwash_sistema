<?php

namespace Tests\Feature\Reportes;

use App\Models\Asistencia;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Reportes\ReportePersonalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportePersonalTest extends TestCase
{
    use RefreshDatabase;

    private ReportePersonalService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReportePersonalService::class);
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 8, 20, 12, 0, 0));

        Permission::firstOrCreate(['name' => 'acceso-reportes', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('acceso-reportes');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_resumen_pago_por_jornal_y_sin_jornal(): void
    {
        $conJornal = Trabajador::factory()->create(['pago_diario' => 40]);
        $sinJornal = Trabajador::factory()->create(['pago_diario' => null]);

        $fechaA = now()->startOfMonth()->addDays(1)->toDateString();
        $fechaB = now()->startOfMonth()->addDays(2)->toDateString();
        $fechaC = now()->startOfMonth()->addDays(3)->toDateString();
        $fechaD = now()->startOfMonth()->addDays(4)->toDateString();

        foreach ([$fechaA, $fechaB, $fechaC] as $fecha) {
            Asistencia::create(['trabajador_id' => $conJornal->id, 'fecha' => $fecha, 'hora_entrada' => '08:00']);
        }
        Asistencia::create(['trabajador_id' => $sinJornal->id, 'fecha' => $fechaD, 'hora_entrada' => '09:00']);

        $resumen = $this->service->resumen(now()->format('Y-m'));

        $this->assertSame(4, $resumen['total_dias_con_marca']);

        $porNombre = collect($resumen['trabajadores'])->keyBy('nombre');

        $filaCon = $porNombre[$conJornal->nombre_completo];
        $this->assertSame(3, $filaCon['asistencias']);
        $this->assertEqualsWithDelta(75.0, $filaCon['porcentaje_asistencia'], 0.01);
        $this->assertSame(false, $filaCon['sin_jornal']);
        $this->assertEqualsWithDelta(120.0, $filaCon['total_pago'], 0.01);

        $filaSin = $porNombre[$sinJornal->nombre_completo];
        $this->assertTrue($filaSin['sin_jornal']);
        $this->assertEqualsWithDelta(0.0, $filaSin['total_pago'], 0.01);

        $this->assertEqualsWithDelta(120.0, $resumen['total_pago_general'], 0.01);
    }

    public function test_acotacion_por_trabajador(): void
    {
        $trabajador = Trabajador::factory()->create(['pago_diario' => 30]);
        Asistencia::create(['trabajador_id' => $trabajador->id, 'fecha' => now()->toDateString(), 'hora_entrada' => '08:00']);

        $resumen = $this->service->resumen(now()->format('Y-m'), $trabajador->id);

        $this->assertCount(1, $resumen['trabajadores']);
        $this->assertSame(1, $resumen['trabajadores'][0]['asistencias']);
        $this->assertEqualsWithDelta(30.0, $resumen['total_pago_general'], 0.01);
    }

    public function test_ruta_descarga_csv(): void
    {
        $trabajador = Trabajador::factory()->create(['pago_diario' => 40]);
        Asistencia::create(['trabajador_id' => $trabajador->id, 'fecha' => now()->toDateString(), 'hora_entrada' => '08:00']);

        $respuesta = $this->actingAs($this->admin)
            ->get(route('reportes.personal', ['export' => 'csv', 'mes' => now()->format('Y-m')]));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('trabajador;pago_diario;asistencias;porcentaje_asistencia;hora_promedio;total_pago;sin_jornal;activo', $contenido);
        $this->assertStringContainsString('40', $contenido);
    }
}
