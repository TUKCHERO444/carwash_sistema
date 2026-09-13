<?php

namespace App\Services\Reportes;

use App\Models\CambioAceite;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reporte de cambios de aceite. Universo: estado confirmado, rango por
 * DATE(created_at). Funciones puras de agregación.
 */
class ReporteCambioAceiteService
{
    /**
     * Query base: cambios confirmados en el rango + filtros.
     */
    private function base(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Builder
    {
        $query = CambioAceite::query()->where('cambio_aceites.estado', 'confirmado')
            ->whereBetween('cambio_aceites.created_at', [$desde, $hasta]);

        if (! empty($filtros['trabajador_id'])) {
            $query->where(function (Builder $q) use ($filtros) {
                $q->where('trabajador_id', $filtros['trabajador_id'])
                    ->orWhereHas('trabajadores', fn (Builder $p) => $p->where('trabajadores.id', $filtros['trabajador_id']));
            });
        }

        if (! empty($filtros['producto_id'])) {
            $query->whereHas('productos', fn (Builder $q) => $q->where('productos.id', $filtros['producto_id']));
        }

        return $query;
    }

    /**
     * @return array{ total: float, operaciones: int, ticket_promedio: float }
     */
    public function kpis(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        $query = $this->base($desde, $hasta, $filtros);

        $total = round((float) (clone $query)->sum('total'), 2);
        $numero = (clone $query)->count();

        return [
            'total' => $total,
            'operaciones' => $numero,
            'ticket_promedio' => $numero > 0 ? round($total / $numero, 2) : 0.0,
        ];
    }

    /**
     * @return array<int, array{ producto_id:int, nombre:string, cantidad:int, total:float }>
     */
    public function porProducto(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        return $this->base($desde, $hasta, $filtros)
            ->selectRaw('productos.id as producto_id, productos.nombre as producto, SUM(cambio_productos.cantidad) as cantidad, SUM(cambio_productos.total) as total')
            ->join('cambio_productos', 'cambio_productos.cambio_aceite_id', '=', 'cambio_aceites.id')
            ->join('productos', 'productos.id', '=', 'cambio_productos.producto_id')
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($fila) => [
                'producto_id' => (int) $fila->producto_id,
                'nombre' => (string) $fila->producto,
                'cantidad' => (int) $fila->cantidad,
                'total' => round((float) $fila->total, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{ trabajador_id:int, nombre:string, operaciones:int, total:float }>
     */
    public function porTrabajador(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        return $this->base($desde, $hasta, $filtros)
            ->selectRaw(
                'trabajadores.id as trabajador_id, trabajadores.nombre, trabajadores.apellido_paterno, trabajadores.apellido_materno, '
                .'COUNT(DISTINCT cambio_aceites.id) as operaciones, SUM(cambio_aceites.total) as total'
            )
            ->join('cambio_aceite_trabajadores', 'cambio_aceite_trabajadores.cambio_aceite_id', '=', 'cambio_aceites.id')
            ->join('trabajadores', 'trabajadores.id', '=', 'cambio_aceite_trabajadores.trabajador_id')
            ->groupBy('trabajadores.id', 'trabajadores.nombre', 'trabajadores.apellido_paterno', 'trabajadores.apellido_materno')
            ->orderByDesc('operaciones')
            ->get()
            ->map(function ($fila) {
                return [
                    'trabajador_id' => (int) $fila->trabajador_id,
                    'nombre' => trim(implode(' ', array_filter([
                        $fila->nombre,
                        $fila->apellido_paterno,
                        $fila->apellido_materno,
                    ]))),
                    'operaciones' => (int) $fila->operaciones,
                    'total' => round((float) $fila->total, 2),
                ];
            })
            ->values()
            ->all();
    }

    public function detalle(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->base($desde, $hasta, $filtros)
            ->with(['cliente:id,nombre,apellido_paterno,apellido_materno', 'automotor:placa,marca,modelo', 'trabajadores:id,nombre,apellido_paterno,apellido_materno', 'productos:id,nombre'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function detalleColeccion(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Collection
    {
        return $this->base($desde, $hasta, $filtros)
            ->with(['cliente', 'automotor', 'trabajadores', 'productos:id,nombre'])
            ->orderByDesc('created_at')
            ->get();
    }
}
