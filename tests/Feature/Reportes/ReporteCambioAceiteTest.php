<?php

namespace Tests\Feature\Reportes;

use App\Models\CambioAceite;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Reportes\ReporteCambioAceiteService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteCambioAceiteTest extends TestCase
{
    use RefreshDatabase;

    private ReporteCambioAceiteService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteCambioAceiteService::class);
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

    public function test_universo_confirmado_y_desgloses(): void
    {
        $usuario = User::factory()->create();
        $trabajador = Trabajador::factory()->create();
        $aceite = Producto::factory()->create(['nombre' => 'Aceite 5W30']);
        $filtro = Producto::factory()->create(['nombre' => 'Filtro A']);

        $cambio = CambioAceite::factory()->create([
            'user_id' => $usuario->id,
            'trabajador_id' => $trabajador->id,
            'estado' => 'confirmado',
            'total' => 120,
            'created_at' => now(),
        ]);
        $cambio->trabajadores()->attach($trabajador->id);
        $cambio->productos()->attach($aceite->id, ['cantidad' => 2, 'precio' => 50, 'total' => 100]);
        $cambio->productos()->attach($filtro->id, ['cantidad' => 1, 'precio' => 20, 'total' => 20]);

        CambioAceite::factory()->create([
            'user_id' => $usuario->id,
            'trabajador_id' => $trabajador->id,
            'estado' => 'pendiente',
            'total' => 999,
            'created_at' => now(),
        ]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $kpis = $this->service->kpis($desde, $hasta);
        $this->assertSame(1, $kpis['operaciones']);
        $this->assertEqualsWithDelta(120, $kpis['total'], 0.01);

        $porProducto = $this->service->porProducto($desde, $hasta);
        $this->assertSame('Aceite 5W30', $porProducto[0]['nombre']);
        $this->assertSame(2, $porProducto[0]['cantidad']);

        $porTrabajador = $this->service->porTrabajador($desde, $hasta);
        $this->assertSame($trabajador->nombre_completo, $porTrabajador[0]['nombre']);
    }

    public function test_filtro_por_producto_via_pivot(): void
    {
        $aceiteA = Producto::factory()->create(['nombre' => 'Aceite A']);
        $aceiteB = Producto::factory()->create(['nombre' => 'Aceite B']);

        $cambioA = CambioAceite::factory()->create(['estado' => 'confirmado', 'total' => 50, 'created_at' => now()]);
        $cambioA->productos()->attach($aceiteA->id, ['cantidad' => 1, 'precio' => 50, 'total' => 50]);

        $cambioB = CambioAceite::factory()->create(['estado' => 'confirmado', 'total' => 500, 'created_at' => now()]);
        $cambioB->productos()->attach($aceiteB->id, ['cantidad' => 1, 'precio' => 500, 'total' => 500]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $kpis = $this->service->kpis($desde, $hasta, ['producto_id' => $aceiteA->id]);
        $this->assertSame(1, $kpis['operaciones']);
        $this->assertEqualsWithDelta(50, $kpis['total'], 0.01);
    }

    public function test_ruta_descarga_csv(): void
    {
        $cambio = CambioAceite::factory()->create(['estado' => 'confirmado', 'total' => 90, 'created_at' => now()]);
        $cambio->productos()->attach(Producto::factory()->create()->id, ['cantidad' => 1, 'precio' => 90, 'total' => 90]);

        $respuesta = $this->actingAs($this->admin)
            ->get(route('reportes.cambioAceite', ['export' => 'csv', 'desde' => now()->toDateString(), 'hasta' => now()->toDateString()]));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('id;fecha;cliente;placa;trabajadores;productos;total;estado', $contenido);
        $this->assertStringContainsString('90', $contenido);
    }
}
