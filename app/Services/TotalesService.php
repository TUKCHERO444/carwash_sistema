<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * Coherencia aritmética entre líneas de detalle y totales de cabecera.
 *
 * Recicla el patrón de CompraController::prepararLineas(): los importes
 * que el formulario envía por comodidad nunca se confían; se recalculan
 * en el servidor con round(..., 2).
 *
 * - Nivel línea: total = round(cantidad × precio). Sin excepciones, pues
 *   las líneas no admiten descuentos (el descuento vive en la cabecera).
 * - Nivel cabecera: el total sí puede variar por descuento manual,
 *   descuento porcentual o facturación (+18 %, tope del formulario).
 *   Se acepta 0 < total ≤ round(suma × 1.18, 2) + 0.01; fuera de ese
 *   rango se rechaza porque contradice el cálculo.
 */
class TotalesService
{
    /**
     * Factor máximo total/suma admitido (espejo del tope de facturación del formulario).
     */
    public const FACTOR_MAXIMO = 1.18;

    /**
     * Tolerancia de redondeo en soles.
     */
    public const TOLERANCIA = 0.01;

    /**
     * Recalcula el total de cada línea como round(cantidad × precio).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function normalizarLineas(array $items, string $precioKey = 'precio', string $totalKey = 'total'): array
    {
        return collect($items)->map(function (array $item) use ($precioKey, $totalKey) {
            $cantidad = (int) ($item['cantidad'] ?? 0);
            $precio = round((float) ($item[$precioKey] ?? 0), 2);
            $item[$precioKey] = $precio;
            $item[$totalKey] = round($cantidad * $precio, 2);

            return $item;
        })->values()->all();
    }

    /**
     * Suma los totales de líneas ya normalizadas.
     */
    public function suma(array $items, string $totalKey = 'total'): float
    {
        return round(collect($items)->sum($totalKey), 2);
    }

    /**
     * Valida que el total de cabecera no contradiga la suma de líneas.
     *
     * @throws ValidationException
     */
    public function validarCabecera(float $total, float $suma, string $campo = 'total'): void
    {
        $total = round($total, 2);
        $tope = round($suma * self::FACTOR_MAXIMO, 2) + self::TOLERANCIA;

        if ($total <= 0 || $total > $tope) {
            throw ValidationException::withMessages([
                $campo => 'El total no coincide con el detalle (máximo permitido con facturación: S/ '.number_format($tope, 2).').',
            ]);
        }
    }

    /**
     * Valida que un importe base iguale exactamente la suma de líneas
     * (subtotales y precios pre-descuento).
     *
     * @throws ValidationException
     */
    public function validarIgualdad(float $valor, float $suma, string $campo): void
    {
        if (abs(round($valor, 2) - round($suma, 2)) > self::TOLERANCIA) {
            throw ValidationException::withMessages([
                $campo => 'El valor no coincide con la suma del detalle.',
            ]);
        }
    }
}
