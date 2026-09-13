<?php

namespace App\Services\Reportes;

use App\Models\MovimientoKardex;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reporte de kardex: movimientos agregados por producto (entradas/salidas)
 * y detalle paginado. Rango por fecha_movimiento.
 */
class ReporteKardexService
{
    /**
     * Query base: movimientos en el rango + filtros (producto, tipo).
     */
    private function base(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Builder
    {
        $query = MovimientoKardex::query()
            ->whereBetween('fecha_movimiento', [$desde, $hasta]);

        if (! empty($filtros['producto_id'])) {
            $query->where('producto_id', $filtros['producto_id']);
        }

        if (! empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        return $query;
    }

    /**
     * @return Collection<int, array{ producto_id:int, producto:string, entradas:int, salidas:int, saldo_neto:int, stock_actual:int }>
     */
    public function agregado(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Collection
    {
        $movimientos = $this->base($desde, $hasta, $filtros)
            ->with('producto:id,nombre,stock')
            ->get();

        $porProducto = [];
        foreach ($movimientos as $movimiento) {
            $id = (int) $movimiento->producto_id;
            if (! isset($porProducto[$id])) {
                $porProducto[$id] = [
                    'producto_id' => $id,
                    'producto' => (string) ($movimiento->producto?->nombre ?? ('Producto #'.$id)),
                    'entradas' => 0,
                    'salidas' => 0,
                    'stock_actual' => (int) ($movimiento->producto?->stock ?? 0),
                ];
            }

            if ($movimiento->tipo === 'entrada') {
                $porProducto[$id]['entradas'] += (int) $movimiento->cantidad;
            } else {
                $porProducto[$id]['salidas'] += (int) $movimiento->cantidad;
            }
        }

        foreach ($porProducto as &$fila) {
            $fila['saldo_neto'] = $fila['entradas'] - $fila['salidas'];
        }
        unset($fila);

        usort($porProducto, fn ($a, $b) => $b['saldo_neto'] <=> $a['saldo_neto']);

        return collect($porProducto)->values();
    }

    public function detalle(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->base($desde, $hasta, $filtros)
            ->with(['producto:id,nombre', 'usuario:id,name'])
            ->orderByDesc('fecha_movimiento')
            ->paginate($perPage);
    }

    public function detalleColeccion(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Collection
    {
        return $this->base($desde, $hasta, $filtros)
            ->with(['producto:id,nombre', 'usuario:id,name'])
            ->orderByDesc('fecha_movimiento')
            ->get();
    }
}
