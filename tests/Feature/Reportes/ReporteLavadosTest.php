<?php

namespace Tests\Feature\Reportes;

use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\Servicio;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Reportes\ReporteLavadosService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteLavadosTest extends TestCase
{
    use RefreshDatabase;

    private ReporteLavadosService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteLavadosService::class);
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

    private function lavadoEn(array $atributos = []): Lavado
    {
        return Lavado::factory()->confirmado()->create(array_merge([
            'fecha' => now()->toDateString(),
            'total' => 100,
        ], $atributos));
    }

    public function test_universo_confirmado_y_desgloses(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $servicio = Servicio::factory()->create();
        $trabajador = Trabajador::factory()->create();

        $lavado = $this->lavadoEn([
            'cliente_id' => $cliente->id,
            'vehiculo_id' => $vehiculo->id,
            'user_id' => $usuario->id,
            'total' => 120,
        ]);
        $lavado->servicios()->attach($servicio->id);
        $lavado->trabajadores()->attach($trabajador->id);

        // Pendiente en rango → fuera del universo
        Lavado::factory()->create([
            'cliente_id' => $cliente->id,
            'vehiculo_id' => $vehiculo->id,
            'user_id' => $usuario->id,
            'fecha' => now()->toDateString(),
            'estado' => 'pendiente',
            'total' => 999,
        ]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $kpis = $this->service->kpis($desde, $hasta);
        $this->assertSame(1, $kpis['operaciones']);
        $this->assertEqualsWithDelta(120, $kpis['total'], 0.01);

        $porVehiculo = $this->service->porVehiculo($desde, $hasta);
        $this->assertSame($vehiculo->nombre, $porVehiculo[0]['nombre']);

        $porServicio = $this->service->porServicio($desde, $hasta);
        $this->assertSame($servicio->nombre, $porServicio[0]['nombre']);

        $porTrabajador = $this->service->porTrabajador($desde, $hasta);
        $this->assertSame($trabajador->nombre_completo, $porTrabajador[0]['nombre']);
    }

    public function test_filtro_por_trabajador_via_pivot(): void
    {
        $trabajadorA = Trabajador::factory()->create();
        $trabajadorB = Trabajador::factory()->create();

        $lavadoA = $this->lavadoEn(['total' => 50]);
        $lavadoA->trabajadores()->attach($trabajadorA->id);

        $lavadoB = $this->lavadoEn(['total' => 500]);
        $lavadoB->trabajadores()->attach($trabajadorB->id);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $kpis = $this->service->kpis($desde, $hasta, ['trabajador_id' => $trabajadorA->id]);
        $this->assertSame(1, $kpis['operaciones']);
        $this->assertEqualsWithDelta(50, $kpis['total'], 0.01);
    }

    public function test_ruta_descarga_csv(): void
    {
        $this->lavadoEn(['total' => 30]);

        $respuesta = $this->actingAs($this->admin)
            ->get(route('reportes.lavados', ['export' => 'csv', 'desde' => now()->toDateString(), 'hasta' => now()->toDateString()]));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('id;fecha;cliente;placa;vehiculo;servicios;total;estado', $contenido);
        $this->assertStringContainsString('30', $contenido);
    }
}
