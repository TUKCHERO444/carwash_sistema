<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Trabajador;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AsistenciaService
{
    /**
     * Trabajadores activos ordenados por nombre (universo de los conteos).
     */
    public function trabajadoresActivos(): Collection
    {
        return Trabajador::where('estado', true)->orderBy('nombre')->get();
    }

    /**
     * Resumen de asistencia de una fecha: asistentes (con hora de entrada),
     * no asistentes (activos sin marca) y conteos correspondientes.
     *
     * @return array{
     *     fecha: string,
     *     total_activos: int,
     *     asistieron: int,
     *     no_asistieron: int,
     *     asistentes: array<int, array{trabajador_id:int, nombre_completo:string, hora_entrada:string(H:i), foto_url:?string}>,
     *     no_asistentes: array<int, array{trabajador_id:int, nombre_completo:string, foto_url:?string}>,
     * }
     */
    public function resumenDeFecha(string $fecha): array
    {
        $activos = $this->trabajadoresActivos();
        $marcas = Asistencia::where('fecha', $fecha)->get()->keyBy('trabajador_id');

        $asistentes = [];
        $noAsistentes = [];

        foreach ($activos as $trabajador) {
            $marca = $marcas->get($trabajador->id);

            if ($marca) {
                $asistentes[] = [
                    'trabajador_id' => $trabajador->id,
                    'nombre_completo' => $trabajador->nombre_completo,
                    'hora_entrada' => Carbon::parse($marca->hora_entrada)->format('H:i'),
                    'foto_url' => $trabajador->foto_url,
                ];
            } else {
                $noAsistentes[] = [
                    'trabajador_id' => $trabajador->id,
                    'nombre_completo' => $trabajador->nombre_completo,
                    'foto_url' => $trabajador->foto_url,
                ];
            }
        }

        usort($asistentes, fn ($a, $b) => [$a['hora_entrada'], $a['nombre_completo']] <=> [$b['hora_entrada'], $b['nombre_completo']]);

        return [
            'fecha' => $fecha,
            'total_activos' => $activos->count(),
            'asistieron' => count($asistentes),
            'no_asistieron' => $activos->count() - count($asistentes),
            'asistentes' => $asistentes,
            'no_asistentes' => $noAsistentes,
        ];
    }

    /**
     * Estado de asistencia de un mes: por cada día con marcas, el conteo de
     * asistentes junto al total de trabajadores activos. Con ambos valores el
     * frontend decide el indicador de color (completo/parcial/nulo).
     *
     * @return array{
     *     total_activos: int,
     *     dias: array<string, array{asistentes:int, total_activos:int}>,
     * }
     */
    public function estadoPorMes(string $mes): array
    {
        $inicio = Carbon::parse($mes.'-01');
        $fin = $inicio->copy()->endOfMonth();
        $totalActivos = $this->trabajadoresActivos()->count();

        $porDia = Asistencia::whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->selectRaw('fecha, count(*) as total')
            ->groupBy('fecha')
            ->pluck('total', 'fecha');

        $dias = [];
        foreach ($porDia as $fecha => $total) {
            $dias[$fecha] = [
                'asistentes' => (int) $total,
                'total_activos' => $totalActivos,
            ];
        }

        return [
            'total_activos' => $totalActivos,
            'dias' => $dias,
        ];
    }

    /**
     * Sincronización total (full-sync) de las marcas de una fecha:
     * inserta/actualiza las marcas enviadas y elimina las omisiones de
     * trabajadores activos. Devuelve el resumen de la fecha tras el sync.
     */
    public function sincronizarMarca(string $fecha, array $marcas): array
    {
        $activosIds = $this->trabajadoresActivos()->pluck('id')->all();

        $marcasFiltradas = array_filter(
            $marcas,
            fn ($hora, $trabajadorId) => in_array((int) $trabajadorId, $activosIds, true),
            ARRAY_FILTER_USE_BOTH
        );

        return DB::transaction(function () use ($fecha, $marcasFiltradas) {
            $existentes = Asistencia::where('fecha', $fecha)->get();

            foreach ($existentes as $marca) {
                if (! array_key_exists((string) $marca->trabajador_id, $marcasFiltradas)) {
                    $marca->delete();
                }
            }

            foreach ($marcasFiltradas as $trabajadorId => $hora) {
                Asistencia::updateOrCreate(
                    ['fecha' => $fecha, 'trabajador_id' => (int) $trabajadorId],
                    ['hora_entrada' => $hora]
                );
            }

            return $this->resumenDeFecha($fecha);
        });
    }
}
