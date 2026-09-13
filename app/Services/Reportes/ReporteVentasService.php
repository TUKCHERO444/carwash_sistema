<?php

namespace App\Services\Reportes;

use App\Models\Venta;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reporte de ventas por rango con filtros por usuario, método de pago
 * y correlativo. Funciones puras de agregación.
 */
class ReporteVentasService
{
    /**
     * Query base: ventas en el rango (por DATE(created_at)) + filtros.
     */
    private function base(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Builder
    {
        $query = Venta::query()
            ->whereBetween('ventas.created_at', [$desde, $hasta]);

        if (! empty($filtros['user_id'])) {
            $query->where('user_id', $filtros['user_id']);
        }

        if (! empty($filtros['metodo_pago'])) {
            $query->where('metodo_pago', $filtros['metodo_pago']);
        }

        if (! empty($filtros['correlativo'])) {
            $query->where('correlativo', 'like', '%'.$filtros['correlativo'].'%');
        }

        return $query;
    }

    /**
     * @return array{ total: float, operaciones: int, ticket_promedio: float }
     */
    public function kpis(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        $query = $this->base($desde, $hasta, $filtros);

        $total = (float) (clone $query)->sum('total');
        $numero = (clone $query)->count();

        return [
            'total' => $total,
            'operaciones' => $numero,
            'ticket_promedio' => $numero > 0 ? round($total / $numero, 2) : 0.0,
        ];
    }

    /**
     * @return array<int, array{ usuario_id:int, usuario:string, total:float, operaciones:int }>
     */
    public function porUsuario(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        return $this->base($desde, $hasta, $filtros)
            ->selectRaw('ventas.user_id, users.name, SUM(ventas.total) as total, COUNT(*) as operaciones')
            ->join('users', 'users.id', '=', 'ventas.user_id')
            ->groupBy('ventas.user_id', 'users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($fila) => [
                'usuario_id' => (int) $fila->user_id,
                'usuario' => (string) $fila->name,
                'total' => round((float) $fila->total, 2),
                'operaciones' => (int) $fila->operaciones,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{ metodo:string, total:float, operaciones:int }>
     */
    public function porMetodo(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        return $this->base($desde, $hasta, $filtros)
            ->selectRaw('ventas.metodo_pago, SUM(ventas.total) as total, COUNT(*) as operaciones')
            ->groupBy('ventas.metodo_pago')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($fila) => [
                'metodo' => (string) $fila->metodo_pago,
                'total' => round((float) $fila->total, 2),
                'operaciones' => (int) $fila->operaciones,
            ])
            ->values()
            ->all();
    }

    public function detalle(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->base($desde, $hasta, $filtros)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function detalleColeccion(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Collection
    {
        return $this->base($desde, $hasta, $filtros)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get();
    }
}
