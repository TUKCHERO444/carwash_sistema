<?php

namespace Tests\Feature\Reportes;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Reportes\ReporteComprasService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteComprasProveedorTest extends TestCase
{
    use RefreshDatabase;

    private ReporteComprasService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteComprasService::class);

        Permission::firstOrCreate(['name' => 'acceso-reportes', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('acceso-reportes');
    }

    private function compraRecibida(Proveedor $proveedor, float $total, string $fecha): Compra
    {
        return Compra::factory()->recibida()->create([
            'proveedor_id' => $proveedor->id,
            'user_id' => $this->admin->id,
            'fecha' => $fecha,
            'subtotal' => $total,
            'total' => $total,
        ]);
    }

    private function linea(Compra $compra, Producto $producto, int $cantidad, float $costo): void
    {
        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'costo_unitario' => $costo,
            'subtotal' => round($cantidad * $costo, 2),
        ]);
    }

    public function test_universo_solo_recibidas_en_kpis_y_cerradas_en_historial(): void
    {
        $proveedor = Proveedor::factory()->create();
        $aceite = Producto::factory()->create(['nombre' => 'Aceite 5W30']);
        $hoy = now()->toDateString();

        $recibida = $this->compraRecibida($proveedor, 200, $hoy);
        $this->linea($recibida, $aceite, 4, 50);

        // Ruido: borrador (sin efecto) y anulada (revertida) no suman al volumen.
        Compra::factory()->borrador()->create([
            'proveedor_id' => $proveedor->id, 'user_id' => $this->admin->id,
            'fecha' => $hoy, 'total' => 9999,
        ]);
        $anulada = Compra::factory()->create([
            'proveedor_id' => $proveedor->id, 'user_id' => $this->admin->id,
            'fecha' => $hoy, 'estado' => 'anulada', 'correlativo' => 'CMP-0900',
            'subtotal' => 500, 'total' => 500, 'fecha_recepcion' => now(),
        ]);
        $this->linea($anulada, $aceite, 10, 50);

        $desde = CarbonImmutable::today()->subDays(30)->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $kpis = $this->service->kpis($proveedor->id, $desde, $hasta);
        $this->assertSame(1, $kpis['compras']);
        $this->assertEqualsWithDelta(200, $kpis['total'], 0.01);
        $this->assertSame(1, $kpis['productos_distintos']);

        // El historial sí incluye la anulada con su estado.
        $historial = $this->service->historial($proveedor->id, $desde, $hasta);
        $this->assertSame(2, $historial->total());
        $this->assertEqualsCanonicalizing(['recibida', 'anulada'], $historial->getCollection()->pluck('estado')->all());

        // El resumen general solo cuenta recibidas.
        $resumen = $this->service->resumenProveedores($desde, $hasta);
        $this->assertCount(1, $resumen);
        $this->assertEqualsWithDelta(200, $resumen->first()['total'], 0.01);
    }

    public function test_frecuencia_y_evolucion_de_precios(): void
    {
        $proveedor = Proveedor::factory()->create();
        $aceite = Producto::factory()->create(['nombre' => 'Aceite 5W30']);

        $c1 = $this->compraRecibida($proveedor, 100, now()->subDays(10)->toDateString());
        $this->linea($c1, $aceite, 2, 50);
        $c2 = $this->compraRecibida($proveedor, 120, now()->subDays(2)->toDateString());
        $this->linea($c2, $aceite, 2, 60);

        $desde = CarbonImmutable::today()->subDays(30)->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $porProducto = $this->service->porProducto($proveedor->id, $desde, $hasta);
        $this->assertSame('Aceite 5W30', $porProducto[0]['nombre']);
        $this->assertSame(4, $porProducto[0]['cantidad']);
        $this->assertSame(2, $porProducto[0]['veces']);
        $this->assertEqualsWithDelta(60, $porProducto[0]['ultimo_costo'], 0.01);

        $evolucion = $this->service->evolucionPrecios($proveedor->id, $desde, $hasta);
        $this->assertCount(1, $evolucion);
        $this->assertCount(2, $evolucion[0]['registros']);
        $this->assertEqualsWithDelta(20.0, $evolucion[0]['variacion_pct'], 0.01);
    }

    public function test_html_resumen_y_detalle_por_proveedor(): void
    {
        $this->actingAs($this->admin);
        $proveedor = Proveedor::factory()->create();
        $aceite = Producto::factory()->create(['nombre' => 'Aceite 5W30']);
        $compra = $this->compraRecibida($proveedor, 200, now()->toDateString());
        $this->linea($compra, $aceite, 4, 50);

        // Sin proveedor: resumen general + mensaje de selección.
        $this->get(route('reportes.comprasProveedor'))
            ->assertOk()
            ->assertSee('Comparativo de proveedores')
            ->assertSee($proveedor->razon_social)
            ->assertSee('Seleccione un proveedor');

        // Con proveedor: ficha, KPIs, frecuencia, evolución e historial.
        $this->get(route('reportes.comprasProveedor', ['proveedor_id' => $proveedor->id]))
            ->assertOk()
            ->assertSee('Historial de compras')
            ->assertSee('Evolución de precios')
            ->assertSee($compra->correlativo);
    }

    public function test_export_csv_y_pdf_en_ambos_modos(): void
    {
        $this->actingAs($this->admin);
        $proveedor = Proveedor::factory()->create();
        $aceite = Producto::factory()->create(['nombre' => 'Aceite 5W30']);
        $compra = $this->compraRecibida($proveedor, 200, now()->toDateString());
        $this->linea($compra, $aceite, 4, 50);

        $csvResumen = $this->get(route('reportes.comprasProveedor', ['export' => 'csv']));
        $csvResumen->assertOk();
        $this->assertStringContainsString($proveedor->razon_social, $csvResumen->streamedContent());

        $csvDetalle = $this->get(route('reportes.comprasProveedor', ['proveedor_id' => $proveedor->id, 'export' => 'csv']));
        $csvDetalle->assertOk();
        $this->assertStringContainsString('Aceite 5W30', $csvDetalle->streamedContent());

        $this->get(route('reportes.comprasProveedor', ['export' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get(route('reportes.comprasProveedor', ['proveedor_id' => $proveedor->id, 'export' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_acceso_requiere_sesion_y_permiso(): void
    {
        $this->get(route('reportes.comprasProveedor'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->get(route('reportes.comprasProveedor'))->assertRedirect(route('dashboard'));
    }
}
