<?php

namespace Tests\Feature\Reportes;

use App\Models\Caja;
use App\Models\EgresoCaja;
use App\Models\User;
use App\Models\Venta;
use App\Services\CajaService;
use App\Services\Reportes\ReporteCajaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteCajaTest extends TestCase
{
    use RefreshDatabase;

    private ReporteCajaService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteCajaService::class);
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

    public function test_kpis_y_detalle_balance_cuadran_con_caja_service(): void
    {
        $usuario = User::factory()->create();

        $caja = Caja::factory()->create([
            'user_id' => $usuario->id,
            'estado' => 'cerrada',
            'monto_inicial' => 100,
            'fecha_apertura' => now(),
            'fecha_cierre' => now(),
        ]);

        Venta::create([
            'correlativo' => 'V1',
            'total' => 50,
            'user_id' => $usuario->id,
            'caja_id' => $caja->id,
            'metodo_pago' => 'efectivo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        EgresoCaja::create([
            'caja_id' => $caja->id,
            'monto' => 20,
            'descripcion' => 'Compra de repuestos',
            'tipo_pago' => 'efectivo',
            'user_id' => $usuario->id,
        ]);

        // Caja abierta sin cierre → fuera del reporte
        Caja::factory()->create(['user_id' => $usuario->id, 'estado' => 'abierta', 'fecha_cierre' => null]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $kpis = $this->service->kpis($desde, $hasta);
        $this->assertSame(1, $kpis['cajas']);
        $this->assertEqualsWithDelta(50, $kpis['ingresos'], 0.01);
        $this->assertEqualsWithDelta(20, $kpis['egresos'], 0.01);
        $this->assertEqualsWithDelta(30, $kpis['saldo_neto'], 0.01);

        $detalle = $this->service->detalle($desde, $hasta);
        $fila = $detalle->items()[0];
        $this->assertEqualsWithDelta(100, $fila['monto_inicial'], 0.01);
        $this->assertEqualsWithDelta(50, $fila['total_ingresos'], 0.01);
        $this->assertEqualsWithDelta(20, $fila['total_egresos'], 0.01);
        $this->assertEqualsWithDelta(130, $fila['balance_final'], 0.01);
        $this->assertEqualsWithDelta(
            app(CajaService::class)->calcularResumen($caja)['balance_final'],
            $fila['balance_final'],
            0.01
        );

        $egresos = $this->service->egresosPorDescripcion($desde, $hasta);
        $this->assertSame('Compra de repuestos', $egresos[0]['descripcion']);
        $this->assertEqualsWithDelta(20, $egresos[0]['total'], 0.01);
    }

    public function test_ruta_descarga_csv(): void
    {
        $usuario = User::factory()->create();
        Caja::factory()->create([
            'user_id' => $usuario->id,
            'estado' => 'cerrada',
            'monto_inicial' => 100,
            'fecha_apertura' => now(),
            'fecha_cierre' => now(),
        ]);

        $respuesta = $this->actingAs($this->admin)
            ->get(route('reportes.caja', ['export' => 'csv', 'desde' => now()->toDateString(), 'hasta' => now()->toDateString()]));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('caja;usuario;apertura;cierre;monto_inicial;total_ingresos;total_egresos;balance_final', $contenido);
        $this->assertStringContainsString('100', $contenido);
    }
}
