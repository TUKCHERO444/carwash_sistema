<?php

namespace Database\Seeders;

use App\Models\CambioAceite;
use App\Models\DetalleVenta;
use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Database\Seeder;

class KardexSeeder extends Seeder
{
    public function run(): void
    {
        MovimientoKardex::query()->delete();

        // Reconstruir el Kardex a partir de los datos ya sembrados, moviendo el
        // stock de forma progresiva (entradas iniciales + salidas por venta/cambio).
        $productos = Producto::all();

        foreach ($productos as $producto) {
            // Entrada inicial (stock inicial)
            $this->movimiento(
                $producto,
                'entrada',
                'inventario',
                'INV-'.str_pad($producto->id, 4, '0', STR_PAD_LEFT),
                $producto->inventario,
                0,
                $producto->inventario
            );
        }

        // Salidas por venta (orden cronológico)
        $detalleVentas = DetalleVenta::with('venta')->orderBy('venta_id')->get();
        foreach ($detalleVentas as $detalle) {
            $producto = $productos->firstWhere('id', $detalle->producto_id);
            if (! $producto) {
                continue;
            }
            $prev = $this->ultimoStock($producto->id);
            $this->movimiento(
                $producto,
                'salida',
                'venta',
                $detalle->venta->correlativo,
                $detalle->cantidad,
                $prev,
                max(0, $prev - $detalle->cantidad)
            );
        }

        // Salidas por cambio de aceite confirmado
        $cambios = CambioAceite::where('estado', 'confirmado')->get();
        foreach ($cambios as $cambio) {
            foreach ($cambio->productos as $productoMod) {
                $producto = $productos->firstWhere('id', $productoMod->id);
                if (! $producto) {
                    continue;
                }
                $prev = $this->ultimoStock($producto->id);
                $this->movimiento(
                    $producto,
                    'salida',
                    'cambio_aceite',
                    $cambio->automotor_id ?? 'N/A',
                    $productoMod->pivot->cantidad,
                    $prev,
                    max(0, $prev - $productoMod->pivot->cantidad)
                );
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
