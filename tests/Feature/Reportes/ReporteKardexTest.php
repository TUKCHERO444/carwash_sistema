<?php

namespace Tests\Feature\Reportes;

use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\User;
use App\Services\Reportes\ReporteKardexService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteKardexTest extends TestCase
{
    use RefreshDatabase;

    private ReporteKardexService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteKardexService::class);
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

    public function test_agregado_entradas_salidas_y_saldo_neto(): void
    {
        $producto = Producto::factory()->create(['stock' => 7]);

        MovimientoKardex::create([
            'producto_id' => $producto->id,
            'tipo' => 'entrada',
            'fuente' => 'inventario',
            'cantidad' => 10,
            'stock_antes' => 0,
            'stock_despues' => 10,
            'origen_id' => 1,
            'usuario_id' => $this->admin->id,
            'fecha_movimiento' => now()->startOfDay(),
        ]);
        MovimientoKardex::create([
            'producto_id' => $producto->id,
            'tipo' => 'salida',
            'fuente' => 'venta',
            'cantidad' => 3,
            'stock_antes' => 10,
            'stock_despues' => 7,
            'origen_id' => 1,
            'usuario_id' => $this->admin->id,
            'fecha_movimiento' => now()->startOfDay(),
        ]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $agregado = $this->service->agregado($desde, $hasta);
        $fila = $agregado[0];

        $this->assertSame($producto->nombre, $fila['producto']);
        $this->assertSame(10, $fila['entradas']);
        $this->assertSame(3, $fila['salidas']);
        $this->assertSame(7, $fila['saldo_neto']);
        $this->assertSame(7, $fila['stock_actual']);
    }

    public function test_filtro_por_tipo(): void
    {
        $producto = Producto::factory()->create(['stock' => 10]);

        MovimientoKardex::create([
            'producto_id' => $producto->id,
            'tipo' => 'entrada',
            'fuente' => 'inventario',
            'cantidad' => 10,
            'stock_antes' => 0,
            'stock_despues' => 10,
            'origen_id' => 1,
            'usuario_id' => $this->admin->id,
            'fecha_movimiento' => now()->startOfDay(),
        ]);
        MovimientoKardex::create([
            'producto_id' => $producto->id,
            'tipo' => 'salida',
            'fuente' => 'venta',
            'cantidad' => 3,
            'stock_antes' => 10,
            'stock_despues' => 7,
            'origen_id' => 1,
            'usuario_id' => $this->admin->id,
            'fecha_movimiento' => now()->startOfDay(),
        ]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $soloSalidas = $this->service->agregado($desde, $hasta, ['tipo' => 'salida']);
        $this->assertSame(0, $soloSalidas[0]['entradas']);
        $this->assertSame(3, $soloSalidas[0]['salidas']);
    }

    public function test_ruta_descarga_csv(): void
    {
        $producto = Producto::factory()->create(['stock' => 10]);
        MovimientoKardex::create([
            'producto_id' => $producto->id,
            'tipo' => 'entrada',
            'fuente' => 'inventario',
            'cantidad' => 10,
            'stock_antes' => 0,
            'stock_despues' => 10,
            'origen_id' => 1,
            'usuario_id' => $this->admin->id,
            'fecha_movimiento' => now()->startOfDay(),
        ]);

        $respuesta = $this->actingAs($this->admin)
            ->get(route('reportes.kardex', ['export' => 'csv', 'desde' => now()->toDateString(), 'hasta' => now()->toDateString()]));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('fecha;producto;tipo;fuente;cantidad;stock_antes;stock_despues;usuario', $contenido);
        $this->assertStringContainsString('entrada', $contenido);
    }
}
