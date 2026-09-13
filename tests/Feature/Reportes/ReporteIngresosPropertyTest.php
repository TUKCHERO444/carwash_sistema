<?php

namespace Tests\Feature\Reportes;

use App\Models\CambioAceite;
use App\Models\Lavado;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Venta;
use App\Services\Reportes\DateRangeFiltro;
use App\Services\Reportes\ReporteIngresosService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteIngresosPropertyTest extends TestCase
{
    use RefreshDatabase;

    private const ITERACIONES = 100;

    private ReporteIngresosService $service;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteIngresosService::class);
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 8, 20, 12, 0, 0));
        $this->usuario = User::factory()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * Feature: reportes, Property 2: La serie diaria suma exactamente el
     * consolidado y cada día es consistente con sus fuentes.
     *
     * For any random set of operations (ventas, lavados, cambios) over a
     * window ending today, the daily series sums (totals and per-source)
     * must equal the consolidated figures within ±0.01, and each day's
     * per-source value must match that source's real total for the day.
     *
     * **Valida: Requisitos 5, 6**
     */
    public function test_property_2_serie_diaria_sums_to_consolidado(): void
    {
        $desde = CarbonImmutable::now()->subDays(6)->startOfDay();
        $hasta = CarbonImmutable::now()->endOfDay();
        $fechas = DateRangeFiltro::diasEntre($desde, $hasta);

        for ($i = 0; $i < self::ITERACIONES; $i++) {
            Venta::query()->delete();
            Lavado::query()->delete();
            CambioAceite::query()->delete();

            $porDia = $this->crearOperacionesAleatorias($fechas, $i);

            $consolidado = $this->service->consolidado($desde, $hasta);
            $serie = $this->service->serieDiaria($desde, $hasta);

            $totalesEsperados = 0.0;
            $ventasTotales = 0.0;
            $lavadosTotales = 0.0;
            $cambiosTotales = 0.0;

            foreach ($fechas as $j => $fecha) {
                $datos = $porDia[$fecha];

                $this->assertEqualsWithDelta(
                    $datos['ventas'],
                    $serie['ventas'][$j],
                    0.01,
                    "Property 2 (iteración {$i}, día {$fecha}): ventas por día"
                );
                $this->assertEqualsWithDelta(
                    $datos['lavados'],
                    $serie['lavados'][$j],
                    0.01,
                    "Property 2 (iteración {$i}, día {$fecha}): lavados por día"
                );
                $this->assertEqualsWithDelta(
                    $datos['cambios'],
                    $serie['cambios'][$j],
                    0.01,
                    "Property 2 (iteración {$i}, día {$fecha}): cambios por día"
                );
                $this->assertEqualsWithDelta(
                    $datos['ventas'] + $datos['lavados'] + $datos['cambios'],
                    $serie['totals'][$j],
                    0.01,
                    "Property 2 (iteración {$i}, día {$fecha}): total por día"
                );

                $totalesEsperados += $datos['ventas'] + $datos['lavados'] + $datos['cambios'];
                $ventasTotales += $datos['ventas'];
                $lavadosTotales += $datos['lavados'];
                $cambiosTotales += $datos['cambios'];
            }

            $this->assertEqualsWithDelta(
                $totalesEsperados,
                $consolidado['total'],
                0.01,
                "Property 2 (iteración {$i}): sum(serie.totals) == consolidado.total"
            );
            $this->assertEqualsWithDelta($ventasTotales, $consolidado['ventas'], 0.01, "Property 2 (iteración {$i}): consolidado ventas");
            $this->assertEqualsWithDelta($lavadosTotales, $consolidado['lavados'], 0.01, "Property 2 (iteración {$i}): consolidado lavados");
            $this->assertEqualsWithDelta($cambiosTotales, $consolidado['cambios'], 0.01, "Property 2 (iteración {$i}): consolidado cambios");

            $diasTop = $this->service->diasTop($desde, $hasta, 10);
            $this->assertEqualsWithDelta(
                $totalesEsperados,
                array_sum(array_column($diasTop, 'total')),
                0.01,
                "Property 2 (iteración {$i}): diasTop conserva la suma"
            );
        }
    }

    /**
     * Crea operaciones aleatorias: confirmadas dentro del rango (que sí cuentan),
     * pendientes dentro del rango y confirmadas fuera del rango (que no cuentan).
     *
     * @param  string[]  $fechas
     * @return array<string, array{ventas: float, lavados: float, cambios: float}>
     */
    private function crearOperacionesAleatorias(array $fechas, int $iteracion): array
    {
        $porDia = [];
        foreach ($fechas as $fecha) {
            $porDia[$fecha] = ['ventas' => 0.0, 'lavados' => 0.0, 'cambios' => 0.0];
        }

        foreach ($fechas as $fecha) {
            $ayer = now()->subDays(7)->toDateString();

            for ($v = mt_rand(0, 3); $v > 0; $v--) {
                $total = mt_rand(10, 900) / 10;
                $porDia[$fecha]['ventas'] += $total;
                $venta = Venta::create([
                    'correlativo' => 'V-'.$iteracion.'-'.$fecha.'-'.$v,
                    'total' => $total,
                    'user_id' => $this->usuario->id,
                ]);
                $horaVenta = sprintf('%02d:%02d:00', mt_rand(0, 23), mt_rand(0, 59));
                Venta::whereKey($venta->id)->update([
                    'created_at' => $fecha.' '.$horaVenta,
                    'updated_at' => $fecha.' '.$horaVenta,
                ]);
            }

            for ($l = mt_rand(0, 3); $l > 0; $l--) {
                $total = mt_rand(10, 900) / 10;
                $porDia[$fecha]['lavados'] += $total;
                Lavado::factory()->create([
                    'user_id' => $this->usuario->id,
                    'fecha' => $fecha,
                    'estado' => 'confirmado',
                    'total' => $total,
                ]);
            }

            for ($c = mt_rand(0, 3); $c > 0; $c--) {
                $total = mt_rand(10, 900) / 10;
                $porDia[$fecha]['cambios'] += $total;
                $cambio = CambioAceite::factory()->create([
                    'trabajador_id' => Trabajador::factory()->create()->id,
                    'user_id' => $this->usuario->id,
                    'estado' => 'confirmado',
                    'total' => $total,
                ]);
                $horaCambio = sprintf('%02d:%02d:00', mt_rand(0, 23), mt_rand(0, 59));
                CambioAceite::whereKey($cambio->id)->update([
                    'created_at' => $fecha.' '.$horaCambio,
                    'updated_at' => $fecha.' '.$horaCambio,
                ]);
            }

            // Pendientes dentro del rango: NUNCA deben contar
            Lavado::factory()->create([
                'user_id' => $this->usuario->id,
                'fecha' => $fecha,
                'estado' => 'pendiente',
                'total' => 99999,
            ]);
            $cambio = CambioAceite::factory()->create([
                'trabajador_id' => Trabajador::factory()->create()->id,
                'user_id' => $this->usuario->id,
                'estado' => 'pendiente',
                'total' => 99999,
            ]);
            CambioAceite::whereKey($cambio->id)->update(['created_at' => $fecha.' 12:00:00']);

            // Confirmados fuera del rango: NUNCA deben contar
            Lavado::factory()->create([
                'user_id' => $this->usuario->id,
                'fecha' => $ayer,
                'estado' => 'confirmado',
                'total' => 99999,
            ]);
        }

        return $porDia;
    }
}
