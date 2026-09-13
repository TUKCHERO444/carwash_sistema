<?php

namespace App\Services\Reportes;

use App\Models\Lavado;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reporte de lavados (vehiculares). Universo: estado confirmado, rango por
 * DATE(fecha). Funciones puras de agregación.
 */
class ReporteLavadosService
{
    /**
     * Query base: lavados confirmados en el rango + filtros.
     */
    private function base(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Builder
    {
        $query = Lavado::query()->where('lavados.estado', 'confirmado')
            ->whereDate('lavados.fecha', '>=', $desde->toDateString())
            ->whereDate('lavados.fecha', '<=', $hasta->toDateString());

        if (! empty($filtros['vehiculo_id'])) {
            $query->where('vehiculo_id', $filtros['vehiculo_id']);
        }

        if (! empty($filtros['servicio_id'])) {
            $query->whereHas('servicios', fn (Builder $q) => $q->where('servicios.id', $filtros['servicio_id']));
        }

        if (! empty($filtros['trabajador_id'])) {
            $query->whereHas('trabajadores', fn (Builder $q) => $q->where('trabajadores.id', $filtros['trabajador_id']));
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
     * @return array<int, array{ vehiculo_id:int, nombre:string, total:float, operaciones:int }>
     */
    public function porVehiculo(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        return $this->base($desde, $hasta, $filtros)
            ->selectRaw('lavados.vehiculo_id, vehiculos.nombre as vehiculo, SUM(lavados.total) as total, COUNT(*) as operaciones')
            ->join('vehiculos', 'vehiculos.id', '=', 'lavados.vehiculo_id')
            ->groupBy('lavados.vehiculo_id', 'vehiculos.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($fila) => [
                'vehiculo_id' => (int) $fila->vehiculo_id,
                'nombre' => (string) $fila->vehiculo,
                'total' => round((float) $fila->total, 2),
                'operaciones' => (int) $fila->operaciones,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{ servicio_id:int, nombre:string, operaciones:int, total:float }>
     */
    public function porServicio(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        return $this->base($desde, $hasta, $filtros)
            ->selectRaw('servicios.id as servicio_id, servicios.nombre as servicio, COUNT(DISTINCT lavados.id) as operaciones, SUM(lavados.total) as total')
            ->join('detalle_servicios', 'detalle_servicios.lavado_id', '=', 'lavados.id')
            ->join('servicios', 'servicios.id', '=', 'detalle_servicios.servicio_id')
            ->groupBy('servicios.id', 'servicios.nombre')
            ->orderByDesc('operaciones')
            ->get()
            ->map(fn ($fila) => [
                'servicio_id' => (int) $fila->servicio_id,
                'nombre' => (string) $fila->servicio,
                'operaciones' => (int) $fila->operaciones,
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
                .'COUNT(DISTINCT lavados.id) as operaciones, SUM(lavados.total) as total'
            )
            ->join('lavado_trabajadores', 'lavado_trabajadores.lavado_id', '=', 'lavados.id')
            ->join('trabajadores', 'trabajadores.id', '=', 'lavado_trabajadores.trabajador_id')
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
            ->with(['cliente:id,nombre,apellido_paterno,apellido_materno', 'automotor:placa,marca,modelo', 'vehiculo:id,nombre', 'servicios:id,nombre', 'trabajadores:id,nombre,apellido_paterno,apellido_materno'])
            ->orderByDesc('fecha')
            ->paginate($perPage);
    }

    public function detalleColeccion(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Collection
    {
        return $this->base($desde, $hasta, $filtros)
            ->with(['cliente', 'automotor', 'vehiculo:id,nombre', 'servicios:id,nombre', 'trabajadores'])
            ->orderByDesc('fecha')
            ->get();
    }
}
