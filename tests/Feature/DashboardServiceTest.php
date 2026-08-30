<?php

namespace Tests\Feature;

use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Venta;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DashboardService;
    }

    public function test_resumen_general_solo_cuenta_lavados_y_cambios_confirmados(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 8, 15, 12, 0, 0));

        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $trabajador = Trabajador::factory()->create();

        // Venta del día de hoy
        Venta::create([
            'correlativo' => 'V0001',
            'total' => 100,
            'user_id' => $usuario->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Lavado confirmado hoy que SÍ cuenta
        Lavado::factory()->create([
            'cliente_id' => $cliente->id,
            'vehiculo_id' => $vehiculo->id,
            'user_id' => $usuario->id,
            'fecha' => now()->toDateString(),
            'estado' => 'confirmado',
            'total' => 50,
        ]);

        // Lavado pendiente hoy que NO cuenta
        Lavado::factory()->create([
            'cliente_id' => $cliente->id,
            'vehiculo_id' => $vehiculo->id,
            'user_id' => $usuario->id,
            'fecha' => now()->toDateString(),
            'estado' => 'pendiente',
            'total' => 999,
        ]);

        // Cambio de aceite confirmado hoy que SÍ cuenta
        CambioAceite::factory()->create([
            'cliente_id' => $cliente->id,
            'trabajador_id' => $trabajador->id,
            'user_id' => $usuario->id,
            'estado' => 'confirmado',
            'total' => 200,
            'created_at' => now(),
        ]);

        // Cambio de aceite pendiente hoy que NO cuenta
        CambioAceite::factory()->create([
            'cliente_id' => $cliente->id,
            'trabajador_id' => $trabajador->id,
            'user_id' => $usuario->id,
            'estado' => 'pendiente',
            'total' => 999,
            'created_at' => now(),
        ]);

        $resumen = $this->service->resumenGeneral();

        $this->assertEqualsWithDelta(350, $resumen['ingresos_hoy'], 0.01); // 100 + 50 + 200
        $this->assertEqualsWithDelta(350, $resumen['ingresos_mes'], 0.01);
        $this->assertSame(3, $resumen['operaciones_mes']); // NO incluye pendientes
        // ticket promedio = 350 / 3
        $this->assertEqualsWithDelta(116.67, $resumen['ticket_promedio_mes'], 0.01);

        CarbonImmutable::setTestNow();
    }

    public function test_ingresos_por_dia_normaliza_fechas_de_ventas_y_cambios(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 8, 15, 12, 0, 0));

        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $trabajador = Trabajador::factory()->create();

        // Venta hace 3 días (usa created_at)
        $venta = Venta::create([
            'correlativo' => 'V0002',
            'total' => 100,
            'user_id' => $usuario->id,
        ]);
        $venta->forceFill([
            'created_at' => now()->subDays(3)->startOfDay(),
            'updated_at' => now()->subDays(3)->startOfDay(),
        ])->save();

        // Cambio confirmado hace 3 días (usa created_at)
        CambioAceite::factory()->create([
            'cliente_id' => $cliente->id,
            'trabajador_id' => $trabajador->id,
            'user_id' => $usuario->id,
            'estado' => 'confirmado',
            'total' => 50,
            'created_at' => now()->subDays(3)->startOfDay(),
        ]);

        // Lavado confirmado hace 3 días (usa fecha date)
        Lavado::factory()->create([
            'cliente_id' => $cliente->id,
            'vehiculo_id' => $vehiculo->id,
            'user_id' => $usuario->id,
            'fecha' => now()->subDays(3)->toDateString(),
            'estado' => 'confirmado',
            'total' => 25,
        ]);

        $serie = $this->service->ingresosPorDia(7);

        $this->assertCount(7, $serie['dias']);
        $indiceHace3 = array_search(now()->subDays(3)->toDateString(), $serie['dias'], true);
        $this->assertNotFalse($indiceHace3);
        $this->assertEqualsWithDelta(100, $serie['ventas'][$indiceHace3], 0.01);
        $this->assertEqualsWithDelta(50, $serie['cambios'][$indiceHace3], 0.01);
        $this->assertEqualsWithDelta(25, $serie['lavados'][$indiceHace3], 0.01);
        $this->assertEqualsWithDelta(175, $serie['totals'][$indiceHace3], 0.01);

        CarbonImmutable::setTestNow();
    }

    public function test_metodo_pago_desglosa_mixto_y_excluye_pendientes(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 8, 15, 12, 0, 0));
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $trabajador = Trabajador::factory()->create();

        Venta::create([
            'correlativo' => 'V0003',
            'total' => 60,
            'user_id' => $usuario->id,
            'metodo_pago' => 'yape',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CambioAceite::factory()->create([
            'cliente_id' => $cliente->id, 'trabajador_id' => $trabajador->id,
            'user_id' => $usuario->id, 'total' => 100,
            'metodo_pago' => 'mixto', 'monto_efectivo' => 40, 'monto_yape' => 60, 'monto_izipay' => 0,
            'estado' => 'confirmado', 'created_at' => now(),
        ]);

        CambioAceite::factory()->create([
            'cliente_id' => $cliente->id, 'trabajador_id' => $trabajador->id,
            'user_id' => $usuario->id, 'total' => 999,
            'metodo_pago' => 'efectivo', 'estado' => 'pendiente', 'created_at' => now(),
        ]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $pagos = $this->service->metodoPago($desde, $hasta);

        $this->assertEqualsWithDelta(40, $pagos['efectivo'], 0.01);
        $this->assertEqualsWithDelta(120, $pagos['yape'], 0.01); // 60 venta + 60 mixto
        $this->assertEqualsWithDelta(0, $pagos['izipay'], 0.01);

        CarbonImmutable::setTestNow();
    }

    public function test_top_productos_combina_ventas_y_cambios_por_cantidad(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $trabajador = Trabajador::factory()->create();

        $aceite = Producto::factory()->create(['nombre' => 'Aceite 5W30']);
        $filtro = Producto::factory()->create(['nombre' => 'Filtro de aceite']);

        // Venta de 3 aceites
        $venta = Venta::create([
            'correlativo' => 'V0004',
            'user_id' => $usuario->id,
        ]);
        $venta->detalles()->create([
            'producto_id' => $aceite->id,
            'cantidad' => 3,
            'precio_unitario' => 10,
            'subtotal' => 30,
        ]);

        // Cambio de aceite usa 2 aceites y 1 filtro
        $cambio = CambioAceite::factory()->create([
            'cliente_id' => $cliente->id,
            'trabajador_id' => $trabajador->id,
            'user_id' => $usuario->id,
            'estado' => 'confirmado',
        ]);
        $cambio->productos()->attach($aceite->id, ['cantidad' => 2, 'precio' => 10, 'total' => 20]);
        $cambio->productos()->attach($filtro->id, ['cantidad' => 1, 'precio' => 5, 'total' => 5]);

        $top = $this->service->topProductos(5);

        $porNombre = $top->pluck('cantidad', 'nombre');
        $this->assertSame(5, $porNombre['Aceite 5W30']);
        $this->assertSame(1, $porNombre['Filtro de aceite']);
    }

    public function test_productos_stock_bajo_respeta_umbral_y_activo(): void
    {
        Producto::factory()->create(['stock' => 3, 'activo' => true]);   // bajo
        Producto::factory()->create(['stock' => 5, 'activo' => true]);   // límite (inclusive)
        Producto::factory()->create(['stock' => 1, 'activo' => false]);  // inactivo, NO cuenta
        Producto::factory()->create(['stock' => 20, 'activo' => true]);  // normal

        $bajos = $this->service->productosStockBajo(5);

        $this->assertCount(2, $bajos);
        $stocks = $bajos->pluck('stock')->all();
        $this->assertEqualsCanonicalizing([3, 5], $stocks);
    }
}
