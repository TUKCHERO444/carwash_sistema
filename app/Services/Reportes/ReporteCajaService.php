<?php

namespace App\Services\Reportes;

use App\Models\Caja;
use App\Models\EgresoCaja;
use App\Services\CajaService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reporte de caja: cajas cerradas en el rango (por fecha_cierre) con su
 * balance reutilizando {@see CajaService::calcularResumen}.
 */
class ReporteCajaService
{
    public function __construct(
        private readonly CajaService $cajaService,
    ) {}

    /**
     * @return array{ cajas:int, ingresos:float, egresos:float, saldo_neto:float }
     */
    public function kpis(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $cajas = $this->cerradasEnRango($desde, $hasta)->get();

        $ingresos = 0.0;
        $egresos = 0.0;

        foreach ($cajas as $caja) {
            $resumen = $this->cajaService->calcularResumen($caja);
            $ingresos += (float) $resumen['total_ingresos'];
            $egresos += (float) $resumen['total_egresos'];
        }

        return [
            'cajas' => $cajas->count(),
            'ingresos' => round($ingresos, 2),
            'egresos' => round($egresos, 2),
            'saldo_neto' => round($ingresos - $egresos, 2),
        ];
    }

    /**
     * Detalle paginado de cajas cerradas con resumen financiero por caja.
     */
    public function detalle(CarbonImmutable $desde, CarbonImmutable $hasta, int $perPage = 10): LengthAwarePaginator
    {
        $pagina = $this->cerradasEnRango($desde, $hasta)
            ->with('user:id,name')
            ->orderByDesc('fecha_cierre')
            ->paginate($perPage);

        $items = $pagina->getCollection()->map(function (Caja $caja) {
            $resumen = $this->cajaService->calcularResumen($caja);

            return [
                'id' => (int) $caja->id,
                'usuario' => (string) ($caja->user?->name ?? ''),
                'fecha_apertura' => $caja->fecha_apertura?->format('d/m/Y H:i'),
                'fecha_cierre' => $caja->fecha_cierre?->format('d/m/Y H:i'),
                'monto_inicial' => (float) $caja->monto_inicial,
                'total_ingresos' => round((float) $resumen['total_ingresos'], 2),
                'total_egresos' => round((float) $resumen['total_egresos'], 2),
                'balance_final' => round((float) $resumen['balance_final'], 2),
            ];
        });

        return $pagina->setCollection($items);
    }

    /**
     * @return Collection<int, EgresoCaja> egresos en el rango (por fecha_cierre de su caja).
     */
    public function egresos(CarbonImmutable $desde, CarbonImmutable $hasta): Collection
    {
        return EgresoCaja::whereHas('caja', function ($q) use ($desde, $hasta) {
            $q->where('estado', 'cerrada')->whereBetween('fecha_cierre', [$desde, $hasta]);
        })
            ->with(['caja:id,fecha_cierre', 'user:id,name'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return array<int, array{ descripcion:string, total:float, cantidad:int, tipo_pago:string }>
     */
    public function egresosPorDescripcion(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return EgresoCaja::whereHas('caja', function ($q) use ($desde, $hasta) {
            $q->where('estado', 'cerrada')->whereBetween('fecha_cierre', [$desde, $hasta]);
        })
            ->selectRaw('descripcion, tipo_pago, SUM(monto) as total, COUNT(*) as cantidad')
            ->groupBy('descripcion', 'tipo_pago')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($fila) => [
                'descripcion' => (string) $fila->descripcion,
                'total' => round((float) $fila->total, 2),
                'cantidad' => (int) $fila->cantidad,
                'tipo_pago' => (string) $fila->tipo_pago,
            ])
            ->values()
            ->all();
    }

    private function cerradasEnRango(CarbonImmutable $desde, CarbonImmutable $hasta): Builder
    {
        return Caja::cerrada()->whereBetween('fecha_cierre', [$desde, $hasta]);
    }
}
