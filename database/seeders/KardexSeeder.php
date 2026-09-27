<?php

namespace Database\Seeders;

use App\Models\CambioAceite;
use App\Models\Compra;
use App\Models\DetalleVenta;
use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Database\Seeder;

class KardexSeeder extends Seeder
{
    /**
     * Reconstruye el Kardex a partir de operaciones reales:
     * 1. Entradas por compras recibidas (fuente='compra', origen=CMP-XXXX)
     * 2. Salidas por ventas (fuente='venta', origen=VTA-XXXX)
     * 3. Salidas por cambios de aceite confirmados (fuente='cambio_aceite', origen=placa)
     *
     * Ya NO genera entradas ficticias INV-XXXX. El stock inicial nace en 0
     * y las compras recibidas lo construyen.
     */
    public function run(): void
    {
        MovimientoKardex::query()->delete();

        $productos = Producto::all();

        // 1. Entradas por compras RECIBIDAS (orden cronológico)
        $comprasRecibidas = Compra::where('estado', 'recibida')
            ->with('detalles.producto')
            ->orderBy('fecha_recepcion')
            ->orderBy('id')
            ->get();

        foreach ($comprasRecibidas as $compra) {
            foreach ($compra->detalles as $detalle) {
                $producto = $productos->firstWhere('id', $detalle->producto_id);
                if (! $producto) {
                    continue;
                }
                $prev = $this->ultimoStock($producto->id);
                $cantidad = $detalle->cantidad;
                $stockDespues = $prev + $cantidad;

                $this->movimiento(
                    $producto,
                    'entrada',
                    'compra',
                    $compra->correlativo,
                    $cantidad,
                    $prev,
                    $stockDespues
                );

                // Actualizar stock e inventario del producto
                $producto->update([
                    'stock' => $stockDespues,
                    'inventario' => $stockDespues,
                ]);
            }
        }

        // 2. Salidas por venta (orden cronológico)
        $detalleVentas = DetalleVenta::with('venta')->orderBy('venta_id')->get();
        foreach ($detalleVentas as $detalle) {
            $producto = $productos->firstWhere('id', $detalle->producto_id);
            if (! $producto) {
                continue;
            }
            $prev = $this->ultimoStock($producto->id);
            $cantidad = $detalle->cantidad;
            $stockDespues = max(0, $prev - $cantidad);

            $this->movimiento(
                $producto,
                'salida',
                'venta',
                $detalle->venta->correlativo,
                $cantidad,
                $prev,
                $stockDespues
            );

            // Actualizar stock e inventario del producto
            $producto->update([
                'stock' => $stockDespues,
                'inventario' => $stockDespues,
            ]);
        }

        // 3. Salidas por cambio de aceite confirmado
        $cambios = CambioAceite::where('estado', 'confirmado')->with('automotor')->get();
        foreach ($cambios as $cambio) {
            foreach ($cambio->productos as $productoMod) {
                $producto = $productos->firstWhere('id', $productoMod->id);
                if (! $producto) {
                    continue;
                }
                $prev = $this->ultimoStock($producto->id);
                $cantidad = $productoMod->pivot->cantidad;
                $stockDespues = max(0, $prev - $cantidad);
                $origenId = $cambio->automotor?->placa ?? 'N/A';

                $this->movimiento(
                    $producto,
                    'salida',
                    'cambio_aceite',
                    $origenId,
                    $cantidad,
                    $prev,
                    $stockDespues
                );

                // Actualizar stock e inventario del producto
                $producto->update([
                    'stock' => $stockDespues,
                    'inventario' => $stockDespues,
                ]);
            }
        }
    }

    private function ultimoStock(int $productoId): int
    {
        $ultimo = MovimientoKardex::where('producto_id', $productoId)
            ->latest('id')
            ->first();

        return $ultimo ? $ultimo->stock_despues : 0;
    }

    private function movimiento(
        Producto $producto,
        string $tipo,
        string $fuente,
        string $origenId,
        int $cantidad,
        int $stockAntes,
        int $stockDespues
    ): void {
        MovimientoKardex::create([
            'producto_id' => $producto->id,
            'tipo' => $tipo,
            'fuente' => $fuente,
            'origen_id' => $origenId,
            'cantidad' => $cantidad,
            'stock_antes' => $stockAntes,
            'stock_despues' => $stockDespues,
            'usuario_id' => Venta::query()->first()?->user_id ?? 1,
            'fecha_movimiento' => now()->subHours($producto->id),
        ]);
    }
}
