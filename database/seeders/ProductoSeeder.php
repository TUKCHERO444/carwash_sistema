<?php

namespace Database\Seeders;

use App\Models\Marca;
use App\Models\Producto;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class ProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Genera 30 productos organizados por categorías con precios realistas.
     * precio_venta siempre es mayor que precio_compra (margen 20%-80%).
     *
     * Ajustado a la lógica de stock bajo por inventario: `inventario` es la
     * cantidad del ciclo vigente e `stock` el saldo restante (0 <= stock <= inventario).
     * Alrededor de un tercio se siembra en alerta (stock <= ceil(inventario * 0.20)),
     * incluidos algunos agotados, para demostrar los avisos de stock bajo.
     */
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        // Mapeo de marca => palabras clave para asignación automática
        $marcaMap = [
            'Motul' => ['aceite motor 5w-30', 'aceite motor 10w-40', 'aceite transmisión'],
            'Castrol' => ['aceite motor 20w-50', 'aceite hidráulico'],
            'Fram' => ['filtro de aceite', 'filtro de aire', 'filtro de combustible', 'filtro de cabina'],
            'Brembo' => ['pastillas de freno', 'discos de freno'],
            'Continental' => ['líquido de frenos'],
            'Monroe' => ['amortiguador', 'resorte de suspensión'],
            'Michelin' => ['neumático'],
            'Varta' => ['batería'],
            'NGK' => ['bujías'],
            'ACDelco' => ['alternador'],
            'Gates' => ['correa de distribución', 'correa de accesorios', 'tensor de correa'],
            'Mobil' => ['refrigerante', 'aditivo limpiador'],
        ];

        // Productos predefinidos por categoría con precio base de compra e
        // inventario base del ciclo vigente (unidades por reposición).
        $productos = [
            // Aceites y lubricantes (alto consumo: ciclos amplios)
            ['nombre' => 'Aceite Motor 5W-30 Sintético',        'precio_compra_base' => 35.00, 'inventario_base' => 120],
            ['nombre' => 'Aceite Motor 10W-40 Semi-Sintético',  'precio_compra_base' => 25.00, 'inventario_base' => 120],
            ['nombre' => 'Aceite Motor 20W-50 Mineral',         'precio_compra_base' => 18.00, 'inventario_base' => 100],
            ['nombre' => 'Aceite Transmisión ATF',              'precio_compra_base' => 30.00, 'inventario_base' => 60],
            ['nombre' => 'Aceite Hidráulico',                   'precio_compra_base' => 22.00, 'inventario_base' => 60],

            // Filtros (consumo medio)
            ['nombre' => 'Filtro de Aceite',                    'precio_compra_base' => 8.00,  'inventario_base' => 80],
            ['nombre' => 'Filtro de Aire',                      'precio_compra_base' => 12.00, 'inventario_base' => 60],
            ['nombre' => 'Filtro de Combustible',               'precio_compra_base' => 15.00, 'inventario_base' => 40],
            ['nombre' => 'Filtro de Cabina',                    'precio_compra_base' => 10.00, 'inventario_base' => 60],

            // Frenos (consumo medio-bajo)
            ['nombre' => 'Pastillas de Freno Delanteras',       'precio_compra_base' => 40.00, 'inventario_base' => 40],
            ['nombre' => 'Pastillas de Freno Traseras',         'precio_compra_base' => 35.00, 'inventario_base' => 40],
            ['nombre' => 'Discos de Freno Delanteros',          'precio_compra_base' => 80.00, 'inventario_base' => 20],
            ['nombre' => 'Discos de Freno Traseros',            'precio_compra_base' => 70.00, 'inventario_base' => 20],
            ['nombre' => 'Líquido de Frenos DOT 4',             'precio_compra_base' => 12.00, 'inventario_base' => 60],

            // Suspensión (consumo bajo)
            ['nombre' => 'Amortiguador Delantero',              'precio_compra_base' => 90.00, 'inventario_base' => 16],
            ['nombre' => 'Amortiguador Trasero',                'precio_compra_base' => 80.00, 'inventario_base' => 16],
            ['nombre' => 'Resorte de Suspensión',               'precio_compra_base' => 50.00, 'inventario_base' => 12],

            // Neumáticos (consumo bajo, precio alto)
            ['nombre' => 'Neumático 185/65 R15',                'precio_compra_base' => 60.00, 'inventario_base' => 24],
            ['nombre' => 'Neumático 195/55 R16',                'precio_compra_base' => 75.00, 'inventario_base' => 24],
            ['nombre' => 'Neumático 205/55 R17',                'precio_compra_base' => 90.00, 'inventario_base' => 16],

            // Batería y eléctrico (consumo bajo)
            ['nombre' => 'Batería 12V 45Ah',                    'precio_compra_base' => 70.00, 'inventario_base' => 12],
            ['nombre' => 'Batería 12V 65Ah',                    'precio_compra_base' => 95.00, 'inventario_base' => 12],
            ['nombre' => 'Bujías de Encendido (set 4)',         'precio_compra_base' => 20.00, 'inventario_base' => 30],
            ['nombre' => 'Alternador',                          'precio_compra_base' => 150.00, 'inventario_base' => 8],

            // Correas y cadenas (consumo bajo)
            ['nombre' => 'Correa de Distribución',              'precio_compra_base' => 45.00, 'inventario_base' => 16],
            ['nombre' => 'Correa de Accesorios',                'precio_compra_base' => 20.00, 'inventario_base' => 24],
            ['nombre' => 'Tensor de Correa',                    'precio_compra_base' => 30.00, 'inventario_base' => 12],

            // Líquidos (consumo medio)
            ['nombre' => 'Refrigerante Motor',                  'precio_compra_base' => 15.00, 'inventario_base' => 80],
            ['nombre' => 'Líquido Limpiaparabrisas',            'precio_compra_base' => 5.00,  'inventario_base' => 100],
            ['nombre' => 'Aditivo Limpiador Motor',             'precio_compra_base' => 18.00, 'inventario_base' => 40],
        ];

        // Precargar marcas
        $marcas = [];
        foreach ($marcaMap as $marcaNombre => $_) {
            $marca = Marca::where('nombre', $marcaNombre)->first();
            if ($marca) {
                $marcas[$marcaNombre] = $marca->id;
            }
        }

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

            // Asignar marca por coincidencia de palabras clave
            $nombreLower = mb_strtolower($producto['nombre']);
            $marcaId = null;

            foreach ($marcaMap as $marcaNombre => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($nombreLower, $keyword)) {
                        $marcaId = $marcas[$marcaNombre] ?? null;
                        break 2;
                    }
                }
            }

            // Inventario del ciclo vigente: variación ±20% sobre la base para
            // dar naturalidad sin perder el rango realista (siempre >= 1).
            $inventario = max(1, $faker->numberBetween(
                (int) round($producto['inventario_base'] * 0.8),
                (int) round($producto['inventario_base'] * 1.2)
            ));

            // Umbral de alerta: stock <= ceil(inventario * 0.20).
            $umbralAlerta = (int) ceil($inventario * 0.20);

            // ~35% en alerta (incluye agotados con stock 0); el resto por encima
            // del umbral, garantizando siempre 0 <= stock <= inventario.
            if ($faker->boolean(35)) {
                $stock = $faker->numberBetween(0, $umbralAlerta);
            } else {
                $stock = $faker->numberBetween($umbralAlerta + 1, $inventario);
            }

            Producto::create([
                'nombre' => $producto['nombre'],
                'precio_compra' => $precioCompra,
                'precio_venta' => $precioVenta,
                'stock' => $stock,
                'inventario' => $inventario,
                'activo' => 1,
                'foto' => null,
                'marca_id' => $marcaId,
            ]);
        }
    }
}
