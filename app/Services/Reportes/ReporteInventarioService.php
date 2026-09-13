<?php

namespace App\Services\Reportes;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de inventario: top de productos por cantidad/ingreso, stock
 * actual valorizado y resúmenes por categoría y marca.
 */
class ReporteInventarioService
{
    /**
     * Productos filtrados por categoría/marca con su ingreso en el rango.
     *
     * @return Collection<int, array{
     *   id:int, nombre:string, categoria_id:?int, marca_id:?int,
     *   cantidad:int, ingreso:float, stock:int, valorizado:float,
     * }>
     */
    private function conIngresos(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Collection
    {
        $porProducto = $this->ingresosPorProducto($desde, $hasta);

        $productos = Producto::query()
            ->with(['categoria:id,nombre', 'marca:id,nombre'])
            ->when(! empty($filtros['categoria_id']), fn ($q) => $q->where('categoria_id', $filtros['categoria_id']))
            ->when(! empty($filtros['marca_id']), fn ($q) => $q->where('marca_id', $filtros['marca_id']))
            ->get();

        return $productos->map(function (Producto $producto) use ($porProducto) {
            $ingreso = $porProducto[$producto->id]['ingreso'] ?? 0.0;

            return [
                'id' => (int) $producto->id,
                'nombre' => (string) $producto->nombre,
                'categoria_id' => $producto->categoria_id,
                'categoria' => $producto->categoria?->nombre,
                'marca_id' => $producto->marca_id,
                'marca' => $producto->marca?->nombre,
                'cantidad' => (int) ($porProducto[$producto->id]['cantidad'] ?? 0),
                'ingreso' => round((float) $ingreso, 2),
                'stock' => (int) $producto->stock,
                'valorizado' => round((float) $producto->stock * (float) $producto->precio_compra, 2),
            ];
        })->values();
    }

    /**
     * @return array<int, array{ cantidad:int, ingreso:float }> keyed por producto_id.
     */
    private function ingresosPorProducto(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $mapa = [];

        foreach (DB::table('detalle_ventas')
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->whereBetween('ventas.created_at', [$desde, $hasta])
            ->selectRaw('detalle_ventas.producto_id as id, SUM(detalle_ventas.cantidad) as cantidad, SUM(detalle_ventas.subtotal) as ingreso')
            ->groupBy('detalle_ventas.producto_id')
            ->get() as $fila) {
            $mapa[(int) $fila->id] = ['cantidad' => (int) $fila->cantidad, 'ingreso' => (float) $fila->ingreso];
        }

        foreach (DB::table('cambio_productos')
            ->join('cambio_aceites', 'cambio_aceites.id', '=', 'cambio_productos.cambio_aceite_id')
            ->where('cambio_aceites.estado', 'confirmado')
            ->whereBetween('cambio_aceites.created_at', [$desde, $hasta])
            ->selectRaw('cambio_productos.producto_id as id, SUM(cambio_productos.cantidad) as cantidad, SUM(cambio_productos.total) as ingreso')
            ->groupBy('cambio_productos.producto_id')
            ->get() as $fila) {
            $actual = $mapa[(int) $fila->id]['cantidad'] ?? 0;
            $mapa[(int) $fila->id] = [
                'cantidad' => $actual + (int) $fila->cantidad,
                'ingreso' => ($mapa[(int) $fila->id]['ingreso'] ?? 0.0) + (float) $fila->ingreso,
            ];
        }

        return $mapa;
    }

    /**
     * @return Collection<int, array{ id:int, nombre:string, categoria:string, marca:string, cantidad:int, ingreso:float }>
     */
    public function topPorCantidad(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = [], int $limite = 20): Collection
    {
        return $this->conIngresos($desde, $hasta, $filtros)
            ->sortByDesc('cantidad')
            ->take($limite)
            ->map(fn ($p) => $this->filaTop($p))
            ->values();
    }

    /**
     * @return Collection<int, array{ id:int, nombre:string, categoria:string, marca:string, cantidad:int, ingreso:float }>
     */
    public function topPorIngreso(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = [], int $limite = 20): Collection
    {
        return $this->conIngresos($desde, $hasta, $filtros)
            ->sortByDesc('ingreso')
            ->take($limite)
            ->map(fn ($p) => $this->filaTop($p))
            ->values();
    }

    /**
     * Stock actual valorizado (stock × precio_compra), con categoría/marca.
     *
     * @return Collection<int, Producto>
     */
    public function stockActual(array $filtros = []): Collection
    {
        return Producto::query()
            ->with(['categoria:id,nombre', 'marca:id,nombre'])
            ->when(! empty($filtros['categoria_id']), fn ($q) => $q->where('categoria_id', $filtros['categoria_id']))
            ->when(! empty($filtros['marca_id']), fn ($q) => $q->where('marca_id', $filtros['marca_id']))
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @return array<int, array{ id:int, nombre:string, productos:int, stock_total:int, ingresos:float }>
     */
    public function resumenCategorias(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        return $this->resumenPorDimension($this->conIngresos($desde, $hasta, $filtros), 'categoria');
    }

    /**
     * @return array<int, array{ id:int, nombre:string, productos:int, stock_total:int, ingresos:float }>
     */
    public function resumenMarcas(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array
    {
        return $this->resumenPorDimension($this->conIngresos($desde, $hasta, $filtros), 'marca');
    }

    /**
     * @param  list<string>  $ids
     */
    private function categoriasEn(array $ids): Collection
    {
        return Categoria::whereIn('id', $ids)->get(['id', 'nombre']);
    }

    /**
     * @param  list<string>  $ids
     */
    private function marcasEn(array $ids): Collection
    {
        return Marca::whereIn('id', $ids)->get(['id', 'nombre']);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $productos
     * @return array<int, array{ id:int, nombre:string, productos:int, stock_total:int, ingresos:float }>
     */
    private function resumenPorDimension(Collection $productos, string $clave): array
    {
        $agrupados = $productos->groupBy($clave.'_id');

        $ids = $agrupados->keys()->filter(fn ($id) => $id !== null && $id !== '')->values()->all();
        $nombres = $clave === 'categoria'
            ? $this->categoriasEn($ids)->keyBy('id')
            : $this->marcasEn($ids)->keyBy('id');

        $resumen = [];
        foreach ($agrupados as $id => $grupo) {
            $intId = (int) $id;
            $fila = $grupo->first();

            $resumen[] = [
                'id' => $intId,
                'nombre' => (string) ($nombres[$intId]->nombre ?? ($fila[$clave] ?? 'Sin '.$clave)),
                'productos' => $grupo->count(),
                'stock_total' => $grupo->sum('stock'),
                'ingresos' => round($grupo->sum('ingreso'), 2),
            ];
        }

        usort($resumen, fn ($a, $b) => $b['ingresos'] <=> $a['ingresos']);

        return $resumen;
    }

    /**
     * @param  array<string, mixed>  $producto
     * @return array{ id:int, nombre:string, categoria:string, marca:string, cantidad:int, ingreso:float }
     */
    private function filaTop(array $producto): array
    {
        return [
            'id' => (int) $producto['id'],
            'nombre' => (string) $producto['nombre'],
            'categoria' => (string) ($producto['categoria'] ?? ''),
            'marca' => (string) ($producto['marca'] ?? ''),
            'cantidad' => (int) $producto['cantidad'],
            'ingreso' => round((float) $producto['ingreso'], 2),
        ];
    }
}
