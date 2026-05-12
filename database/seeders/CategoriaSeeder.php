<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear las 8 categorías (idempotente con firstOrCreate)
        $categorias = [];

        $categoriasData = [
            ['nombre' => 'Aceites y Lubricantes', 'descripcion' => 'Aceites de motor, lubricantes y grasas para vehículos'],
            ['nombre' => 'Filtros',                'descripcion' => 'Filtros de aceite, aire, combustible y habitáculo'],
            ['nombre' => 'Frenos',                 'descripcion' => 'Pastillas, discos, tambores y líquido de frenos'],
            ['nombre' => 'Suspensión',             'descripcion' => 'Amortiguadores, resortes, muelles y componentes de suspensión'],
            ['nombre' => 'Neumáticos',             'descripcion' => 'Neumáticos, llantas, ruedas y cubiertas'],
            ['nombre' => 'Batería y Eléctrico',    'descripcion' => 'Baterías, bujías, alternadores, arranques y componentes eléctricos'],
            ['nombre' => 'Correas y Cadenas',      'descripcion' => 'Correas de distribución, cadenas y bandas'],
            ['nombre' => 'Líquidos',               'descripcion' => 'Refrigerantes, anticongelantes y otros líquidos (excepto aceites)'],
        ];

        foreach ($categoriasData as $data) {
            $categorias[$data['nombre']] = Categoria::firstOrCreate(
                ['nombre' => $data['nombre']],
                ['descripcion' => $data['descripcion'], 'contador_productos' => 0]
            );
        }

        // 2. Mapa de categoría => palabras clave (orden importa: Aceites antes que Líquidos)
        $keywordMap = [
            'Aceites y Lubricantes' => ['aceite', 'lubricante', 'lubricación', 'grasa'],
            'Filtros'               => ['filtro'],
            'Frenos'                => ['freno', 'pastilla', 'disco de freno', 'líquido de freno'],
            'Suspensión'            => ['suspensión', 'amortiguador', 'resorte', 'muelle'],
            'Neumáticos'            => ['neumático', 'llanta', 'rueda', 'cubierta'],
            'Batería y Eléctrico'   => ['batería', 'bujía', 'alternador', 'arranque', 'eléctrico', 'fusible'],
            'Correas y Cadenas'     => ['correa', 'cadena', 'banda'],
            'Líquidos'              => ['líquido', 'refrigerante', 'anticongelante', 'coolant'],
        ];

        // Palabras que excluyen de "Líquidos" (van a Aceites y Lubricantes)
        $aceitesKeywords = ['aceite', 'lubricante'];

        // 3. Asignar categoría a cada producto existente
        $productos = Producto::all();

        foreach ($productos as $producto) {
            $nombreLower = mb_strtolower($producto->nombre);
            $categoriaAsignada = null;

            foreach ($keywordMap as $categoriaNombre => $keywords) {
                // Regla especial: "Líquidos" no aplica si el nombre contiene aceite o lubricante
                if ($categoriaNombre === 'Líquidos') {
                    $esAceite = false;
                    foreach ($aceitesKeywords as $acWord) {
                        if (str_contains($nombreLower, $acWord)) {
                            $esAceite = true;
                            break;
                        }
                    }
                    if ($esAceite) {
                        continue;
                    }
                }

                foreach ($keywords as $keyword) {
                    if (str_contains($nombreLower, mb_strtolower($keyword))) {
                        $categoriaAsignada = $categoriaNombre;
                        break 2;
                    }
                }
            }

            $producto->categoria_id = $categoriaAsignada !== null
                ? $categorias[$categoriaAsignada]->id
                : null;
            $producto->save();
        }

        // 4. Actualizar contador_productos para cada categoría
        foreach ($categorias as $categoria) {
            $categoria->contador_productos = Producto::where('categoria_id', $categoria->id)->count();
            $categoria->save();
        }
    }
}
