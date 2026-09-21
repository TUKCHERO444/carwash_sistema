<?php

namespace App\Services\Reportes;

use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Lavado;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de clientes y automotores: gasto consolidado (lavados confirmados
 * + cambios confirmados), visitas y frecuencia mensual.
 */
class ReporteClientesService
{
    /**
     * @return array<int, array{ gasto:float, visitas:int }> keyed por cliente_id.
     */
    private function operacionesPorCliente(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $mapa = [];

        foreach (Lavado::where('estado', 'confirmado')
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->whereNotNull('cliente_id')
            ->selectRaw('cliente_id as id, SUM(total) as total, COUNT(*) as visitas')
            ->groupBy('cliente_id')
            ->get() as $fila) {
            $mapa[(int) $fila->id] = ['gasto' => (float) $fila->total, 'visitas' => (int) $fila->visitas];
        }

        foreach (CambioAceite::where('estado', 'confirmado')
            ->whereBetween('created_at', [$desde, $hasta])
            ->whereNotNull('cliente_id')
            ->selectRaw('cliente_id as id, SUM(total) as total, COUNT(*) as visitas')
            ->groupBy('cliente_id')
            ->get() as $fila) {
            $actual = $mapa[(int) $fila->id]['gasto'] ?? 0.0;
            $mapa[(int) $fila->id] = [
                'gasto' => $actual + (float) $fila->total,
                'visitas' => ($mapa[(int) $fila->id]['visitas'] ?? 0) + (int) $fila->visitas,
            ];
        }

        return $mapa;
    }

    /**
     * Automotores distintos atendidos por cliente en el rango (lavados
     * confirmados + cambios confirmados), con desglose por fuente.
     *
     * @return array<int, array{ lavados:int, cambio_aceite:int, atendidos:int }> keyed por cliente_id.
     */
    private function automotoresPorCliente(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $mapa = [];

        foreach (Lavado::where('estado', 'confirmado')
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->whereNotNull('cliente_id')
            ->whereNotNull('automotor_id')
            ->selectRaw('cliente_id as id, COUNT(DISTINCT automotor_id) as automotores')
            ->groupBy('cliente_id')
            ->get() as $fila) {
            $mapa[(int) $fila->id]['lavados'] = (int) $fila->automotores;
        }

        foreach (CambioAceite::where('estado', 'confirmado')
            ->whereBetween('created_at', [$desde, $hasta])
            ->whereNotNull('cliente_id')
            ->whereNotNull('automotor_id')
            ->selectRaw('cliente_id as id, COUNT(DISTINCT automotor_id) as automotores')
            ->groupBy('cliente_id')
            ->get() as $fila) {
            $mapa[(int) $fila->id]['cambio_aceite'] = (int) $fila->automotores;
        }

        foreach (DB::table(function ($query) use ($desde, $hasta) {
            $query->selectRaw('cliente_id as id, automotor_id as placa')
                ->from('lavados')
                ->where('estado', 'confirmado')
                ->whereDate('fecha', '>=', $desde->toDateString())
                ->whereDate('fecha', '<=', $hasta->toDateString())
                ->whereNotNull('cliente_id')
                ->whereNotNull('automotor_id');
            $query->union(
                DB::table('cambio_aceites')
                    ->selectRaw('cliente_id as id, automotor_id as placa')
                    ->where('estado', 'confirmado')
                    ->whereBetween('created_at', [$desde, $hasta])
                    ->whereNotNull('cliente_id')
                    ->whereNotNull('automotor_id')
            );
        }, 'operaciones')
            ->selectRaw('id, COUNT(*) as automotores')
            ->groupBy('id')
            ->get() as $fila) {
            $mapa[(int) $fila->id]['atendidos'] = (int) $fila->automotores;
        }

        return $mapa;
    }

    /**
     * @return Collection<int, array{ cliente_id:int, nombre:string, gasto:float, visitas:int, automotores:int, lavados:int, cambio_aceite:int, visitas_por_mes:float }>
     */
    public function topClientes(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite = 20): Collection
    {
        $porCliente = $this->operacionesPorCliente($desde, $hasta);

        if ($porCliente === []) {
            return collect();
        }

        $automotores = $this->automotoresPorCliente($desde, $hasta);
        $clientes = Cliente::whereIn('id', array_keys($porCliente))->get();

        $meses = $this->mesesEnRango($desde, $hasta);

        return $clientes
            ->map(function (Cliente $cliente) use ($porCliente, $automotores, $meses) {
                $datos = $porCliente[$cliente->id];
                $datosAuto = $automotores[$cliente->id] ?? ['lavados' => 0, 'cambio_aceite' => 0, 'atendidos' => 0];
                $visitas = $datos['visitas'];

                return [
                    'cliente_id' => (int) $cliente->id,
                    'nombre' => $cliente->nombre_completo ?: ('Cliente #'.$cliente->id),
                    'gasto' => round($datos['gasto'], 2),
                    'visitas' => $visitas,
                    'automotores' => $datosAuto['atendidos'] ?? 0,
                    'lavados' => $datosAuto['lavados'] ?? 0,
                    'cambio_aceite' => $datosAuto['cambio_aceite'] ?? 0,
                    'visitas_por_mes' => round($visitas / $meses, 2),
                ];
            })
            ->sortByDesc('gasto')
            ->take($limite)
            ->values();
    }

    /**
     * @return Collection<int, array{ placa:string, marca:string, modelo:string, cliente:string, visitas:int, ingresos:float }>
     */
    public function topAutomotores(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite = 20): Collection
    {
        $visitados = [];

        foreach (Lavado::where('estado', 'confirmado')
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->whereNotNull('automotor_id')
            ->selectRaw('automotor_id as placa, SUM(total) as total, COUNT(*) as visitas')
            ->groupBy('automotor_id')
            ->get() as $fila) {
            $visitados[(string) $fila->placa] = ['ingresos' => (float) $fila->total, 'visitas' => (int) $fila->visitas];
        }

        foreach (CambioAceite::where('estado', 'confirmado')
            ->whereBetween('created_at', [$desde, $hasta])
            ->whereNotNull('automotor_id')
            ->selectRaw('automotor_id as placa, SUM(total) as total, COUNT(*) as visitas')
            ->groupBy('automotor_id')
            ->get() as $fila) {
            $actual = $visitados[(string) $fila->placa]['ingresos'] ?? 0.0;
            $visitados[(string) $fila->placa] = [
                'ingresos' => $actual + (float) $fila->total,
                'visitas' => ($visitados[(string) $fila->placa]['visitas'] ?? 0) + (int) $fila->visitas,
            ];
        }

        if ($visitados === []) {
            return collect();
        }

        return DB::table('automotores')
            ->whereIn('placa', array_keys($visitados))
            ->leftJoin('clientes', 'clientes.id', '=', 'automotores.cliente_id')
            ->get(['automotores.placa', 'automotores.marca', 'automotores.modelo', 'clientes.nombre', 'clientes.apellido_paterno', 'clientes.apellido_materno'])
            ->map(function ($fila) use ($visitados) {
                $datos = $visitados[$fila->placa];

                return [
                    'placa' => (string) $fila->placa,
                    'marca' => (string) ($fila->marca ?? ''),
                    'modelo' => (string) ($fila->modelo ?? ''),
                    'cliente' => trim(implode(' ', array_filter([
                        $fila->nombre,
                        $fila->apellido_paterno,
                        $fila->apellido_materno,
                    ]))),
                    'visitas' => $datos['visitas'],
                    'ingresos' => round($datos['ingresos'], 2),
                ];
            })
            ->sortByDesc('ingresos')
            ->take($limite)
            ->values();
    }

    /**
     * Detalle paginado de clientes con gasto/visitas/automotores en el rango.
     */
    public function detalle(CarbonImmutable $desde, CarbonImmutable $hasta, int $perPage = 10): LengthAwarePaginator
    {
        $porCliente = $this->operacionesPorCliente($desde, $hasta);
        $meses = $this->mesesEnRango($desde, $hasta);

        $pagina = Cliente::withCount('automotores')
            ->orderBy('created_at')
            ->paginate($perPage);

        $items = $pagina->getCollection()->map(function (Cliente $cliente) use ($porCliente, $meses) {
            $datos = $porCliente[$cliente->id] ?? ['gasto' => 0.0, 'visitas' => 0];
            $visitas = $datos['visitas'];

            return [
                'cliente_id' => (int) $cliente->id,
                'nombre' => $cliente->nombre_completo ?: ('Cliente #'.$cliente->id),
                'gasto' => round($datos['gasto'], 2),
                'visitas' => $visitas,
                'automotores' => (int) $cliente->automotores_count,
                'visitas_por_mes' => round($visitas / $meses, 2),
            ];
        });

        return $pagina->setCollection($items);
    }

    /**
     * Meses calendario (mínimo 1) cubiertos por el rango.
     */
    private function mesesEnRango(CarbonImmutable $desde, CarbonImmutable $hasta): int
    {
        $meses = ($hasta->year - $desde->year) * 12 + ($hasta->month - $desde->month) + 1;

        return max(1, $meses);
    }
}
