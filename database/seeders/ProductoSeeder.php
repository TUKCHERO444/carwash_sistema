<?php

namespace Database\Seeders;

use App\Models\Producto;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class ProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Genera 30 productos organizados por categorías con precios realistas.
     * precio_venta siempre es mayor que precio_compra (margen 20%-80%).
     */
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        // Productos predefinidos por categoría con precio base de compra
        $productos = [
            // Aceites y lubricantes
            ['nombre' => 'Aceite Motor 5W-30 Sintético',        'precio_compra_base' => 35.00],
            ['nombre' => 'Aceite Motor 10W-40 Semi-Sintético',  'precio_compra_base' => 25.00],
            ['nombre' => 'Aceite Motor 20W-50 Mineral',         'precio_compra_base' => 18.00],
            ['nombre' => 'Aceite Transmisión ATF',              'precio_compra_base' => 30.00],
            ['nombre' => 'Aceite Hidráulico',                   'precio_compra_base' => 22.00],

            // Filtros
            ['nombre' => 'Filtro de Aceite',                    'precio_compra_base' => 8.00],
            ['nombre' => 'Filtro de Aire',                      'precio_compra_base' => 12.00],
            ['nombre' => 'Filtro de Combustible',               'precio_compra_base' => 15.00],
            ['nombre' => 'Filtro de Cabina',                    'precio_compra_base' => 10.00],

            // Frenos
            ['nombre' => 'Pastillas de Freno Delanteras',       'precio_compra_base' => 40.00],
            ['nombre' => 'Pastillas de Freno Traseras',         'precio_compra_base' => 35.00],
            ['nombre' => 'Discos de Freno Delanteros',          'precio_compra_base' => 80.00],
            ['nombre' => 'Discos de Freno Traseros',            'precio_compra_base' => 70.00],
            ['nombre' => 'Líquido de Frenos DOT 4',             'precio_compra_base' => 12.00],

            // Suspensión
            ['nombre' => 'Amortiguador Delantero',              'precio_compra_base' => 90.00],
            ['nombre' => 'Amortiguador Trasero',                'precio_compra_base' => 80.00],
            ['nombre' => 'Resorte de Suspensión',               'precio_compra_base' => 50.00],

            // Neumáticos
            ['nombre' => 'Neumático 185/65 R15',                'precio_compra_base' => 60.00],
            ['nombre' => 'Neumático 195/55 R16',                'precio_compra_base' => 75.00],
            ['nombre' => 'Neumático 205/55 R17',                'precio_compra_base' => 90.00],

            // Batería y eléctrico
            ['nombre' => 'Batería 12V 45Ah',                    'precio_compra_base' => 70.00],
            ['nombre' => 'Batería 12V 65Ah',                    'precio_compra_base' => 95.00],
            ['nombre' => 'Bujías de Encendido (set 4)',         'precio_compra_base' => 20.00],
            ['nombre' => 'Alternador',                          'precio_compra_base' => 150.00],

            // Correas y cadenas
            ['nombre' => 'Correa de Distribución',              'precio_compra_base' => 45.00],
            ['nombre' => 'Correa de Accesorios',                'precio_compra_base' => 20.00],
            ['nombre' => 'Tensor de Correa',                    'precio_compra_base' => 30.00],

            // Líquidos
            ['nombre' => 'Refrigerante Motor',                  'precio_compra_base' => 15.00],
            ['nombre' => 'Líquido Limpiaparabrisas',            'precio_compra_base' => 5.00],
            ['nombre' => 'Aditivo Limpiador Motor',             'precio_compra_base' => 18.00],
        ];

        foreach ($productos as $producto) {
            // Aplicar variación aleatoria ±10% al precio base de compra
            $precioCompra = round($producto['precio_compra_base'] * $faker->randomFloat(2, 0.9, 1.1), 2);

            // Margen entre 20% y 80% sobre el precio de compra
            $margen = $faker->randomFloat(2, 1.20, 1.80);
            $precioVenta = round($precioCompra * $margen, 2);

            // Garantizar que precio_venta > precio_compra (invariante de negocio)
            if ($precioVenta <= $precioCompra) {
                $precioVenta = round($precioCompra * 1.20, 2);
            }

            Producto::create([
                'nombre'        => $producto['nombre'],
                'precio_compra' => $precioCompra,
                'precio_venta'  => $precioVenta,
                'stock'         => $faker->numberBetween(0, 100),
                'inventario'    => $faker->numberBetween(0, 500),
                'activo'        => 1,
                'foto'          => null,
            ]);
        }
    }
}
