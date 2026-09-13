<?php

namespace Tests\Feature\Reportes;

use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Venta;
use App\Services\Reportes\ReporteIngresosService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteIngresosTest extends TestCase
{
    use RefreshDatabase;

    private ReporteIngresosService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteIngresosService::class);
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

    public function test_consolidado_solo_cuenta_confirmados_y_desglosa_fuentes(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $trabajador = Trabajador::factory()->create();

        Venta::create([
            'correlativo' => 'V0001',
            'total' => 100,
            'user_id' => $usuario->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Lavado::factory()->create([
            'cliente_id' => $cliente->id,
            'vehiculo_id' => $vehiculo->id,
            'user_id' => $usuario->id,
            'fecha' => now()->toDateString(),
            'estado' => 'confirmado',
            'total' => 50,
        ]);

        Lavado::factory()->create([
            'cliente_id' => $cliente->id,
            'vehiculo_id' => $vehiculo->id,
            'user_id' => $usuario->id,
            'fecha' => now()->toDateString(),
            'estado' => 'pendiente',
            'total' => 999,
        ]);

        CambioAceite::factory()->create([
            'cliente_id' => $cliente->id,
            'trabajador_id' => $trabajador->id,
            'user_id' => $usuario->id,
            'estado' => 'confirmado',
            'total' => 200,
            'created_at' => now(),
        ]);

        CambioAceite::factory()->create([
            'cliente_id' => $cliente->id,
            'trabajador_id' => $trabajador->id,
            'user_id' => $usuario->id,
            'estado' => 'pendiente',
            'total' => 999,
            'created_at' => now(),
        ]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();
        $consolidado = $this->service->consolidado($desde, $hasta);

        $this->assertEqualsWithDelta(350, $consolidado['total'], 0.01);
        $this->assertSame(3, $consolidado['operaciones']);
        $this->assertEqualsWithDelta(116.67, $consolidado['ticket_promedio'], 0.01);
        $this->assertEqualsWithDelta(100, $consolidado['ventas'], 0.01);
        $this->assertEqualsWithDelta(50, $consolidado['lavados'], 0.01);
        $this->assertEqualsWithDelta(200, $consolidado['cambios'], 0.01);
    }

    public function test_serie_diaria_suma_el_consolidado_y_marca_actividad_dominante(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $trabajador = Trabajador::factory()->create();

        Venta::create([
            'correlativo' => 'V0002',
            'total' => 80,
            'user_id' => $usuario->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CambioAceite::factory()->create([
            'cliente_id' => $cliente->id,
            'trabajador_id' => $trabajador->id,
            'user_id' => $usuario->id,
            'estado' => 'confirmado',
            'total' => 20,
            'created_at' => now(),
        ]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $serie = $this->service->serieDiaria($desde, $hasta);
        $consolidado = $this->service->consolidado($desde, $hasta);

        $this->assertCount(1, $serie['dias']);
        $this->assertEqualsWithDelta($consolidado['total'], $serie['totals'][0], 0.01);

        $top = $this->service->diasTop($desde, $hasta, 10);
        $this->assertCount(1, $top);
        // 80/100 = 80% → ventas domina
        $this->assertSame('ventas', $top[0]['actividad_dominante']);
    }

    public function test_metodo_pago_desglosa_mixto(): void
    {
        $usuario = User::factory()->create();

        Venta::create([
            'correlativo' => 'V0003',
            'total' => 60,
            'user_id' => $usuario->id,
            'metodo_pago' => 'yape',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CambioAceite::factory()->create([
            'trabajador_id' => Trabajador::factory()->create()->id,
            'user_id' => $usuario->id,
            'total' => 100,
            'metodo_pago' => 'mixto',
            'monto_efectivo' => 40,
            'monto_yape' => 60,
            'monto_izipay' => 0,
            'estado' => 'confirmado',
            'created_at' => now(),
        ]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();
        $pagos = $this->service->metodoPago($desde, $hasta);

        $this->assertEqualsWithDelta(40, $pagos['efectivo'], 0.01);
        $this->assertEqualsWithDelta(120, $pagos['yape'], 0.01);
        $this->assertEqualsWithDelta(0, $pagos['izipay'], 0.01);
    }

    public function test_ruta_descarga_csv_con_bom_y_cabeceras(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->get(route('reportes.ingresos', ['export' => 'csv', 'desde' => now()->toDateString(), 'hasta' => now()->toDateString()]));

        $respuesta->assertOk();
        $this->assertStringContainsString('attachment', $respuesta->headers->get('Content-Disposition'));
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString("\xEF\xBB\xBF", $contenido);
        $this->assertStringContainsString('fecha;ventas;lavados;cambio_aceite;total', $contenido);
    }
}
