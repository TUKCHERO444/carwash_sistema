<?php

namespace Tests\Feature\Reportes;

use App\Models\Asistencia;
use App\Models\Trabajador;
use App\Services\Reportes\ReportePersonalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportePersonalPropertyTest extends TestCase
{
    use RefreshDatabase;

    private const ITERACIONES = 100;

    private ReportePersonalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReportePersonalService::class);
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 8, 20, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * Feature: reportes, Property 4: El total de pago es asistencias × jornal
     * y el total general es la suma de los totales.
     *
     * For any random set of workers with random jornals (including null = "Sin
     * jornal") and random asistances inside the current month, each row's
     * total_pago must be asistencias × (pago_diario ?? 0), sin_jornal must
     * reflect a null jornal, and total_pago_general must be the sum.
     *
     * **Valida: Requisito 13**
     */
    public function test_property_4_pago_es_asistencias_por_jornal(): void
    {
        $mes = now()->format('Y-m');

        for ($i = 0; $i < self::ITERACIONES; $i++) {
            $trabajadores = $this->crearJornadasAleatorias();

            $resumen = $this->service->resumen($mes);

            $esperadoGeneral = 0.0;
            foreach ($trabajadores as $t) {
                $fila = collect($resumen['trabajadores'])->firstWhere('trabajador_id', $t['id']);

                $this->assertNotNull($fila, "Property 4 (iteración {$i}): trabajador {$t['id']} en el resumen");
                $this->assertSame($t['asistencias'], $fila['asistencias'], "Property 4 (iteración {$i}): asistencias de {$t['id']}");
                $this->assertSame($t['sin_jornal'], $fila['sin_jornal'], "Property 4 (iteración {$i}): flag sin_jornal de {$t['id']}");
                $this->assertEqualsWithDelta(
                    $t['asistencias'] * $t['jornal'],
                    $fila['total_pago'],
                    0.01,
                    "Property 4 (iteración {$i}): total_pago = asistencias × jornal de {$t['id']}"
                );

                $esperadoGeneral += $t['asistencias'] * $t['jornal'];
            }

            $this->assertEqualsWithDelta(
                $esperadoGeneral,
                $resumen['total_pago_general'],
                0.01,
                "Property 4 (iteración {$i}): total_pago_general es la suma"
            );

            Asistencia::query()->delete();
        }
    }

    /**
     * Feature: reportes, Property 5: El resumen está acotado por el mes y el
     * porcentaje de asistencia vive en [0, 100].
     *
     * For any random month, asistencias outside the month never leak into the
     * summary, every percentage falls within [0, 100], and total_dias_con_marca
     * equals the number of distinct dates that actually have a mark.
     *
     * **Valida: Requisitos 13, 18**
     */
    public function test_property_5_resumen_acotado_por_mes_y_porcentaje_en_rango(): void
    {
        $mes = now()->format('Y-m');

        for ($i = 0; $i < self::ITERACIONES; $i++) {
            $trabajadores = $this->crearJornadasAleatorias();
            $otrosMeses = $this->agregarMarcasFueraDelMes();

            $resumen = $this->service->resumen($mes);

            $diasTotalesEsperados = Asistencia::whereDate('fecha', '>=', now()->startOfMonth()->toDateString())
                ->whereDate('fecha', '<=', now()->endOfMonth()->toDateString())
                ->distinct('fecha')
                ->count('fecha');
            $this->assertSame($diasTotalesEsperados, $resumen['total_dias_con_marca'], "Property 5 (iteración {$i}): días con marca exactos");

            foreach ($resumen['trabajadores'] as $fila) {
                $this->assertGreaterThanOrEqual(0.0, $fila['porcentaje_asistencia'], "Property 5 (iteración {$i}): % >= 0");
                $this->assertLessThanOrEqual(100.0, $fila['porcentaje_asistencia'], "Property 5 (iteración {$i}): % <= 100");
            }

            $idsResumen = collect($resumen['trabajadores'])->pluck('trabajador_id')->all();
            foreach ($otrosMeses as $id) {
                $this->assertNotContains($id, $idsResumen, "Property 5 (iteración {$i}): marcas de otros meses no aparecen");
            }

            Asistencia::query()->delete();
        }
    }

    /**
     * @return array<int, array{id: int, jornal: float, sin_jornal: bool, asistencias: int}>
     */
    private function crearJornadasAleatorias(): array
    {
        $mes = now()->format('Y-m');
        $diasDelMes = range(1, 28);
        $trabajadores = [];

        foreach (range(1, mt_rand(1, 5)) as $n) {
            $sinJornal = mt_rand(0, 3) === 0;
            $jornal = $sinJornal ? 0.0 : mt_rand(10, 200);

            $trabajador = Trabajador::factory()->create(['pago_diario' => $sinJornal ? null : $jornal]);

            $asistencias = mt_rand(1, count($diasDelMes));
            $elegidas = collect($diasDelMes)->shuffle()->take($asistencias)->all();

            foreach ($elegidas as $dia) {
                Asistencia::create([
                    'trabajador_id' => $trabajador->id,
                    'fecha' => $mes.'-'.sprintf('%02d', $dia),
                    'hora_entrada' => sprintf('%02d:00', mt_rand(0, 23)),
                ]);
            }

            $trabajadores[] = [
                'id' => (int) $trabajador->id,
                'jornal' => $jornal,
                'sin_jornal' => $sinJornal,
                'asistencias' => $asistencias,
            ];
        }

        return $trabajadores;
    }

    /**
     * Agrega marcas en el mes anterior y el siguiente; devuelve los ids.
     *
     * @return int[]
     */
    private function agregarMarcasFueraDelMes(): array
    {
        $ids = [];

        foreach ([now()->subMonth(), now()->addMonth()] as $otroMes) {
            $trabajador = Trabajador::factory()->create();
            Asistencia::create([
                'trabajador_id' => $trabajador->id,
                'fecha' => $otroMes->startOfMonth()->addDays(mt_rand(0, 9))->toDateString(),
                'hora_entrada' => '08:00',
            ]);
            $ids[] = (int) $trabajador->id;
        }

        return $ids;
    }
}
