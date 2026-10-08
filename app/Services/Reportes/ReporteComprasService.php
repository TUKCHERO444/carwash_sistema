<?php

namespace App\Services\Reportes;

use App\Models\Compra;
use App\Models\Proveedor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Compras por proveedor: historial de abastecimiento y comparativo.
 *
 * Universo del historial: compras cerradas (`recibida` + `anulada`), que son
 * las que tuvieron efecto real (las anuladas generaron salida compensatoria y
 * nota de crédito). Los borradores se excluyen: son editables y sin efecto en
 * inventario, Kardex o caja. Los KPIs y agregados se calculan solo sobre
 * `recibida` (abastecimiento vigente). Rango por `fecha` de compra.
 * Funciones puras de agregación.
 */
class ReporteComprasService
{
    /**
     * Query base: compras cerradas del rango + filtros.
     */
    private function base(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Builder
    {
        $query = Compra::query()->whereIn('compras.estado', ['recibida', 'anulada'])
            ->whereDate('compras.fecha', '>=', $desde->toDateString())
            ->whereDate('compras.fecha', '<=', $hasta->toDateString());

        if (! empty($filtros['proveedor_id'])) {
            $query->where('proveedor_id', $filtros['proveedor_id']);
        }

        if (! empty($filtros['producto_id'])) {
            $query->whereHas('detalles', fn (Builder $q) => $q->where('producto_id', $filtros['producto_id']));
        }

        return $query;
    }

    /**
     * Query base restringida a recibidas (abastecimiento vigente).
     */
    private function recibidas(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Builder
    {
        return $this->base($desde, $hasta, $filtros)->where('compras.estado', 'recibida');
    }

    /**
     * Resumen general para comparar proveedores (solo recibidas).
     *
     * @return Collection<int, array{ proveedor_id:int, razon_social:string, ruc:string, compras:int, total:float, ultima_compra:?string }>
     */
    public function resumenProveedores(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite = 20): Collection
    {
        $agregado = Compra::where('estado', 'recibida')
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->selectRaw('proveedor_id as id, SUM(total) as total, COUNT(*) as compras, MAX(fecha) as ultima')
            ->groupBy('proveedor_id')
            ->get()
            ->keyBy('id');

        if ($agregado->isEmpty()) {
            return collect();
        }

        return Proveedor::whereIn('id', $agregado->keys())
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'ruc'])
            ->map(fn (Proveedor $p) => [
                'proveedor_id' => (int) $p->id,
                'razon_social' => (string) $p->razon_social,
                'ruc' => (string) ($p->ruc ?? ''),
                'compras' => (int) $agregado[$p->id]->compras,
                'total' => round((float) $agregado[$p->id]->total, 2),
                'ultima_compra' => $agregado[$p->id]->ultima
                    ? CarbonImmutable::parse($agregado[$p->id]->ultima)->format('d/m/Y')
                    : null,
            ])
            ->sortByDesc('total')
            ->take($limite)
            ->values();
    }

    /**
     * @return array{ total:float, compras:int, ticket_promedio:float, ultima_compra:?string, productos_distintos:int }
     */
    public function kpis(int $proveedorId, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        $filtros['proveedor_id'] = $proveedorId;
        $query = $this->recibidas($desde, $hasta, $filtros);

        $total = round((float) (clone $query)->sum('total'), 2);
        $compras = (clone $query)->count();
        $ultima = (clone $query)->orderByDesc('fecha')->first();

        $productos = (clone $query)
            ->join('detalle_compras', 'detalle_compras.compra_id', '=', 'compras.id')
            ->distinct('detalle_compras.producto_id')
            ->count('detalle_compras.producto_id');

        return [
            'total' => $total,
            'compras' => $compras,
            'ticket_promedio' => $compras > 0 ? round($total / $compras, 2) : 0.0,
            'ultima_compra' => $ultima?->fecha?->format('d/m/Y'),
            'productos_distintos' => (int) $productos,
        ];
    }

    /**
     * Productos adquiridos con mayor frecuencia (solo recibidas).
     *
     * @return array<int, array{ nombre:string, cantidad:int, veces:int, total:float, ultimo_costo:float }>
     */
    public function porProducto(int $proveedorId, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        $filtros['proveedor_id'] = $proveedorId;

        return $this->recibidas($desde, $hasta, $filtros)
            ->selectRaw(
                'productos.nombre as producto, SUM(detalle_compras.cantidad) as cantidad, '
                .'COUNT(DISTINCT compras.id) as veces, SUM(detalle_compras.subtotal) as total'
            )
            ->join('detalle_compras', 'detalle_compras.compra_id', '=', 'compras.id')
            ->join('productos', 'productos.id', '=', 'detalle_compras.producto_id')
            ->groupBy('productos.nombre')
            ->orderByDesc('cantidad')
            ->get()
            ->map(function ($fila) use ($proveedorId, $desde, $hasta) {
                $ultimo = Compra::where('estado', 'recibida')
                    ->where('proveedor_id', $proveedorId)
                    ->whereDate('fecha', '>=', $desde->toDateString())
                    ->whereDate('fecha', '<=', $hasta->toDateString())
                    ->join('detalle_compras', 'detalle_compras.compra_id', '=', 'compras.id')
                    ->join('productos', 'productos.id', '=', 'detalle_compras.producto_id')
                    ->where('productos.nombre', $fila->producto)
                    ->orderByDesc('compras.fecha')
                    ->value('detalle_compras.costo_unitario');

                return [
                    'nombre' => (string) $fila->producto,
                    'cantidad' => (int) $fila->cantidad,
                    'veces' => (int) $fila->veces,
                    'total' => round((float) $fila->total, 2),
                    'ultimo_costo' => round((float) $ultimo, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Evolución del costo unitario por producto (solo recibidas).
     *
     * @return array<int, array{ nombre:string, registros:array<int, array{fecha:string, costo:float}>, minimo:float, maximo:float, primero:float, ultimo:float, variacion_pct:?float }>
     */
    public function evolucionPrecios(int $proveedorId, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        $query = Compra::where('compras.estado', 'recibida')
            ->where('compras.proveedor_id', $proveedorId)
            ->whereDate('compras.fecha', '>=', $desde->toDateString())
            ->whereDate('compras.fecha', '<=', $hasta->toDateString());

        if (! empty($filtros['producto_id'])) {
            $query->where('detalle_compras.producto_id', $filtros['producto_id']);
        }

        $lineas = $query
            ->join('detalle_compras', 'detalle_compras.compra_id', '=', 'compras.id')
            ->join('productos', 'productos.id', '=', 'detalle_compras.producto_id')
            ->orderBy('compras.fecha')
            ->orderBy('compras.id')
            ->get([
                'productos.nombre as producto',
                'compras.fecha as fecha',
                'detalle_compras.costo_unitario as costo',
            ]);

        return $lineas
            ->groupBy('producto')
            ->map(function (Collection $grupo, string $nombre) {
                $registros = $grupo->map(fn ($l) => [
                    'fecha' => CarbonImmutable::parse($l->fecha)->format('d/m/Y'),
                    'costo' => round((float) $l->costo, 2),
                ])->values()->all();

                $costos = array_column($registros, 'costo');
                $primero = $costos[0];
                $ultimo = $costos[count($costos) - 1];

                return [
                    'nombre' => $nombre,
                    'registros' => $registros,
                    'minimo' => round(min($costos), 2),
                    'maximo' => round(max($costos), 2),
                    'primero' => round($primero, 2),
                    'ultimo' => round($ultimo, 2),
                    'variacion_pct' => $primero > 0
                        ? round((($ultimo - $primero) / $primero) * 100, 1)
                        : null,
                ];
            })
            ->sortByDesc(fn ($e) => count($e['registros']))
            ->values()
            ->all();
    }

    public function historial(int $proveedorId, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = [], int $perPage = 10): LengthAwarePaginator
    {
        $filtros['proveedor_id'] = $proveedorId;

        return $this->base($desde, $hasta, $filtros)
            ->with(['detalles.producto:id,nombre', 'user:id,name'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function historialColeccion(int $proveedorId, CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Collection
    {
        $filtros['proveedor_id'] = $proveedorId;

        return $this->base($desde, $hasta, $filtros)
            ->with(['detalles.producto:id,nombre'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }
}
