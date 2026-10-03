<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Seeder;

/**
 * Asigna a cada producto su categoría definitiva por palabras clave del nombre
 * y recalcula `contador_productos`.
 *
 * Se ejecuta DESPUÉS de `ProductoSeeder` (necesita los productos ya cargados) y
 * es idempotente: se puede volver a correr sin duplicar nada.
 *
 * `categoria_id` es NOT NULL, así que un producto sin coincidencia conserva la
 * categoría asignada en el alta en lugar de quedar en null.
 */
class ProductoCategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorias = Categoria::pluck('id', 'nombre');

        if ($categorias->isEmpty()) {
            return;
        }

        // Mapa de categoría => palabras clave (orden importa: Aceites antes que Líquidos)
        $keywordMap = [
            'Aceites y Lubricantes' => ['aceite', 'lubricante', 'lubricación', 'grasa'],
            'Filtros' => ['filtro'],
            'Frenos' => ['freno', 'pastilla', 'disco de freno', 'líquido de freno'],
            'Suspensión' => ['suspensión', 'amortiguador', 'resorte', 'muelle'],
            'Neumáticos' => ['neumático', 'llanta', 'rueda', 'cubierta'],
            'Batería y Eléctrico' => ['batería', 'bujía', 'alternador', 'arranque', 'eléctrico', 'fusible'],
            'Correas y Cadenas' => ['correa', 'cadena', 'banda'],
            'Líquidos' => ['líquido', 'refrigerante', 'anticongelante', 'coolant', 'aditivo'],
        ];

        // Palabras que excluyen de "Líquidos" (van a Aceites y Lubricantes)
        $aceitesKeywords = ['aceite', 'lubricante'];

        $categoriaPorDefecto = $categorias->first();

        foreach (Producto::all() as $producto) {
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
                ? $categorias[$categoriaAsignada]
                : ($producto->categoria_id ?: $categoriaPorDefecto);
            $producto->save();
        }

        // Actualizar contador_productos para cada categoría
        foreach ($categorias as $id) {
            Categoria::where('id', $id)->update([
                'contador_productos' => Producto::where('categoria_id', $id)->count(),
            ]);
        }
    }
}
