<?php

namespace App\Services;

use App\Models\MovimientoKardex;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class KardexService
{
    /**
     * Registra un movimiento de salida (decremento de stock) en el Kardex.
     * No modifica el stock: solo materializa el movimiento capturando el estado
     * del producto antes y después de la operación realizada por el llamador.
     *
     * @param  Producto  $producto  Producto afectado (con el stock ya mutado por el llamador).
     * @param  int  $cantidad  Cantidad movida (>0).
     * @param  int  $stockAntes  Stock del producto antes de la operación.
     * @param  string  $fuente  'venta' | 'cambio_aceite'.
     * @param  string  $origenId  Correlativo (VTA-XXXX) o placa (cambio de aceite).
     */
    public function registrarSalida(
        Producto $producto,
        int $cantidad,
        int $stockAntes,
        string $fuente,
        string $origenId
    ): void {
        $this->registrar(
            $producto,
            $tipo = 'salida',
            $cantidad,
            $stockAntes,
            $stockAntes - $cantidad,
            $fuente,
            $origenId
        );
    }

    /**
     * Registra un movimiento de entrada (incremento de stock) en el Kardex.
     *
     * @param  Producto  $producto  Producto afectado (con el stock ya mutado por el llamador).
     * @param  int  $cantidad  Cantidad movida (>0).
     * @param  int  $stockAntes  Stock del producto antes de la operación.
     * @param  string  $fuente  'inventario' | 'venta' (anulación) | 'cambio_aceite' (anulación).
     * @param  string  $origenId  Correlativo INV-XXXX, VTA-XXXX o placa.
     */
    public function registrarEntrada(
        Producto $producto,
        int $cantidad,
        int $stockAntes,
        string $fuente,
        string $origenId
    ): void {
        $this->registrar(
            $producto,
            $tipo = 'entrada',
            $cantidad,
            $stockAntes,
            $stockAntes + $cantidad,
            $fuente,
            $origenId
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
     * Crea la fila de Kardex de forma individual por producto.
     */
    private function registrar(
        Producto $producto,
        string $tipo,
        int $cantidad,
        int $stockAntes,
        int $stockDespues,
        string $fuente,
        string $origenId
    ): void {
        DB::table('movimientos_kardex')->insert([
            'producto_id' => $producto->id,
            'tipo' => $tipo,
            'fuente' => $fuente,
            'origen_id' => $origenId,
            'cantidad' => $cantidad,
            'stock_antes' => $stockAntes,
            'stock_despues' => $stockDespues,
            'usuario_id' => auth()->id(),
            'fecha_movimiento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
