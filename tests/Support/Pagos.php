<?php

namespace Tests\Support;

use App\Models\MetodoPago;
use Illuminate\Database\Eloquent\Model;

/**
 * Helper para adjuntar líneas de pago en tests.
 */
class Pagos
{
    public static function linea(Model $pagable, string $codigo, float $monto): void
    {
        $pagable->pagos()->create([
            'metodo_pago_id' => MetodoPago::where('codigo', $codigo)->firstOrFail()->id,
            'monto' => round($monto, 2),
        ]);
    }

    /**
     * @param  array<string, float>  $montos  codigo => monto
     */
    public static function mixto(Model $pagable, array $montos): void
    {
        foreach ($montos as $codigo => $monto) {
            if ((float) $monto > 0) {
                static::linea($pagable, $codigo, (float) $monto);
            }
        }
    }
}
