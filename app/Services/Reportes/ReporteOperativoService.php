<?php

namespace App\Services\Reportes;

use App\Models\CambioAceite;
use App\Models\Lavado;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reporte operativo del carwash/lubricentro (solo lectura).
 *
 * Universo: lavados y cambios de aceite en estado `confirmado`.
 * Rango por fecha efectiva (lavados → `fecha`, cambios → `created_at`),
 * el mismo criterio de `DashboardService` y del resto de reportes.
 *
 * Reglas de cálculo:
 * - Solicitud registrada ≠ servicio completado: solo `confirmado` cuenta.
 *   Los `pendiente` se excluyen y los eliminados (`delete()` duro) no existen.
 * - Participación de trabajador: un trabajador cuenta 1 por cada ticket en
 *   cuyo pivote aparece (`lavado_trabajadores` / `cambio_aceite_trabajadores`).
 *   En co-atención, el mismo ticket suma en cada participante.
 * - Vehículo único: `vehiculo_id` (placa) distinto y no nulo. `Sin placa`
 *   no se cuenta como vehículo único.
 * - Tiempo de atención: `tiempo_atencion_minutos` persistido
 *   (`atencion_inicio_at` → `atencion_fin_at` vía `TiempoAtencionService`).
 *   Solo promedian los tickets con valor no nulo.
 * - Ticket operativo: total (lavados + cambios confirmados) ÷ atenciones.
 *   No incluye ventas de mostrador (ver reporte de ingresos para el
 *   ticket consolidado).
 */
class ReporteOperativoService
{
    /**
     * @return array{
     *   atenciones: int, lavados: int, cambios: int,
     *   vehiculos_unicos: int, atenciones_por_dia: float,
     *   tiempo_promedio: ?float, con_tiempo: int, sin_tiempo: int,
     *   ticket_promedio: float, total: float,
     *   total_clientes: int, clientes_recurrentes: int, pct_recurrentes: float,
     * }
     */
    public function kpis(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $lavados = $this->lavadosEnRango($desde, $hasta);
        $cambios = $this->cambiosEnRango($desde, $hasta);

        $nLav = (clone $lavados)->count();
        $nCam = (clone $cambios)->count();
        $atenciones = $nLav + $nCam;

        $total = round(
            (float) (clone $lavados)->sum('lavados.total')
            + (float) (clone $cambios)->sum('cambio_aceites.total'),
            2
        );

        $placasLav = (clone $lavados)->whereNotNull('lavados.vehiculo_id')->distinct()->pluck('lavados.vehiculo_id');
        $placasCam = (clone $cambios)->whereNotNull('cambio_aceites.vehiculo_id')->distinct()->pluck('cambio_aceites.vehiculo_id');
        $vehiculosUnicos = collect($placasLav)->merge($placasCam)->unique()->count();

        $tiemposLav = (clone $lavados)->whereNotNull('lavados.tiempo_atencion_minutos')->pluck('lavados.tiempo_atencion_minutos');
        $tiemposCam = (clone $cambios)->whereNotNull('cambio_aceites.tiempo_atencion_minutos')->pluck('cambio_aceites.tiempo_atencion_minutos');
        $tiempos = $tiemposLav->merge($tiemposCam);
        $conTiempo = $tiempos->count();
        $tiempoPromedio = $conTiempo > 0 ? round($tiempos->avg(), 1) : null;

        $dias = max(1, count(DateRangeFiltro::diasEntre($desde, $hasta)));

        $recurrencia = $this->recurrencia($desde, $hasta);

        return [
            'atenciones' => $atenciones,
            'lavados' => $nLav,
            'cambios' => $nCam,
            'vehiculos_unicos' => $vehiculosUnicos,
            'atenciones_por_dia' => round($atenciones / $dias, 2),
            'tiempo_promedio' => $tiempoPromedio,
            'con_tiempo' => $conTiempo,
            'sin_tiempo' => $atenciones - $conTiempo,
            'ticket_promedio' => $atenciones > 0 ? round($total / $atenciones, 2) : 0.0,
            'total' => $total,
            'total_clientes' => $recurrencia['total_clientes'],
            'clientes_recurrentes' => $recurrencia['recurrentes'],
            'pct_recurrentes' => $recurrencia['porcentaje'],
        ];
    }

    /**
     * Serie diaria del rango (sin huecos): atenciones y placas únicas por día.
     *
     * @return array{ dias: string[], atenciones: int[], vehiculos_unicos: int[] }
     */
    public function serieDiaria(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $dias = DateRangeFiltro::diasEntre($desde, $hasta);

        $porDia = [];
        foreach ($dias as $dia) {
            $porDia[$dia] = ['atenciones' => 0, 'placas' => []];
        }

        foreach (
            $this->lavadosEnRango($desde, $hasta)
                ->get(['lavados.fecha', 'lavados.vehiculo_id']) as $fila
        ) {
            $dia = $fila->fecha instanceof \DateTimeInterface
                ? $fila->fecha->format('Y-m-d')
                : (string) $fila->fecha;
            if (! isset($porDia[$dia])) {
                continue;
            }
            $porDia[$dia]['atenciones']++;
            if ($fila->vehiculo_id) {
                $porDia[$dia]['placas'][$fila->vehiculo_id] = true;
            }
        }

        foreach (
            $this->cambiosEnRango($desde, $hasta)
                ->get(['cambio_aceites.created_at', 'cambio_aceites.vehiculo_id']) as $fila
        ) {
            $dia = Carbon::parse($fila->created_at)->toDateString();
            if (! isset($porDia[$dia])) {
                continue;
            }
            $porDia[$dia]['atenciones']++;
            if ($fila->vehiculo_id) {
                $porDia[$dia]['placas'][$fila->vehiculo_id] = true;
            }
        }

        return [
            'dias' => $dias,
            'atenciones' => array_map(fn ($d) => $porDia[$d]['atenciones'], $dias),
            'vehiculos_unicos' => array_map(fn ($d) => count($porDia[$d]['placas']), $dias),
        ];
    }

    /**
     * Participaciones consolidadas por trabajador (lavados + cambios).
     *
     * @return array<int, array{ trabajador_id: int, nombre: string, operaciones: int, lavados: int, cambios: int }>
     */
    public function porTrabajador(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $mapa = [];

        $lav = $this->lavadosEnRango($desde, $hasta)
            ->selectRaw('trabajadores.id as trabajador_id, trabajadores.nombre, trabajadores.apellido_paterno, trabajadores.apellido_materno, COUNT(DISTINCT lavados.id) as operaciones')
            ->join('lavado_trabajadores', 'lavado_trabajadores.lavado_id', '=', 'lavados.id')
            ->join('trabajadores', 'trabajadores.id', '=', 'lavado_trabajadores.trabajador_id')
            ->groupBy('trabajadores.id', 'trabajadores.nombre', 'trabajadores.apellido_paterno', 'trabajadores.apellido_materno')
            ->get();

        foreach ($lav as $fila) {
            $mapa[(int) $fila->trabajador_id] = [
                'trabajador_id' => (int) $fila->trabajador_id,
                'nombre' => $this->nombreTrabajador($fila),
                'operaciones' => 0,
                'lavados' => (int) $fila->operaciones,
                'cambios' => 0,
            ];
        }

        $cam = $this->cambiosEnRango($desde, $hasta)
            ->selectRaw('trabajadores.id as trabajador_id, trabajadores.nombre, trabajadores.apellido_paterno, trabajadores.apellido_materno, COUNT(DISTINCT cambio_aceites.id) as operaciones')
            ->join('cambio_aceite_trabajadores', 'cambio_aceite_trabajadores.cambio_aceite_id', '=', 'cambio_aceites.id')
            ->join('trabajadores', 'trabajadores.id', '=', 'cambio_aceite_trabajadores.trabajador_id')
            ->groupBy('trabajadores.id', 'trabajadores.nombre', 'trabajadores.apellido_paterno', 'trabajadores.apellido_materno')
            ->get();

        foreach ($cam as $fila) {
            $id = (int) $fila->trabajador_id;
            if (! isset($mapa[$id])) {
                $mapa[$id] = [
                    'trabajador_id' => $id,
                    'nombre' => $this->nombreTrabajador($fila),
                    'operaciones' => 0,
                    'lavados' => 0,
                    'cambios' => 0,
                ];
            }
            $mapa[$id]['cambios'] = (int) $fila->operaciones;
        }

        foreach ($mapa as $id => $fila) {
            $mapa[$id]['operaciones'] = $fila['lavados'] + $fila['cambios'];
        }

        usort($mapa, fn ($a, $b) => $b['operaciones'] <=> $a['operaciones']);

        return array_values($mapa);
    }

    /**
     * Servicios de lavado más solicitados (conteo de tickets que los incluyen).
     *
     * @return array<int, array{ servicio_id: int, nombre: string, operaciones: int }>
     */
    public function porServicio(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite = 10): array
    {
        return $this->lavadosEnRango($desde, $hasta)
            ->selectRaw('servicios.id as servicio_id, servicios.nombre as servicio, COUNT(DISTINCT lavados.id) as operaciones')
            ->join('detalle_servicios', 'detalle_servicios.lavado_id', '=', 'lavados.id')
            ->join('servicios', 'servicios.id', '=', 'detalle_servicios.servicio_id')
            ->groupBy('servicios.id', 'servicios.nombre')
            ->orderByDesc('operaciones')
            ->limit($limite)
            ->get()
            ->map(fn ($fila) => [
                'servicio_id' => (int) $fila->servicio_id,
                'nombre' => (string) $fila->servicio,
                'operaciones' => (int) $fila->operaciones,
            ])
            ->values()
            ->all();
    }

    /**
     * Tipos de vehículo (catálogo `tipos_vehiculo`) más atendidos en lavados.
     *
     * @return array<int, array{ nombre: string, operaciones: int, total: float }>
     */
    public function porTipoVehiculo(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite = 10): array
    {
        return $this->lavadosEnRango($desde, $hasta)
            ->selectRaw('tipos_vehiculo.nombre as nombre, COUNT(*) as operaciones, SUM(lavados.total) as total')
            ->leftJoin('tipos_vehiculo', 'tipos_vehiculo.id', '=', 'lavados.tipo_vehiculo_id')
            ->groupBy('tipos_vehiculo.nombre')
            ->orderByDesc('operaciones')
            ->limit($limite)
            ->get()
            ->map(fn ($fila) => [
                'nombre' => (string) ($fila->nombre ?: 'Sin tipo'),
                'operaciones' => (int) $fila->operaciones,
                'total' => round((float) $fila->total, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * Placas más atendidas (lavados + cambios).
     *
     * @return array<int, array{ placa: string, atenciones: int, total: float }>
     */
    public function topPlacas(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite = 10): array
    {
        $mapa = [];

        foreach (
            $this->lavadosEnRango($desde, $hasta)
                ->whereNotNull('lavados.vehiculo_id')
                ->selectRaw('lavados.vehiculo_id as placa, COUNT(*) as atenciones, SUM(lavados.total) as total')
                ->groupBy('lavados.vehiculo_id')
                ->get() as $fila
        ) {
            $mapa[$fila->placa] = ['placa' => $fila->placa, 'atenciones' => (int) $fila->atenciones, 'total' => (float) $fila->total];
        }

        foreach (
            $this->cambiosEnRango($desde, $hasta)
                ->whereNotNull('cambio_aceites.vehiculo_id')
                ->selectRaw('cambio_aceites.vehiculo_id as placa, COUNT(*) as atenciones, SUM(cambio_aceites.total) as total')
                ->groupBy('cambio_aceites.vehiculo_id')
                ->get() as $fila
        ) {
            if (! isset($mapa[$fila->placa])) {
                $mapa[$fila->placa] = ['placa' => $fila->placa, 'atenciones' => 0, 'total' => 0.0];
            }
            $mapa[$fila->placa]['atenciones'] += (int) $fila->atenciones;
            $mapa[$fila->placa]['total'] += (float) $fila->total;
        }

        usort($mapa, fn ($a, $b) => $b['atenciones'] <=> $a['atenciones']);

        return array_map(
            fn ($fila) => ['placa' => $fila['placa'], 'atenciones' => $fila['atenciones'], 'total' => round($fila['total'], 2)],
            array_slice(array_values($mapa), 0, $limite)
        );
    }

    /**
     * Distribución por hora de inicio (`atencion_inicio_at`, fallback `created_at`).
     * Hora en `America/Lima` (hora de la app). Incluye hora pico del rango.
     *
     * @return array{ horas: array<int, int>, hora_pico: ?int, atenciones_pico: int }
     */
    public function porHora(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $horas = array_fill(0, 24, 0);

        $inicios = $this->lavadosEnRango($desde, $hasta)
            ->get(['lavados.atencion_inicio_at', 'lavados.created_at'])
            ->map(fn ($f) => $f->atencion_inicio_at ?? $f->created_at)
            ->merge(
                $this->cambiosEnRango($desde, $hasta)
                    ->get(['cambio_aceites.atencion_inicio_at', 'cambio_aceites.created_at'])
                    ->map(fn ($f) => $f->atencion_inicio_at ?? $f->created_at)
            );

        foreach ($inicios as $marca) {
            if (! $marca) {
                continue;
            }
            $hora = Carbon::parse($marca)->hour;
            $horas[$hora]++;
        }

        $pico = null;
        $maximo = 0;
        foreach ($horas as $hora => $total) {
            if ($total > $maximo) {
                $maximo = $total;
                $pico = $hora;
            }
        }

        return ['horas' => $horas, 'hora_pico' => $maximo > 0 ? $pico : null, 'atenciones_pico' => $maximo];
    }

    /**
     * Tiempos promedio de atención por tipo y global (minutos, 1 decimal).
     *
     * @return array{ lavados: ?float, cambios: ?float, global: ?float, con_tiempo: int, sin_tiempo: int }
     */
    public function tiempos(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $tLav = (clone $this->lavadosEnRango($desde, $hasta))
            ->whereNotNull('lavados.tiempo_atencion_minutos')
            ->avg('lavados.tiempo_atencion_minutos');
        $tCam = (clone $this->cambiosEnRango($desde, $hasta))
            ->whereNotNull('cambio_aceites.tiempo_atencion_minutos')
            ->avg('cambio_aceites.tiempo_atencion_minutos');

        $kpis = $this->kpis($desde, $hasta);

        return [
            'lavados' => $tLav !== null ? round((float) $tLav, 1) : null,
            'cambios' => $tCam !== null ? round((float) $tCam, 1) : null,
            'global' => $kpis['tiempo_promedio'],
            'con_tiempo' => $kpis['con_tiempo'],
            'sin_tiempo' => $kpis['sin_tiempo'],
        ];
    }

    /**
     * Recurrencia: clientes con ≥2 atenciones (lavados + cambios) en el rango.
     *
     * @return array{ total_clientes: int, recurrentes: int, porcentaje: float }
     */
    private function recurrencia(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $visitas = [];

        foreach (
            $this->lavadosEnRango($desde, $hasta)
                ->whereNotNull('lavados.cliente_id')
                ->selectRaw('lavados.cliente_id as id, COUNT(*) as visitas')
                ->groupBy('lavados.cliente_id')
                ->get() as $fila
        ) {
            $visitas[(int) $fila->id] = (int) $fila->visitas;
        }

        foreach (
            $this->cambiosEnRango($desde, $hasta)
                ->whereNotNull('cambio_aceites.cliente_id')
                ->selectRaw('cambio_aceites.cliente_id as id, COUNT(*) as visitas')
                ->groupBy('cambio_aceites.cliente_id')
                ->get() as $fila
        ) {
            $visitas[(int) $fila->id] = ($visitas[(int) $fila->id] ?? 0) + (int) $fila->visitas;
        }

        $total = count($visitas);
        $recurrentes = count(array_filter($visitas, fn ($v) => $v >= 2));

        return [
            'total_clientes' => $total,
            'recurrentes' => $recurrentes,
            'porcentaje' => $total > 0 ? round($recurrentes / $total * 100, 1) : 0.0,
        ];
    }

    private function lavadosEnRango(CarbonImmutable $desde, CarbonImmutable $hasta)
    {
        return Lavado::query()->where('lavados.estado', 'confirmado')
            ->whereDate('lavados.fecha', '>=', $desde->toDateString())
            ->whereDate('lavados.fecha', '<=', $hasta->toDateString());
    }

    private function cambiosEnRango(CarbonImmutable $desde, CarbonImmutable $hasta)
    {
        return CambioAceite::query()->where('cambio_aceites.estado', 'confirmado')
            ->whereBetween('cambio_aceites.created_at', [$desde, $hasta]);
    }

    private function nombreTrabajador(object $fila): string
    {
        return trim(implode(' ', array_filter([
            $fila->nombre ?? null,
            $fila->apellido_paterno ?? null,
            $fila->apellido_materno ?? null,
        ])));
    }

    /**
     * Serie + agregados listos para exportar en CSV (detalle por día).
     *
     * @return Collection<int, array{ fecha: string, atenciones: int, vehiculos_unicos: int }>
     */
    public function filasSerie(CarbonImmutable $desde, CarbonImmutable $hasta)
    {
        $serie = $this->serieDiaria($desde, $hasta);

        return collect($serie['dias'])->map(fn ($dia, $i) => [
            'fecha' => $dia,
            'atenciones' => $serie['atenciones'][$i],
            'vehiculos_unicos' => $serie['vehiculos_unicos'][$i],
        ]);
    }
}
