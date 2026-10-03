<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Punto único de cálculo del tiempo de atención de la orden.
 *
 * La duración se persiste únicamente en minutos (entero) y se presenta
 * como minutos/horas mediante etiqueta().
 */
class TiempoAtencionService
{
    /**
     * Minutos completos entre inicio y fin. Nunca negativo.
     */
    public function minutosEntre(Carbon|string $inicio, Carbon|string $fin): int
    {
        $inicio = $inicio instanceof Carbon ? $inicio : Carbon::parse($inicio);
        $fin = $fin instanceof Carbon ? $fin : Carbon::parse($fin);

        if ($fin->lessThan($inicio)) {
            return 0;
        }

        return (int) $inicio->diffInMinutes($fin);
    }

    /**
     * Etiqueta legible usando solo minutos/horas: "45 min", "2 h", "2 h 15 min".
     */
    public function etiqueta(?int $minutos): ?string
    {
        if ($minutos === null) {
            return null;
        }

        $minutos = max(0, $minutos);

        if ($minutos < 60) {
            return "{$minutos} min";
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $resto > 0 ? "{$horas} h {$resto} min" : "{$horas} h";
    }

    /**
     * Datos de cierre de la atención listos para persistir en el ticket.
     *
     * @return array{atencion_fin_at: Carbon, tiempo_atencion_minutos: int}
     */
    public function datosFinalizacion(Carbon|string|null $inicio, Carbon|string|null $fin = null): array
    {
        $inicio = $inicio instanceof Carbon ? $inicio : Carbon::parse($inicio ?? now()->toDateTimeString());
        $fin = $fin instanceof Carbon ? $fin : Carbon::parse($fin ?? now()->toDateTimeString());

        return [
            'atencion_fin_at' => $fin,
            'tiempo_atencion_minutos' => $this->minutosEntre($inicio, $fin),
        ];
    }
}
