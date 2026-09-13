<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Normaliza rangos de fecha para los reportes.
 *
 * Sin parámetros devuelve los últimos 30 días (hoy incluido) usando now()
 * de la aplicación (America/Lima). Toda comparación posterior se hace con
 * `->toDateString()` para evitar desfases de zona horaria.
 */
final class DateRangeFiltro
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     *                                                                  [desde, hasta, etiqueta]
     */
    public static function aplicar(?string $desde, ?string $hasta): array
    {
        $hoy = CarbonImmutable::today();

        if (! $desde && ! $hasta) {
            $inicio = $hoy->subDays(29)->startOfDay();
            $fin = $hoy->endOfDay();

            return [$inicio, $fin, self::etiqueta($inicio, $fin)];
        }

        $inicio = self::parseFecha($desde) ?? $hoy->subDays(29);
        $fin = self::parseFecha($hasta) ?? $hoy;

        if ($fin->lt($inicio)) {
            [$inicio, $fin] = [$fin, $inicio];
        }

        return [
            $inicio->startOfDay(),
            $fin->endOfDay(),
            self::etiqueta($inicio, $fin),
        ];
    }

    /**
     * Serie completa de días 'Y-m-d' entre desde y hasta (sin huecos).
     *
     * @return string[]
     */
    public static function diasEntre(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $dias = [];
        $cursor = $desde->startOfDay();
        $fin = $hasta->startOfDay();

        while ($cursor->lte($fin)) {
            $dias[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $dias;
    }

    /**
     * 'Y-m' → [desde: inicio de mes, hasta: fin de mes]. Si el mes es null
     * o inválido se usa el mes en curso.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    public static function mes(?string $mes): array
    {
        $base = $mes !== null && preg_match('/^\d{4}-\d{2}$/', $mes)
            ? CarbonImmutable::createFromFormat('Y-m', $mes)
            : CarbonImmutable::today();

        if (! $base) {
            throw new InvalidArgumentException("Mes inválido: {$mes}");
        }

        $desde = $base->startOfMonth();
        $hasta = $base->endOfMonth();

        return [$desde, $hasta, $base->format('m/Y')];
    }

    /**
     * Parsea 'Y-m-d' de forma estricta; null si no es una fecha real.
     */
    private static function parseFecha(?string $fecha): ?CarbonImmutable
    {
        if (! $fecha || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return null;
        }

        $carbon = CarbonImmutable::createFromFormat('Y-m-d', $fecha);

        return $carbon ?: null;
    }

    /**
     * Etiqueta legible para el encabezado: "dd/mm/aaaa - dd/mm/aaaa".
     */
    private static function etiqueta(CarbonImmutable $inicio, CarbonImmutable $fin): string
    {
        return $inicio->format('d/m/Y').' - '.$fin->format('d/m/Y');
    }
}
