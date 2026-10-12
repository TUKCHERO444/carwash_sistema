<?php

namespace Tests\Feature\Reportes;

use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\Servicio;
use App\Models\TipoVehiculo;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\DashboardService;
use App\Services\Reportes\ReporteOperativoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteOperativoTest extends TestCase
{
    use RefreshDatabase;

    private ReporteOperativoService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteOperativoService::class);
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

    private function rangoHoy(): array
    {
        return [CarbonImmutable::today()->startOfDay(), CarbonImmutable::today()->endOfDay()];
    }

    private function lavadoConfirmado(array $atributos = []): Lavado
    {
        return Lavado::factory()->confirmado()->create(array_merge([
            'fecha' => now()->toDateString(),
            'total' => 100,
        ], $atributos));
    }

    private function cambioConfirmado(array $atributos = []): CambioAceite
    {
        return CambioAceite::factory()->create(array_merge([
            'estado' => 'confirmado',
            'total' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ], $atributos));
    }

    public function test_universo_solo_confirmados_y_ticket_operativo(): void
    {
        $this->lavadoConfirmado(['total' => 100, 'tiempo_atencion_minutos' => 30]);
        $this->cambioConfirmado(['total' => 200, 'tiempo_atencion_minutos' => 60]);

        // Pendientes fuera del universo aunque estén en rango.
        Lavado::factory()->create([
            'fecha' => now()->toDateString(), 'estado' => 'pendiente', 'total' => 999,
        ]);
        CambioAceite::factory()->create([
            'estado' => 'pendiente', 'total' => 999, 'created_at' => now(),
        ]);

        [$desde, $hasta] = $this->rangoHoy();
        $kpis = $this->service->kpis($desde, $hasta);

        $this->assertSame(2, $kpis['atenciones']);
        $this->assertSame(1, $kpis['lavados']);
        $this->assertSame(1, $kpis['cambios']);
        $this->assertEqualsWithDelta(300, $kpis['total'], 0.01);
        // Ticket operativo = (100 + 200) / 2, sin ventas de mostrador.
        $this->assertEqualsWithDelta(150, $kpis['ticket_promedio'], 0.01);
    }

    public function test_tiempo_promedio_solo_con_valor_y_sin_datos_es_nulo(): void
    {
        $this->lavadoConfirmado(['tiempo_atencion_minutos' => 20]);
        $this->lavadoConfirmado(['tiempo_atencion_minutos' => null, 'atencion_fin_at' => null]);
        $this->cambioConfirmado(['tiempo_atencion_minutos' => 40]);

        [$desde, $hasta] = $this->rangoHoy();
        $kpis = $this->service->kpis($desde, $hasta);

        $this->assertSame(3, $kpis['atenciones']);
        $this->assertSame(2, $kpis['con_tiempo']);
        $this->assertSame(1, $kpis['sin_tiempo']);
        $this->assertEqualsWithDelta(30, $kpis['tiempo_promedio'], 0.01);

        $tiempos = $this->service->tiempos($desde, $hasta);
        $this->assertEqualsWithDelta(20, $tiempos['lavados'], 0.01);
        $this->assertEqualsWithDelta(40, $tiempos['cambios'], 0.01);
        $this->assertEqualsWithDelta(30, $tiempos['global'], 0.01);

        // Rango vacío: ceros y nulos, sin división por cero.
        $ayer = CarbonImmutable::yesterday()->startOfDay();
        $vacios = $this->service->kpis($ayer, $ayer->endOfDay());
        $this->assertSame(0, $vacios['atenciones']);
        $this->assertNull($vacios['tiempo_promedio']);
        $this->assertSame(0.0, $vacios['ticket_promedio']);
        $this->assertSame(0.0, $vacios['atenciones_por_dia']);
    }

    public function test_vehiculos_unicos_excluye_sin_placa_y_serie_cuadra(): void
    {
        $cliente = Cliente::factory()->create();
        Vehiculo::create(['placa' => 'ABC-123', 'cliente_id' => $cliente->id]);
        Vehiculo::create(['placa' => 'XYZ-999', 'cliente_id' => $cliente->id]);

        $this->lavadoConfirmado(['vehiculo_id' => 'ABC-123']);
        // Misma placa en otro ticket: atenciones 2, únicos 1 para esa placa.
        $this->cambioConfirmado(['vehiculo_id' => 'ABC-123']);
        $this->cambioConfirmado(['vehiculo_id' => 'XYZ-999']);
        $this->lavadoConfirmado(['vehiculo_id' => null]);

        [$desde, $hasta] = $this->rangoHoy();
        $kpis = $this->service->kpis($desde, $hasta);

        $this->assertSame(4, $kpis['atenciones']);
        $this->assertSame(2, $kpis['vehiculos_unicos']);

        $serie = $this->service->serieDiaria($desde, $hasta);
        // Feature: dashboard-reportes-operativo, Property 1: la serie suma las atenciones.
        $this->assertSame($kpis['atenciones'], array_sum($serie['atenciones']));
        $indice = array_search(now()->toDateString(), $serie['dias'], true);
        $this->assertNotFalse($indice);
        $this->assertSame(4, $serie['atenciones'][$indice]);
        $this->assertSame(2, $serie['vehiculos_unicos'][$indice]);
    }

    public function test_por_trabajador_cuenta_participaciones_y_coatencion_en_ambos(): void
    {
        $tA = Trabajador::factory()->create();
        $tB = Trabajador::factory()->create();

        $lavado = $this->lavadoConfirmado();
        $lavado->trabajadores()->attach([$tA->id, $tB->id]);

        $cambio = $this->cambioConfirmado();
        $cambio->trabajadores()->attach($tA->id);

        [$desde, $hasta] = $this->rangoHoy();
        $filas = collect($this->service->porTrabajador($desde, $hasta))->keyBy('trabajador_id');

        // Co-atención: el lavado suma en ambos; A además tiene el cambio.
        $this->assertSame(2, $filas[$tA->id]['operaciones']);
        $this->assertSame(1, $filas[$tA->id]['lavados']);
        $this->assertSame(1, $filas[$tA->id]['cambios']);
        $this->assertSame(1, $filas[$tB->id]['operaciones']);
    }

    public function test_servicios_tipos_placas_y_hora_pico(): void
    {
        $servicio = Servicio::factory()->create();
        $tipoVehiculo = TipoVehiculo::factory()->create();
        $cliente = Cliente::factory()->create();
        Vehiculo::create(['placa' => 'PIK-001', 'cliente_id' => $cliente->id]);

        $lavado = $this->lavadoConfirmado([
            'tipo_vehiculo_id' => $tipoVehiculo->id,
            'vehiculo_id' => 'PIK-001',
            'atencion_inicio_at' => now()->setTime(9, 15),
        ]);
        $lavado->servicios()->attach($servicio->id);
        $this->cambioConfirmado([
            'vehiculo_id' => 'PIK-001',
            'atencion_inicio_at' => now()->setTime(9, 45),
        ]);
        $this->cambioConfirmado(['atencion_inicio_at' => now()->setTime(15, 5)]);

        [$desde, $hasta] = $this->rangoHoy();

        $porServicio = $this->service->porServicio($desde, $hasta);
        $this->assertSame($servicio->nombre, $porServicio[0]['nombre']);
        $this->assertSame(1, $porServicio[0]['operaciones']);

        $porTipo = $this->service->porTipoVehiculo($desde, $hasta);
        $this->assertSame($tipoVehiculo->nombre, $porTipo[0]['nombre']);

        $placas = $this->service->topPlacas($desde, $hasta);
        $this->assertSame('PIK-001', $placas[0]['placa']);
        $this->assertSame(2, $placas[0]['atenciones']);

        $porHora = $this->service->porHora($desde, $hasta);
        $this->assertSame(2, $porHora['horas'][9]);
        $this->assertSame(1, $porHora['horas'][15]);
        $this->assertSame(9, $porHora['hora_pico']);
        $this->assertSame(2, $porHora['atenciones_pico']);
    }

    public function test_clientes_recurrentes_y_filtros_de_fecha(): void
    {
        $recurrente = Cliente::factory()->create();
        $ocasional = Cliente::factory()->create();

        $this->lavadoConfirmado(['cliente_id' => $recurrente->id]);
        $this->cambioConfirmado(['cliente_id' => $recurrente->id]);
        $this->lavadoConfirmado(['cliente_id' => $ocasional->id]);

        // Fuera de rango: no contamina recurrencia ni atenciones.
        $this->lavadoConfirmado([
            'cliente_id' => $ocasional->id,
            'fecha' => now()->subDays(10)->toDateString(),
        ]);

        [$desde, $hasta] = $this->rangoHoy();
        $kpis = $this->service->kpis($desde, $hasta);

        $this->assertSame(3, $kpis['atenciones']);
        $this->assertSame(2, $kpis['total_clientes']);
        $this->assertSame(1, $kpis['clientes_recurrentes']);
        $this->assertEqualsWithDelta(50, $kpis['pct_recurrentes'], 0.01);
    }

    public function test_dashboard_reutiliza_las_mismas_reglas_del_reporte(): void
    {
        $this->lavadoConfirmado(['total' => 80, 'tiempo_atencion_minutos' => 25]);
        Lavado::factory()->create([
            'fecha' => now()->toDateString(), 'estado' => 'pendiente', 'total' => 500,
        ]);

        $desde = CarbonImmutable::today()->subDays(29)->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $resumen = app(DashboardService::class)->resumenOperativo($desde, $hasta);

        $this->assertSame(1, $resumen['atenciones']);
        $this->assertEqualsWithDelta(25, $resumen['tiempo_promedio'], 0.01);
        $this->assertArrayHasKey('vehiculos_unicos', $resumen);
        $this->assertArrayHasKey('atenciones_por_dia', $resumen);
    }

    public function test_ruta_html_csv_y_pdf(): void
    {
        $this->lavadoConfirmado(['total' => 30]);

        $params = ['desde' => now()->toDateString(), 'hasta' => now()->toDateString()];

        $this->withoutVite()->actingAs($this->admin)->get(route('reportes.operativo', $params))->assertOk();

        $csv = $this->actingAs($this->admin)->get(route('reportes.operativo', $params + ['export' => 'csv']));
        $csv->assertOk();
        $contenido = $csv->streamedContent();
        $this->assertStringContainsString('fecha;atenciones;vehiculos_unicos', $contenido);
        $this->assertStringContainsString(now()->toDateString(), $contenido);

        $pdf = $this->actingAs($this->admin)->get(route('reportes.operativo', $params + ['export' => 'pdf']));
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }
}
