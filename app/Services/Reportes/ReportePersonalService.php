<?php

namespace App\Services\Reportes;

use App\Models\Asistencia;
use App\Models\Trabajador;
use Illuminate\Support\Collection;

/**
 * Reporte de personal: asistencias, porcentaje y resumen de pago por mes.
 * El % de asistencia se calcula sobre los días con marca (no sobre el
 * universo de trabajadores activos).
 */
class ReportePersonalService
{
    /**
     * @return array{
     *   mes: string, desde: string, hasta: string,
     *   total_dias_con_marca: int, total_pago_general: float,
     *   trabajadores: array<int, array{
     *      trabajador_id:int, nombre:string, pago_diario:?float, asistencias:int,
     *      porcentaje_asistencia:float, hora_promedio:?string, total_pago:float,
     *      sin_jornal:bool, activo:bool,
     *   }>,
     * }
     */
    public function resumen(string $mes, ?int $trabajadorId = null): array
    {
        [$desde, $hasta, $etiqueta] = DateRangeFiltro::mes($mes);

        $asistencias = Asistencia::whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString());

        if ($trabajadorId) {
            $asistencias->where('trabajador_id', $trabajadorId);
        }

        $totalDiasConMarca = (clone $asistencias)->distinct('fecha')->count('fecha');

        $registros = (clone $asistencias)
            ->orderBy('trabajador_id')
            ->get(['trabajador_id', 'hora_entrada']);

        $agrupadas = $registros->groupBy('trabajador_id');

        $trabajadores = Trabajador::whereIn('id', $agrupadas->keys())
            ->get()
            ->keyBy('id');

        $filas = [];
        $totalPagoGeneral = 0.0;

        foreach ($agrupadas as $trabajadorId => $marcas) {
            $trabajador = $trabajadores[(int) $trabajadorId];
            $asistenciasTrabajador = $marcas->count();
            $pagoDiario = $trabajador->pago_diario !== null
                ? (float) $trabajador->pago_diario
                : null;
            $totalPago = $pagoDiario !== null
                ? round($asistenciasTrabajador * $pagoDiario, 2)
                : 0.0;
            $porcentaje = $totalDiasConMarca > 0
                ? round(($asistenciasTrabajador / $totalDiasConMarca) * 100, 2)
                : 0.0;

            $filas[] = [
                'trabajador_id' => (int) $trabajador->id,
                'nombre' => $trabajador->nombre_completo ?: ('Trabajador #'.$trabajador->id),
                'pago_diario' => $pagoDiario,
                'asistencias' => $asistenciasTrabajador,
                'porcentaje_asistencia' => $porcentaje,
                'hora_promedio' => $this->horaPromedio($marcas->pluck('hora_entrada')),
                'total_pago' => $totalPago,
                'sin_jornal' => $pagoDiario === null,
                'activo' => (bool) $trabajador->estado,
            ];

            $totalPagoGeneral += $totalPago;
        }

        usort($filas, fn ($a, $b) => $a['activo'] <=> $b['activo'] ?: $b['asistencias'] <=> $a['asistencias']);

        return [
            'mes' => $etiqueta,
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'total_dias_con_marca' => $totalDiasConMarca,
            'total_pago_general' => round($totalPagoGeneral, 2),
            'trabajadores' => $filas,
        ];
    }

    private function horaPromedio(Collection $horas): ?string
    {
        if ($horas->isEmpty()) {
            return null;
        }

        $segundos = $horas
            ->map(fn ($hora) => $this->segundosDesdeMedianoche((string) $hora))
            ->avg();

        if ($segundos === null) {
            return null;
        }

        $segundos = (int) round($segundos);

        return sprintf('%02d:%02d', intdiv($segundos, 3600), intdiv($segundos % 3600, 60));
    }

    private function segundosDesdeMedianoche(string $hora): int
    {
        $partes = array_map('intval', explode(':', $hora));

        return ($partes[0] ?? 0) * 3600 + ($partes[1] ?? 0) * 60 + ($partes[2] ?? 0);
    }

    /**
     * Trabajadores con su pago diario, para el select del filtro.
     *
     * @return array<int, array{ id:int, nombre:string, pago_diario:?float }>
     */
    public function trabajadoresDisponibles(): array
    {
        $mes = now()->format('Y-m');

        return Trabajador::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno', 'pago_diario', 'estado'])
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'nombre' => $t->nombre_completo,
            ])
            ->values()
            ->all();
    }
}
