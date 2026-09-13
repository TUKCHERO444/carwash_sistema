<?php

namespace App\Services\Reportes;

use App\Models\CambioAceite;
use App\Models\Lavado;
use App\Models\Venta;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;

/**
 * Reporte de ingresos consolidados.
 *
 * Reutiliza el criterio de ingreso de {@see DashboardService}: ventas por
 * DATE(created_at), lavados confirmados por DATE(fecha) y cambios de aceite
 * confirmados por DATE(created_at). Funciones puras de agregación.
 */
class ReporteIngresosService
{
    private const FECHA_VENTAS = 'DATE(created_at)';

    private const FECHA_LAVADOS = 'DATE(fecha)';

    private const FECHA_CAMBIOS = 'DATE(created_at)';

    /**
     * @return array{
     *   total: float, operaciones: int, ticket_promedio: float,
     *   ventas: float, lavados: float, cambios: float,
     *   efectivo: float, yape: float, izipay: float,
     * }
     */
    public function consolidado(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $ventas = (float) Venta::whereBetween('created_at', [$desde, $hasta])->sum('total');
        $lavados = (float) Lavado::where('estado', 'confirmado')
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())->sum('total');
        $cambios = (float) CambioAceite::where('estado', 'confirmado')
            ->whereBetween('created_at', [$desde, $hasta])->sum('total');

        $operaciones = Venta::whereBetween('created_at', [$desde, $hasta])->count()
            + Lavado::where('estado', 'confirmado')
                ->whereDate('fecha', '>=', $desde->toDateString())
                ->whereDate('fecha', '<=', $hasta->toDateString())->count()
            + CambioAceite::where('estado', 'confirmado')
                ->whereBetween('created_at', [$desde, $hasta])->count();

        $metodo = app(DashboardService::class)->metodoPago($desde, $hasta);

        return [
            'total' => $ventas + $lavados + $cambios,
            'operaciones' => $operaciones,
            'ticket_promedio' => $operaciones > 0
                ? round(($ventas + $lavados + $cambios) / $operaciones, 2)
                : 0.0,
            'ventas' => $ventas,
            'lavados' => $lavados,
            'cambios' => $cambios,
            'efectivo' => $metodo['efectivo'],
            'yape' => $metodo['yape'],
            'izipay' => $metodo['izipay'],
        ];
    }

    /**
     * Serie diaria por fuente en el rango (sin huecos).
     *
     * @return array{
     *   dias: string[], ventas: float[], lavados: float[],
     *   cambios: float[], totals: float[],
     * }
     */
    public function serieDiaria(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $dias = DateRangeFiltro::diasEntre($desde, $hasta);

        $mapa = [];
        foreach ($dias as $fecha) {
            $mapa[$fecha] = ['ventas' => 0.0, 'lavados' => 0.0, 'cambios' => 0.0];
        }

        foreach (Venta::selectRaw(self::FECHA_VENTAS.' as fecha, SUM(total) as total')
            ->whereBetween('created_at', [$desde, $hasta])
            ->groupByRaw(self::FECHA_VENTAS)->get() as $fila) {
            $mapa[$this->fechaTexto($fila->fecha)]['ventas'] = (float) $fila->total;
        }

        foreach (Lavado::selectRaw(self::FECHA_LAVADOS.' as fecha, SUM(total) as total')
            ->where('estado', 'confirmado')
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->groupByRaw(self::FECHA_LAVADOS)->get() as $fila) {
            $mapa[$this->fechaTexto($fila->fecha)]['lavados'] = (float) $fila->total;
        }

        foreach (CambioAceite::selectRaw(self::FECHA_CAMBIOS.' as fecha, SUM(total) as total')
            ->where('estado', 'confirmado')
            ->whereBetween('created_at', [$desde, $hasta])
            ->groupByRaw(self::FECHA_CAMBIOS)->get() as $fila) {
            $mapa[$this->fechaTexto($fila->fecha)]['cambios'] = (float) $fila->total;
        }

        $ventas = [];
        $lavados = [];
        $cambios = [];
        $totals = [];

        foreach ($dias as $fecha) {
            $ventas[] = $mapa[$fecha]['ventas'];
            $lavados[] = $mapa[$fecha]['lavados'];
            $cambios[] = $mapa[$fecha]['cambios'];
            $totals[] = $mapa[$fecha]['ventas'] + $mapa[$fecha]['lavados'] + $mapa[$fecha]['cambios'];
        }

        return [
            'dias' => $dias,
            'ventas' => $ventas,
            'lavados' => $lavados,
            'cambios' => $cambios,
            'totals' => $totals,
        ];
    }

    /**
     * Top días por ingreso consolidado, ordenado descendente.
     * `actividad_dominante` es la fuente que aporta ≥50% del día, si existe.
     *
     * @return array<int, array{fecha:string, ventas:float, lavados:float, cambios:float, total:float, actividad_dominante:?string}>
     */
    public function diasTop(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite = 10): array
    {
        $serie = $this->serieDiaria($desde, $hasta);

        $dias = [];
        foreach ($serie['dias'] as $i => $fecha) {
            $ventas = $serie['ventas'][$i];
            $lavados = $serie['lavados'][$i];
            $cambios = $serie['cambios'][$i];
            $total = $serie['totals'][$i];

            $dias[] = [
                'fecha' => $fecha,
                'ventas' => $ventas,
                'lavados' => $lavados,
                'cambios' => $cambios,
                'total' => $total,
                'actividad_dominante' => $this->actividadDominante($ventas, $lavados, $cambios),
            ];
        }

        usort($dias, fn ($a, $b) => $b['total'] <=> $a['total']);

        return array_slice($dias, 0, $limite);
    }

    /**
     * @return array{ efectivo: float, yape: float, izipay: float }
     */
    public function metodoPago(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return app(DashboardService::class)->metodoPago($desde, $hasta);
    }

    /**
     * Fuente con mayor aporte si cubre al menos 50% del total del día.
     */
    private function actividadDominante(float $ventas, float $lavados, float $cambios): ?string
    {
        $total = $ventas + $lavados + $cambios;

        if ($total <= 0) {
            return null;
        }

        $candidatos = [
            'ventas' => $ventas,
            'lavados' => $lavados,
            'cambio_aceite' => $cambios,
        ];

        $mayor = null;
        $mayorValor = -1.0;
        foreach ($candidatos as $clave => $valor) {
            if ($valor > $mayorValor) {
                $mayor = $clave;
                $mayorValor = $valor;
            }
        }

        return ($mayorValor / $total) >= 0.5 ? $mayor : null;
    }

    private function fechaTexto(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        return (string) $fecha;
    }
}
