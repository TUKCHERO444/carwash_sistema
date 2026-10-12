<?php

namespace Tests\Feature\Reportes;

use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Reportes\ReporteVehiculoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteVehiculoTest extends TestCase
{
    use RefreshDatabase;

    private ReporteVehiculoService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteVehiculoService::class);

        Permission::firstOrCreate(['name' => 'acceso-reportes', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('acceso-reportes');
    }

    private function vehiculo(string $placa = 'ABC-123'): Vehiculo
    {
        $cliente = Cliente::factory()->create();

        return Vehiculo::create([
            'placa' => $placa,
            'cliente_id' => $cliente->id,
            'marca' => 'Toyota',
            'modelo' => 'Yaris',
        ]);
    }

    private function lavadoConfirmado(Vehiculo $vehiculo, array $extra = []): Lavado
    {
        $lavado = Lavado::factory()->confirmado()->create(array_merge([
            'cliente_id' => $vehiculo->cliente_id,
            'vehiculo_id' => $vehiculo->placa,
            'fecha' => now()->toDateString(),
            'total' => 50,
        ], $extra));

        return $lavado;
    }

    private function cambioConfirmado(Vehiculo $vehiculo, array $extra = []): CambioAceite
    {
        return CambioAceite::factory()->create(array_merge([
            'cliente_id' => $vehiculo->cliente_id,
            'vehiculo_id' => $vehiculo->placa,
            'estado' => 'confirmado',
            'total' => 120,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    public function test_universo_solo_confirmados_de_la_placa(): void
    {
        $vehiculo = $this->vehiculo();
        $trabajador = Trabajador::factory()->create();
        $servicio = Servicio::factory()->create(['nombre' => 'Lavado completo']);
        $aceite = Producto::factory()->create(['nombre' => 'Aceite 5W30']);

        $lavado = $this->lavadoConfirmado($vehiculo);
        $lavado->servicios()->attach($servicio->id);
        $lavado->trabajadores()->attach($trabajador->id);

        $cambio = $this->cambioConfirmado($vehiculo);
        $cambio->trabajadores()->attach($trabajador->id);
        $cambio->productos()->attach($aceite->id, ['cantidad' => 2, 'precio' => 50, 'total' => 100]);

        // Ruido: pendiente de la misma placa y confirmado de otra placa.
        $this->lavadoConfirmado($vehiculo, ['estado' => 'pendiente', 'total' => 999]);
        $otro = $this->vehiculo('XYZ-999');
        $this->lavadoConfirmado($otro, ['total' => 777]);

        $desde = CarbonImmutable::today()->subDays(30)->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $kpis = $this->service->kpis($vehiculo->placa, $desde, $hasta);

        $this->assertSame(2, $kpis['operaciones']);
        $this->assertSame(1, $kpis['lavados']);
        $this->assertSame(1, $kpis['cambios']);
        $this->assertEqualsWithDelta(170, $kpis['total'], 0.01);
        $this->assertNotNull($kpis['ultima_atencion']);
        $this->assertSame('Aceite 5W30', $kpis['ultimo_cambio']['productos']);

        $historial = $this->service->historial($vehiculo->placa, $desde, $hasta);
        $this->assertCount(2, $historial);
        $this->assertEqualsCanonicalizing(['lavado', 'cambio_aceite'], $historial->pluck('tipo')->all());

        $porServicio = $this->service->porServicio($vehiculo->placa, $desde, $hasta);
        $this->assertSame('Lavado completo', $porServicio[0]['nombre']);

        $porProducto = $this->service->porProducto($vehiculo->placa, $desde, $hasta);
        $this->assertSame('Aceite 5W30', $porProducto[0]['nombre']);
        $this->assertSame(2, $porProducto[0]['cantidad']);
    }

    public function test_filtro_tipo_limita_el_universo(): void
    {
        $vehiculo = $this->vehiculo();
        $this->lavadoConfirmado($vehiculo);
        $this->cambioConfirmado($vehiculo);

        $desde = CarbonImmutable::today()->subDays(30)->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $soloLavados = $this->service->kpis($vehiculo->placa, $desde, $hasta, ['tipo' => 'lavado']);
        $this->assertSame(1, $soloLavados['operaciones']);
        $this->assertSame(1, $soloLavados['lavados']);
        $this->assertSame(0, $soloLavados['cambios']);

        $soloCambios = $this->service->historial($vehiculo->placa, $desde, $hasta, ['tipo' => 'cambio_aceite']);
        $this->assertCount(1, $soloCambios);
        $this->assertSame('cambio_aceite', $soloCambios->first()['tipo']);
    }

    public function test_html_normaliza_placa_y_muestra_historial(): void
    {
        $this->actingAs($this->admin);
        $vehiculo = $this->vehiculo();
        $this->lavadoConfirmado($vehiculo);

        // Minúsculas y sin guion normalizado: la búsqueda es insensible.
        $this->get(route('reportes.vehiculo', ['placa' => 'abc-123']))
            ->assertOk()
            ->assertSee($vehiculo->placa)
            ->assertSee('Historial cronológico');

        // Sin placa: pide buscar.
        $this->get(route('reportes.vehiculo'))
            ->assertOk()
            ->assertSee('Ingrese la placa');

        // Placa inexistente: mensaje de no encontrado.
        $this->get(route('reportes.vehiculo', ['placa' => 'ZZZ-000']))
            ->assertOk()
            ->assertSee('No se encontr');
    }

    public function test_export_csv_y_pdf_con_placa_y_422_sin_placa(): void
    {
        $this->actingAs($this->admin);
        $vehiculo = $this->vehiculo();
        $this->lavadoConfirmado($vehiculo);

        $csv = $this->get(route('reportes.vehiculo', ['placa' => $vehiculo->placa, 'export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $this->assertStringContainsString($vehiculo->placa, $csv->streamedContent());

        $this->get(route('reportes.vehiculo', ['placa' => $vehiculo->placa, 'export' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->getJson(route('reportes.vehiculo', ['export' => 'csv']))->assertStatus(422);
        $this->getJson(route('reportes.vehiculo', ['export' => 'pdf']))->assertStatus(422);
    }

    public function test_acceso_requiere_sesion_y_permiso(): void
    {
        $this->get(route('reportes.vehiculo'))->assertRedirect(route('login'));

        $sinPermiso = User::factory()->create();
        $this->actingAs($sinPermiso);
        $this->get(route('reportes.vehiculo'))->assertRedirect(route('dashboard'));
    }
}
