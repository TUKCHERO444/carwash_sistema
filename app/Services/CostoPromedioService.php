<?php

namespace App\Services;

use App\Models\Producto;

class CostoPromedioService
{
    /**
     * Obtiene el costo promedio ponderado (CPP) del producto.
     * Fallback a precio_compra si CPP = 0.
     */
    public function obtenerCpp(Producto $producto): float
    {
        $cpp = (float) $producto->costo_promedio_ponderado;

        return $cpp > 0 ? $cpp : (float) $producto->precio_compra;
    }

    /**
     * Actualiza el CPP tras una entrada con costo conocido.
     *
     * Fórmula PMP: nuevo_cpp = ((stock_actual - cantidad_nueva) * cpp_actual + cantidad_nueva * costo_nuevo) / stock_actual
     *
     * @param  Producto  $producto  Producto con stock YA actualizado (incluye la nueva cantidad)
     * @param  int  $cantidad  Cantidad de la entrada entrante
     * @param  float  $costoUnitario  Costo unitario de la entrada entrante (>0)
     */
    public function actualizarPorEntrada(Producto $producto, int $cantidad, float $costoUnitario): void
    {
        if ($costoUnitario <= 0) {
            return; // No actualizar si no hay costo conocido
        }

        $stockActual = $producto->stock; // YA incluye la nueva cantidad
        $cppActual = (float) $producto->costo_promedio_ponderado;

        if ($cppActual <= 0) {
            $nuevoCpp = $costoUnitario;
        } else {
            $nuevoCpp = (($stockActual - $cantidad) * $cppActual + $cantidad * $costoUnitario) / $stockActual;
        }

        $producto->update(['costo_promedio_ponderado' => round($nuevoCpp, 4)]);
    }

    /**
     * Inicializa CPP en productos existentes que tienen precio_compra > 0 pero CPP = 0.
     * Útil para migración inicial.
     */
    public function inicializarCppExistentes(): int
    {
        $actualizados = 0;

        Producto::where('costo_promedio_ponderado', 0)
            ->where('precio_compra', '>', 0)
            ->chunkById(100, function ($productos) use (&$actualizados) {
                foreach ($productos as $producto) {
                    $producto->update(['costo_promedio_ponderado' => round((float) $producto->precio_compra, 4)]);
                    $actualizados++;
                }
            });

        return $actualizados;
    }

    /**
     * Obtiene el costo a usar para valorización (CPP si > 0, sino precio_compra).
     */
    public function obtenerCostoValorizado(Producto $producto): float
    {
        $cpp = (float) $producto->costo_promedio_ponderado;

        return $cpp > 0 ? $cpp : (float) $producto->precio_compra;
    }
}
