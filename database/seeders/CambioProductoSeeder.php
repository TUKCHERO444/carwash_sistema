<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CambioAceite;
use App\Models\CambioProducto;
use App\Models\Producto;
use Faker\Factory as Faker;

class CambioProductoSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        // Solo productos relacionados con aceites y filtros
        $productosAceitesFiltros = Producto::where('nombre', 'like', '%Aceite%')
            ->orWhere('nombre', 'like', '%Filtro%')
            ->pluck('id')
            ->toArray();

        // Si no hay productos filtrados, usar todos los productos como fallback
        if (empty($productosAceitesFiltros)) {
            $productosAceitesFiltros = Producto::pluck('id')->toArray();
        }

        // Cargar productos con precios para calcular totales
        $productos = Producto::whereIn('id', $productosAceitesFiltros)->get()->keyBy('id');

        $cambioAceites = CambioAceite::all();

        foreach ($cambioAceites as $cambioAceite) {
            // Generar entre 1 y 4 productos por cambio de aceite
            $cantidadProductos = $faker->numberBetween(1, min(4, count($productosAceitesFiltros)));

            // Seleccionar productos únicos para este cambio de aceite
            $productosSeleccionados = $faker->randomElements(
                $productosAceitesFiltros,
                $cantidadProductos
            );

            foreach ($productosSeleccionados as $productoId) {
                // Verificar que no exista ya la combinación (respeta unique constraint)
                $existe = CambioProducto::where('cambio_aceite_id', $cambioAceite->id)
                    ->where('producto_id', $productoId)
                    ->exists();

                if (!$existe) {
                    $cantidad = $faker->numberBetween(1, 5);
                    $precio   = (float) $productos->find($productoId)->precio_venta;
                    $total    = round($cantidad * $precio, 2);

                    CambioProducto::create([
                        'cambio_aceite_id' => $cambioAceite->id,
                        'producto_id'      => $productoId,
                        'cantidad'         => $cantidad,
                        'precio'           => $precio,
                        'total'            => $total,
                    ]);
                }
            }
        }
    }
}
