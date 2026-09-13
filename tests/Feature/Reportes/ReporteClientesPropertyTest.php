<?php

namespace Tests\Feature\Reportes;

use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Reportes\DateRangeFiltro;
use App\Services\Reportes\ReporteClientesService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteClientesPropertyTest extends TestCase
{
    use RefreshDatabase;

    private const ITERACIONES = 100;

    private ReporteClientesService $service;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteClientesService::class);
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 8, 20, 12, 0, 0));
        $this->usuario = User::factory()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * Feature: reportes, Property 3: El gasto por cliente es la suma exacta de
     * sus operaciones confirmadas en el rango.
     *
     * For any random set of clients with random lavados/cambios (confirmed or
     * pending, in range or out of range), the top list must report for every
     * client: gasto == sum of confirmed totals in range, visitas == count of
     * confirmed operations in range, and rows ordered by gasto desc.
     *
     * **Valida: Requisito 11**
     */
    public function test_property_3_gasto_por_cliente_es_suma_exacta(): void
    {
        $desde = CarbonImmutable::now()->subDays(6)->startOfDay();
        $hasta = CarbonImmutable::now()->endOfDay();
        $fechas = DateRangeFiltro::diasEntre($desde, $hasta);

        for ($i = 0; $i < self::ITERACIONES; $i++) {
            Cliente::query()->delete();
            Lavado::query()->delete();
            CambioAceite::query()->delete();

            $esperado = [];
            $numClientes = mt_rand(1, 6);

            for ($c = 0; $c < $numClientes; $c++) {
                $cliente = Cliente::factory()->create();
                $esperado[$cliente->id] = ['gasto' => 0.0, 'visitas' => 0];

                for ($op = mt_rand(1, 4); $op > 0; $op--) {
                    $fecha = $fechas[array_rand($fechas)];
                    $esLavado = mt_rand(0, 1) === 1;
                    $total = mt_rand(10, 900) / 10;

                    if ($esLavado) {
                        Lavado::factory()->create([
                            'cliente_id' => $cliente->id,
                            'user_id' => $this->usuario->id,
                            'fecha' => $fecha,
                            'estado' => 'confirmado',
                            'total' => $total,
                        ]);
                    } else {
                        CambioAceite::factory()->create([
                            'cliente_id' => $cliente->id,
                            'trabajador_id' => Trabajador::factory()->create()->id,
                            'user_id' => $this->usuario->id,
                            'estado' => 'confirmado',
                            'total' => $total,
                        ]);
                    }

                    $esperado[$cliente->id]['gasto'] += $total;
                    $esperado[$cliente->id]['visitas'] += 1;
                }
            }

            // Una operación confirmada fuera del rango no debe contar
            Cliente::factory()->create();
            $fuera = Cliente::factory()->create();
            Lavado::factory()->create([
                'cliente_id' => $fuera->id,
                'user_id' => $this->usuario->id,
                'fecha' => now()->subMonth()->toDateString(),
                'estado' => 'confirmado',
                'total' => 99999,
            ]);

            $top = $this->service->topClientes($desde, $hasta);

            $porCliente = $top->keyBy('cliente_id');
            $this->assertCount(count($esperado), $top, "Property 3 (iteración {$i}): solo clientes con operaciones en rango");

            foreach ($esperado as $clienteId => $datos) {
                $this->assertArrayHasKey($clienteId, $porCliente, "Property 3 (iteración {$i}): cliente {$clienteId} presente");
                $this->assertEqualsWithDelta(
                    $datos['gasto'],
                    $porCliente[$clienteId]['gasto'],
                    0.01,
                    "Property 3 (iteración {$i}): gasto del cliente {$clienteId}"
                );
                $this->assertSame(
                    $datos['visitas'],
                    $porCliente[$clienteId]['visitas'],
                    "Property 3 (iteración {$i}): visitas del cliente {$clienteId}"
                );
            }

            $gastos = $top->pluck('gasto')->all();
            $ordenados = $gastos;
            rsort($ordenados);
            $this->assertSame($ordenados, $gastos, "Property 3 (iteración {$i}): orden descendente por gasto");

            $this->assertNull($porCliente->get($fuera->id), "Property 3 (iteración {$i}): sin fuga fuera del rango");
        }
    }
}
