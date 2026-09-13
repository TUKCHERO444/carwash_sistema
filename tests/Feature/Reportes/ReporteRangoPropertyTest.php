<?php

namespace Tests\Feature\Reportes;

use App\Services\Reportes\DateRangeFiltro;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ReporteRangoPropertyTest extends TestCase
{
    private const ITERACIONES = 100;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 8, 20, 15, 30, 0));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * Feature: reportes, Property 1: Sin parámetros el rango por defecto son
     * los últimos 30 días terminando hoy, en hora de Perú.
     *
     * For any call with no params, aplicar() returns a range of exactly 30
     * calendar days ending today (America/Lima), with desde <= hasta.
     *
     * **Valida: Requisitos 4, 18**
     */
    public function test_property_1_default_range_is_30_days_ending_today(): void
    {
        for ($i = 0; $i < self::ITERACIONES; $i++) {
            [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar(null, null);

            $this->assertInstanceOf(CarbonImmutable::class, $desde, "Property 1 (iteración {$i}): desde es CarbonImmutable");
            $this->assertInstanceOf(CarbonImmutable::class, $hasta, "Property 1 (iteración {$i}): hasta es CarbonImmutable");
            $this->assertTrue($desde->lte($hasta), "Property 1 (iteración {$i}): desde <= hasta");
            $this->assertSame('America/Lima', $desde->timezoneName, "Property 1 (iteración {$i}): zona horaria Perú");

            $this->assertSame(
                now()->toDateString(),
                $hasta->toDateString(),
                "Property 1 (iteración {$i}): el rango por defecto termina hoy"
            );
            $this->assertSame(
                30,
                (int) $desde->diffInDays($hasta) + 1,
                "Property 1 (iteración {$i}): el rango por defecto tiene 30 días"
            );
            $this->assertStringContainsString(' - ', $etiqueta, "Property 1 (iteración {$i}): etiqueta con rango");
        }
    }

    /**
     * Feature: reportes, Property 1: Pares aleatorios se normalizan a un rango
     * estable y acotado, e idempotente.
     *
     * For any pair of date strings (including invalid ones or reversed order),
     * aplicar() returns a valid range with desde <= hasta (swapping if needed),
     * and re-applying the resulting strings yields the same bounds.
     *
     * **Valida: Requisitos 4, 18**
     */
    public function test_property_1_random_pairs_normalize_stably_and_idempotently(): void
    {
        for ($i = 0; $i < self::ITERACIONES; $i++) {
            $desde = $this->fechaAleatoria();
            $hasta = $this->fechaAleatoria();

            [$desdeT, $hastaT, $etiqueta] = DateRangeFiltro::aplicar($desde, $hasta);

            $this->assertTrue($desdeT->lte($hastaT), "Property 1 (iteración {$i}): rango normalizado desde <= hasta");
            $this->assertSame('00:00:00', $desdeT->format('H:i:s'), "Property 1 (iteración {$i}): desde al inicio del día");
            $this->assertSame('23:59:59', $hastaT->format('H:i:s'), "Property 1 (iteración {$i}): hasta al final del día");
            $this->assertStringContainsString(' - ', $etiqueta, "Property 1 (iteración {$i}): etiqueta con rango");

            [$desdeT2, $hastaT2] = DateRangeFiltro::aplicar($desdeT->toDateString(), $hastaT->toDateString());
            $this->assertTrue($desdeT->eq($desdeT2), "Property 1 (iteración {$i}): idempotente en desde");
            $this->assertTrue($hastaT->eq($hastaT2), "Property 1 (iteración {$i}): idempotente en hasta");
        }
    }

    /**
     * Feature: reportes, Property 1: Entradas inválidas caen al rango por defecto.
     *
     * For any garbage input, aplicar() falls back to the default 30-day range.
     *
     * **Valida: Requisitos 4, 18**
     */
    public function test_property_1_invalid_inputs_fall_back_to_default(): void
    {
        for ($i = 0; $i < self::ITERACIONES; $i++) {
            $basura = $i % 2 === 0 ? 'no-es-fecha' : '31/12/2026';

            [$desde, $hasta] = DateRangeFiltro::aplicar($basura, $basura);
            [$desdeDef, $hastaDef] = DateRangeFiltro::aplicar(null, null);

            $this->assertTrue($desde->eq($desdeDef), "Property 1 (iteración {$i}): inválido usa el rango por defecto");
            $this->assertTrue($hasta->eq($hastaDef), "Property 1 (iteración {$i}): inválido usa el rango por defecto");
        }
    }

    /**
     * Feature: reportes, Property 1: diasEntre cubre exactamente el rango sin huecos.
     *
     * For any normalized range, diasEntre returns all calendar days in order,
     * starting at desde and ending at hasta, without gaps or duplicates.
     *
     * **Valida: Requisito 6**
     */
    public function test_property_1_dias_entre_covers_range_exactly(): void
    {
        for ($i = 0; $i < self::ITERACIONES; $i++) {
            [$desde, $hasta] = DateRangeFiltro::aplicar($this->fechaAleatoria(), $this->fechaAleatoria());
            $dias = DateRangeFiltro::diasEntre($desde, $hasta);

            $this->assertSame((int) $desde->diffInDays($hasta) + 1, count($dias), "Property 1 (iteración {$i}): cantidad de días");
            $this->assertSame($desde->toDateString(), $dias[0], "Property 1 (iteración {$i}): primer día");
            $this->assertSame($hasta->toDateString(), end($dias), "Property 1 (iteración {$i}): último día");
            $this->assertSame(array_unique($dias), array_values($dias), "Property 1 (iteración {$i}): sin duplicados");
        }
    }

    private function fechaAleatoria(): ?string
    {
        if (mt_rand(0, 3) === 0) {
            return null;
        }

        $offset = mt_rand(-90, 90);

        return now()->addDays($offset)->toDateString();
    }
}
