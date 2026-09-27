<?php

namespace App\Services;

use App\Models\MovimientoKardex;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class KardexService
{
    /**
     * Fuentes válidas para movimientos de Kardex.
     */
    public const FUENTES = [
        'venta', 'cambio_aceite', 'compra',
        'ajuste_positivo', 'ajuste_negativo', 'merma', 'daño', 'conteo_fisico', 'inventario',
    ];

    /**
     * Registra un movimiento de salida (decremento de stock) en el Kardex.
     * No modifica el stock: solo materializa el movimiento capturando el estado
     * del producto antes y después de la operación realizada por el llamador.
     *
     * @param  Producto  $producto  Producto afectado (con el stock ya mutado por el llamador).
     * @param  int  $cantidad  Cantidad movida (>0).
     * @param  int  $stockAntes  Stock del producto antes de la operación.
     * @param  string  $fuente  'venta' | 'cambio_aceite' | 'ajuste_negativo' | 'merma' | 'daño' | 'conteo_fisico'.
     * @param  string  $origenId  Correlativo (VTA-XXXX), placa, o correlativo ajuste.
     * @param  float  $costoUnitario  Costo unitario para valorización (opcional).
     */
    public function registrarSalida(
        Producto $producto,
        int $cantidad,
        int $stockAntes,
        string $fuente,
        string $origenId,
        float $costoUnitario = 0
    ): void {
        $this->registrar(
            $producto,
            $tipo = 'salida',
            $cantidad,
            $stockAntes,
            $stockAntes - $cantidad,
            $fuente,
            $origenId,
            $costoUnitario
        );
    }

    /**
     * Registra un movimiento de entrada (incremento de stock) en el Kardex.
     *
     * @param  Producto  $producto  Producto afectado (con el stock ya mutado por el llamador).
     * @param  int  $cantidad  Cantidad movida (>0).
     * @param  int  $stockAntes  Stock del producto antes de la operación.
     * @param  string  $fuente  'inventario' | 'venta' (anulación) | 'cambio_aceite' (anulación) | 'compra' | 'ajuste_positivo' | 'conteo_fisico'.
     * @param  string  $origenId  Correlativo INV-XXXX, VTA-XXXX, CAM-XXXX, CMP-XXXX, AJU-XXXX.
     * @param  float  $costoUnitario  Costo unitario para valorización (opcional).
     */
    public function registrarEntrada(
        Producto $producto,
        int $cantidad,
        int $stockAntes,
        string $fuente,
        string $origenId,
        float $costoUnitario = 0
    ): void {
        $this->registrar(
            $producto,
            $tipo = 'entrada',
            $cantidad,
            $stockAntes,
            $stockAntes + $cantidad,
            $fuente,
            $origenId,
            $costoUnitario
        );
    }

    /**
     * Genera el correlativo genérico de inventario (INV-XXXX) usando el mismo
     * patrón que el correlativo de venta. Debe llamarse dentro de la misma
     * transacción que registrará el/los movimientos para evitar colisiones.
     */
    public function siguienteCorrelativoInventario(): string
    {
        $max = MovimientoKardex::where('fuente', 'inventario')
            ->where('origen_id', 'like', 'INV-%')
            ->max('origen_id');

        $next = $max ? ((int) substr($max, 4)) + 1 : 1;

        return 'INV-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Genera el correlativo de compra (CMP-XXXX) dentro de la transacción.
     * Debe llamarse tras bloquear la compra para evitar colisiones.
     */
    public function siguienteCorrelativoCompra(): string
    {
        $max = MovimientoKardex::where('fuente', 'compra')
            ->where('origen_id', 'like', 'CMP-%')
            ->max('origen_id');

        $next = $max ? ((int) substr($max, 4)) + 1 : 1;

        return 'CMP-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Genera el correlativo de ajuste (AJU-XXXX) dentro de la transacción.
     */
    public function siguienteCorrelativoAjuste(): string
    {
        $max = MovimientoKardex::where('fuente', 'like', 'ajuste_%')
            ->where('origen_id', 'like', 'AJU-%')
            ->max('origen_id');

        $next = $max ? ((int) substr($max, 4)) + 1 : 1;

        return 'AJU-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Registra un movimiento de entrada por compra en el Kardex.
     *
     * No modifica el stock: el llamador ya lo mutó. Solo materializa el movimiento
     * capturando el estado del producto antes y después.
     *
     * @param  Producto  $producto  Producto afectado (con el stock ya mutado).
     * @param  int  $cantidad  Cantidad movida (>0).
     * @param  int  $stockAntes  Stock del producto antes de la operación.
     * @param  string  $origenId  Correlativo de la compra (CMP-XXXX).
     * @param  float  $costoUnitario  Costo unitario del detalle de compra.
     */
    public function registrarEntradaCompra(
        Producto $producto,
        int $cantidad,
        int $stockAntes,
        string $origenId,
        float $costoUnitario = 0
    ): void {
        $this->registrar(
            $producto,
            $tipo = 'entrada',
            $cantidad,
            $stockAntes,
            $stockAntes + $cantidad,
            $fuente = 'compra',
            $origenId,
            $costoUnitario
        );
    }

    /**
     * Registra una salida compensatoria por anulación de compra recibida.
     *
     * @param  Producto  $producto  Producto afectado (con el stock ya mutado).
     * @param  int  $cantidad  Cantidad movida (>0).
     * @param  int  $stockAntes  Stock del producto antes de la operación.
     * @param  string  $origenId  Correlativo de la compra (CMP-XXXX).
     * @param  float  $costoUnitario  Costo unitario (CPP actual).
     */
    public function registrarSalidaCompensatoria(
        Producto $producto,
        int $cantidad,
        int $stockAntes,
        string $origenId,
        float $costoUnitario = 0
    ): void {
        $this->registrar(
            $producto,
            $tipo = 'salida',
            $cantidad,
            $stockAntes,
            $stockAntes - $cantidad,
            $fuente = 'ajuste_negativo',
            $origenId,
            $costoUnitario
        );
    }

    /**
     * Crea la fila de Kardex de forma individual por producto.
     */
    private function registrar(
        Producto $producto,
        string $tipo,
        int $cantidad,
        int $stockAntes,
        int $stockDespues,
        string $fuente,
        string $origenId,
        float $costoUnitario = 0
    ): void {
        $costoTotal = round($cantidad * $costoUnitario, 2);

        DB::table('movimientos_kardex')->insert([
            'producto_id' => $producto->id,
            'tipo' => $tipo,
            'fuente' => $fuente,
            'origen_id' => $origenId,
            'cantidad' => $cantidad,
            'stock_antes' => $stockAntes,
            'stock_despues' => $stockDespues,
            'costo_unitario' => round($costoUnitario, 2),
            'costo_total' => $costoTotal,
            'usuario_id' => auth()->id(),
            'fecha_movimiento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
