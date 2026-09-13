<?php

namespace Tests\Feature\Reportes;

use App\Models\Automotor;
use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Reportes\ReporteClientesService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteClientesTest extends TestCase
{
    use RefreshDatabase;

    private ReporteClientesService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteClientesService::class);
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

    public function test_gasto_suma_operaciones_de_lavados_y_cambios_confirmados(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->create();

        Lavado::factory()->create([
            'cliente_id' => $cliente->id,
            'user_id' => $usuario->id,
            'fecha' => now()->toDateString(),
            'estado' => 'confirmado',
            'total' => 50,
        ]);

        CambioAceite::factory()->create([
            'cliente_id' => $cliente->id,
            'trabajador_id' => Trabajador::factory()->create()->id,
            'user_id' => $usuario->id,
            'estado' => 'confirmado',
            'total' => 200,
            'created_at' => now(),
        ]);

        CambioAceite::factory()->create([
            'cliente_id' => $cliente->id,
            'trabajador_id' => Trabajador::factory()->create()->id,
            'user_id' => $usuario->id,
            'estado' => 'pendiente',
            'total' => 999,
            'created_at' => now(),
        ]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $top = $this->service->topClientes($desde, $hasta);

        $this->assertCount(1, $top);
        $this->assertSame($cliente->nombre_completo, $top->first()['nombre']);
        $this->assertEqualsWithDelta(250, $top->first()['gasto'], 0.01);
        $this->assertSame(2, $top->first()['visitas']);
    }

    public function test_top_automotores_combina_fuentes(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $placa = 'ABC123';

        Automotor::create([
            'placa' => $placa,
            'cliente_id' => $cliente->id,
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
        ]);

        Lavado::factory()->create([
            'cliente_id' => $cliente->id,
            'automotor_id' => $placa,
            'user_id' => $usuario->id,
            'fecha' => now()->toDateString(),
            'estado' => 'confirmado',
            'total' => 40,
        ]);

        CambioAceite::factory()->create([
            'cliente_id' => $cliente->id,
            'automotor_id' => $placa,
            'trabajador_id' => Trabajador::factory()->create()->id,
            'user_id' => $usuario->id,
            'estado' => 'confirmado',
            'total' => 160,
            'created_at' => now(),
        ]);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $top = $this->service->topAutomotores($desde, $hasta);

        $this->assertCount(1, $top);
        $this->assertSame($placa, $top->first()['placa']);
        $this->assertEqualsWithDelta(200, $top->first()['ingresos'], 0.01);
        $this->assertSame(2, $top->first()['visitas']);
    }

    public function test_ruta_descarga_csv(): void
    {
        $cliente = Cliente::factory()->create();
        Lavado::factory()->create([
            'cliente_id' => $cliente->id,
            'fecha' => now()->toDateString(),
            'estado' => 'confirmado',
            'total' => 80,
        ]);

        $respuesta = $this->actingAs($this->admin)
            ->get(route('reportes.clientes', ['export' => 'csv', 'desde' => now()->toDateString(), 'hasta' => now()->toDateString()]));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('cliente;gasto;visitas;automotores;visitas_por_mes', $contenido);
        $this->assertStringContainsString('80', $contenido);
    }
}
