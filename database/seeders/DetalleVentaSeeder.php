<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use Faker\Factory as Faker;

class DetalleVentaSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        // Cargar todos los productos con sus precios de venta
        $productos = Producto::all()->keyBy('id');
        $productoIds = $productos->keys()->toArray();

        $ventas = Venta::all();

        foreach ($ventas as $venta) {
            // Generar entre 1 y 6 productos por venta
            $cantidadProductos = $faker->numberBetween(1, min(6, count($productoIds)));

            // Seleccionar productos únicos para esta venta
            $productosSeleccionados = $faker->randomElements(
                $productoIds,
                $cantidadProductos
            );

            $totalVenta = 0;

            foreach ($productosSeleccionados as $productoId) {
                $cantidad       = $faker->numberBetween(1, 10);
                $precioUnitario = (float) $productos[$productoId]->precio_venta;
                $subtotal       = round($cantidad * $precioUnitario, 2);

                DetalleVenta::create([
                    'venta_id'        => $venta->id,
                    'producto_id'     => $productoId,
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'subtotal'        => $subtotal,
                ]);

                $totalVenta += $subtotal;
            }

            // Actualizar el total de la venta con la suma de subtotales
            $venta->update([
                'subtotal' => round($totalVenta, 2),
                'total'    => round($totalVenta, 2),
            ]);
        }
    }
}
