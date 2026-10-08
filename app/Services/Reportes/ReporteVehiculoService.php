<?php

namespace App\Services\Reportes;

use App\Models\Automotor;
use App\Models\CambioAceite;
use App\Models\Lavado;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginador;
use Illuminate\Support\Collection;

/**
 * Historial de atención por vehículo (placa).
 *
 * Universo: lavados y cambios de aceite en estado confirmado. Las ventas de
 * mostrador no se incluyen porque no se vinculan a ningún automotor.
 * Rango por fecha efectiva: lavados → `fecha`, cambios → `created_at`.
 * Funciones puras de agregación.
 */
class ReporteVehiculoService
{
    /**
     * Busca el automotor por placa normalizada (mayúsculas, sin espacios).
     */
    public function buscarAutomotor(string $placa): ?Automotor
    {
        return Automotor::with('cliente')
            ->where('placa', Automotor::normalizarPlaca($placa))
            ->first();
    }

    /**
     * Query base: lavados confirmados de la placa en el rango + filtros.
     */
    private function lavados(string $placa, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Builder
    {
        $query = Lavado::query()->where('lavados.estado', 'confirmado')
            ->where('lavados.automotor_id', $placa)
            ->whereDate('lavados.fecha', '>=', $desde->toDateString())
            ->whereDate('lavados.fecha', '<=', $hasta->toDateString());

        if (! empty($filtros['servicio_id'])) {
            $query->whereHas('servicios', fn (Builder $q) => $q->where('servicios.id', $filtros['servicio_id']));
        }

        return $query;
    }

    /**
     * Query base: cambios confirmados de la placa en el rango + filtros.
     */
    private function cambios(string $placa, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Builder
    {
        $query = CambioAceite::query()->where('cambio_aceites.estado', 'confirmado')
            ->where('cambio_aceites.automotor_id', $placa)
            ->whereBetween('cambio_aceites.created_at', [$desde, $hasta]);

        if (! empty($filtros['producto_id'])) {
            $query->whereHas('productos', fn (Builder $q) => $q->where('productos.id', $filtros['producto_id']));
        }

        return $query;
    }

    private function conTipo(string $placa, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros): bool
    {
        return empty($filtros['tipo']) || $filtros['tipo'] === 'lavado';
    }

    private function conCambios(string $placa, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros): bool
    {
        return empty($filtros['tipo']) || $filtros['tipo'] === 'cambio_aceite';
    }

    /**
     * @return array{ operaciones:int, lavados:int, cambios:int, total:float, ticket_promedio:float, primera_atencion:?string, ultima_atencion:?string, dias_promedio_entre_atenciones:?float, ultimo_cambio:?array{fecha:string, productos:string} }
     */
    public function kpis(string $placa, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        $usaLavados = $this->conTipo($placa, $desde, $hasta, $filtros);
        $usaCambios = $this->conCambios($placa, $desde, $hasta, $filtros);

        $qLavados = $usaLavados ? $this->lavados($placa, $desde, $hasta, $filtros) : null;
        $qCambios = $usaCambios ? $this->cambios($placa, $desde, $hasta, $filtros) : null;

        $nLavados = $qLavados ? (clone $qLavados)->count() : 0;
        $nCambios = $qCambios ? (clone $qCambios)->count() : 0;
        $total = round(
            ($qLavados ? (float) (clone $qLavados)->sum('total') : 0.0)
            + ($qCambios ? (float) (clone $qCambios)->sum('total') : 0.0),
            2
        );
        $operaciones = $nLavados + $nCambios;

        $fechas = $this->fechasAtencion($placa, $desde, $hasta, $filtros)->sort()->values();
        $primera = $fechas->first();
        $ultima = $fechas->last();
        $diasPromedio = null;
        if ($primera && $ultima && $operaciones > 1) {
            $diasPromedio = round($primera->diffInDays($ultima) / ($operaciones - 1), 1);
        }

        $ultimoCambio = null;
        if ($usaCambios) {
            $cambio = (clone $qCambios)->with('productos:id,nombre')->orderByDesc('created_at')->first();
            if ($cambio) {
                $ultimoCambio = [
                    'fecha' => $cambio->created_at?->format('d/m/Y H:i'),
                    'productos' => $cambio->productos->pluck('nombre')->implode(' + ') ?: '—',
                ];
            }
        }

        return [
            'operaciones' => $operaciones,
            'lavados' => $nLavados,
            'cambios' => $nCambios,
            'total' => $total,
            'ticket_promedio' => $operaciones > 0 ? round($total / $operaciones, 2) : 0.0,
            'primera_atencion' => $primera?->format('d/m/Y'),
            'ultima_atencion' => $ultima?->format('d/m/Y'),
            'dias_promedio_entre_atenciones' => $diasPromedio,
            'ultimo_cambio' => $ultimoCambio,
        ];
    }

    /**
     * Fechas efectivas de atención (lavados: fecha; cambios: created_at).
     *
     * @return Collection<int, CarbonImmutable|Carbon>
     */
    private function fechasAtencion(string $placa, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros): Collection
    {
        $fechas = collect();

        if ($this->conTipo($placa, $desde, $hasta, $filtros)) {
            $fechas = $fechas->merge(
                (clone $this->lavados($placa, $desde, $hasta, $filtros))->pluck('fecha')
            );
        }

        if ($this->conCambios($placa, $desde, $hasta, $filtros)) {
            $fechas = $fechas->merge(
                (clone $this->cambios($placa, $desde, $hasta, $filtros))->pluck('created_at')
            );
        }

        return $fechas->filter()->map(fn ($f) => $f instanceof CarbonImmutable ? $f : CarbonImmutable::parse($f));
    }

    /**
     * @return array<int, array{ nombre:string, operaciones:int }>
     */
    public function porServicio(string $placa, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        if (! $this->conTipo($placa, $desde, $hasta, $filtros)) {
            return [];
        }

        return $this->lavados($placa, $desde, $hasta, $filtros)
            ->selectRaw('servicios.nombre as servicio, COUNT(DISTINCT lavados.id) as operaciones')
            ->join('detalle_servicios', 'detalle_servicios.lavado_id', '=', 'lavados.id')
            ->join('servicios', 'servicios.id', '=', 'detalle_servicios.servicio_id')
            ->groupBy('servicios.nombre')
            ->orderByDesc('operaciones')
            ->get()
            ->map(fn ($fila) => [
                'nombre' => (string) $fila->servicio,
                'operaciones' => (int) $fila->operaciones,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{ nombre:string, cantidad:int, total:float }>
     */
    public function porProducto(string $placa, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        if (! $this->conCambios($placa, $desde, $hasta, $filtros)) {
            return [];
        }

        return $this->cambios($placa, $desde, $hasta, $filtros)
            ->selectRaw('productos.nombre as producto, SUM(cambio_productos.cantidad) as cantidad, SUM(cambio_productos.total) as total')
            ->join('cambio_productos', 'cambio_productos.cambio_aceite_id', '=', 'cambio_aceites.id')
            ->join('productos', 'productos.id', '=', 'cambio_productos.producto_id')
            ->groupBy('productos.nombre')
            ->orderByDesc('cantidad')
            ->get()
            ->map(fn ($fila) => [
                'nombre' => (string) $fila->producto,
                'cantidad' => (int) $fila->cantidad,
                'total' => round((float) $fila->total, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * Historial cronológico unificado (más reciente primero).
     *
     * @return Collection<int, array{ fecha:string, tipo:string, tipo_etiqueta:string, cliente:string, detalle:string, trabajadores:string, total:float, estado:string }>
     */
    public function historial(string $placa, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Collection
    {
        $items = collect();

        if ($this->conTipo($placa, $desde, $hasta, $filtros)) {
            $lavados = $this->lavados($placa, $desde, $hasta, $filtros)
                ->with(['cliente:id,nombre,apellido_paterno,apellido_materno', 'vehiculo:id,nombre', 'servicios:id,nombre', 'trabajadores:id,nombre,apellido_paterno,apellido_materno'])
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->get();

            foreach ($lavados as $lavado) {
                $items->push([
                    'fecha' => $lavado->fecha?->format('d/m/Y') ?? '—',
                    'orden' => ($lavado->fecha?->format('Y-m-d') ?? '').'#'.str_pad((string) $lavado->id, 10, '0', STR_PAD_LEFT),
                    'tipo' => 'lavado',
                    'tipo_etiqueta' => 'Lavado'.($lavado->vehiculo ? ' · '.$lavado->vehiculo->nombre : ''),
                    'cliente' => $lavado->cliente?->nombre_completo ?? '—',
                    'detalle' => $lavado->servicios->pluck('nombre')->implode(' + ') ?: '—',
                    'trabajadores' => $lavado->trabajadores->pluck('nombre_completo')->implode(' + ') ?: '—',
                    'total' => round((float) $lavado->total, 2),
                    'estado' => $lavado->estado,
                ]);
            }
        }

        if ($this->conCambios($placa, $desde, $hasta, $filtros)) {
            $cambios = $this->cambios($placa, $desde, $hasta, $filtros)
                ->with(['cliente:id,nombre,apellido_paterno,apellido_materno', 'trabajadores:id,nombre,apellido_paterno,apellido_materno', 'productos:id,nombre'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            foreach ($cambios as $cambio) {
                $items->push([
                    'fecha' => $cambio->created_at?->format('d/m/Y H:i') ?? '—',
                    'orden' => ($cambio->created_at?->format('Y-m-d H:i:s') ?? '').'#'.str_pad((string) $cambio->id, 10, '0', STR_PAD_LEFT),
                    'tipo' => 'cambio_aceite',
                    'tipo_etiqueta' => 'Cambio de aceite',
                    'cliente' => $cambio->cliente?->nombre_completo ?? '—',
                    'detalle' => $cambio->productos->pluck('nombre')->implode(' + ') ?: '—',
                    'trabajadores' => $cambio->trabajadores->pluck('nombre_completo')->implode(' + ')
                        ?: ($cambio->trabajador?->nombre_completo ?? '—'),
                    'total' => round((float) $cambio->total, 2),
                    'estado' => $cambio->estado,
                ]);
            }
        }

        return $items->sortByDesc('orden')->values();
    }

    public function historialPaginado(string $placa, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = [], int $pagina = 1, int $perPage = 10): LengthAwarePaginator
    {
        $coleccion = $this->historial($placa, $desde, $hasta, $filtros);

        return new Paginador(
            $coleccion->forPage($pagina, $perPage)->values(),
            $coleccion->count(),
            $perPage,
            $pagina,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
