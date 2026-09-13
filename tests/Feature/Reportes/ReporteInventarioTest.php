<?php

namespace Tests\Feature\Reportes;

use App\Models\CambioAceite;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Venta;
use App\Services\Reportes\ReporteInventarioService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteInventarioTest extends TestCase
{
    use RefreshDatabase;

    private ReporteInventarioService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteInventarioService::class);
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

    public function test_top_combina_ventas_y_cambios_por_cantidad(): void
    {
        $usuario = User::factory()->create();
        $aceite = Producto::factory()->create(['nombre' => 'Aceite 5W30']);
        $filtro = Producto::factory()->create(['nombre' => 'Filtro A']);

        $venta = Venta::create([
            'correlativo' => 'V1',
            'user_id' => $usuario->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $venta->detalles()->create([
            'producto_id' => $aceite->id,
            'cantidad' => 3,
            'precio_unitario' => 10,
            'subtotal' => 30,
        ]);

        $cambio = CambioAceite::factory()->create([
            'trabajador_id' => Trabajador::factory()->create()->id,
            'user_id' => $usuario->id,
            'estado' => 'confirmado',
            'created_at' => now(),
        ]);
        $cambio->productos()->attach($aceite->id, ['cantidad' => 2, 'precio' => 10, 'total' => 20]);
        $cambio->productos()->attach($filtro->id, ['cantidad' => 1, 'precio' => 5, 'total' => 5]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $top = $this->service->topPorCantidad($desde, $hasta);

        $porNombre = $top->pluck('cantidad', 'nombre');
        $this->assertSame(5, $porNombre['Aceite 5W30']);
        $this->assertSame(1, $porNombre['Filtro A']);

        $porIngreso = $this->service->topPorIngreso($desde, $hasta);
        $this->assertSame('Aceite 5W30', $porIngreso->first()['nombre']);
        $this->assertEqualsWithDelta(50, $porIngreso->first()['ingreso'], 0.01);
    }

    public function test_stock_actual_y_resumenes_por_categoria_y_marca(): void
    {
        $categoria = Categoria::create(['nombre' => 'Aceites']);
        $marca = Marca::create(['nombre' => 'Mobil']);

        Producto::factory()->create([
            'nombre' => 'Aceite 1',
            'stock' => 10,
            'precio_compra' => 20,
            'categoria_id' => $categoria->id,
            'marca_id' => $marca->id,
        ]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();
        $filtros = ['categoria_id' => $categoria->id, 'marca_id' => $marca->id];

        $stock = $this->service->stockActual($filtros);
        $this->assertCount(1, $stock);
        $this->assertSame(10, $stock->first()->stock);

        $categorias = $this->service->resumenCategorias($desde, $hasta, $filtros);
        $this->assertSame($categoria->nombre, $categorias[0]['nombre']);
        $this->assertSame(10, $categorias[0]['stock_total']);

        $marcas = $this->service->resumenMarcas($desde, $hasta, $filtros);
        $this->assertSame($marca->nombre, $marcas[0]['nombre']);
    }

    public function test_ruta_descarga_csv(): void
    {
        Producto::factory()->create(['nombre' => 'Aceite X', 'stock' => 5]);

        $respuesta = $this->actingAs($this->admin)
            ->get(route('reportes.inventario', ['export' => 'csv', 'desde' => now()->toDateString(), 'hasta' => now()->toDateString()]));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('producto;categoria;marca;cantidad_vendida;ingreso', $contenido);
        $this->assertStringContainsString('Aceite X', $contenido);
    }
}
