<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crea (o repara) el catálogo de las 8 categorías de la web pública.
 *
 * Se ejecuta ANTES de `ProductoSeeder` porque `productos.categoria_id` es
 * NOT NULL: los productos deben poder insertarse con una categoría válida. La
 * asignación definitiva por palabras clave del nombre la hace
 * `ProductoCategoriaSeeder`, que corre después del alta de los productos.
 *
 * @see ProductoCategoriaSeeder
 * @see tests/Feature/SeederWebFlowTest.php
 */
class CategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categoriasData = [
            ['nombre' => 'Aceites y Lubricantes', 'descripcion' => 'Aceites de motor lubricantes y grasas para vehículos'],
            ['nombre' => 'Filtros',                'descripcion' => 'Filtros de aceite aire combustible y habitáculo'],
            ['nombre' => 'Frenos',                 'descripcion' => 'Pastillas discos tambores y líquido de frenos'],
            ['nombre' => 'Suspensión',             'descripcion' => 'Amortiguadores resortes muelles y componentes de suspensión'],
            ['nombre' => 'Neumáticos',             'descripcion' => 'Neumáticos llantas ruedas y cubiertas'],
            ['nombre' => 'Batería y Eléctrico',    'descripcion' => 'Baterías bujías alternadores arranques y componentes eléctricos'],
            ['nombre' => 'Correas y Cadenas',      'descripcion' => 'Correas de distribución cadenas y bandas'],
            ['nombre' => 'Líquidos',               'descripcion' => 'Refrigerantes anticongelantes y otros líquidos excepto aceites'],
        ];

        foreach ($categoriasData as $data) {
            Categoria::updateOrCreate(
                ['nombre' => $data['nombre']],
                [
                    // El slug se asigna de forma explícita: DatabaseSeeder corre
                    // sin eventos de modelo (WithoutModelEvents) y el hook
                    // `saving` del modelo Categoria no dispara la generación.
                    'slug' => Str::slug($data['nombre']),
                    'descripcion' => $data['descripcion'],
                    'contador_productos' => 0,
                ]
            );
        }

        // Respaldo idempotente: categorías de una BD legacy que quedaron sin
        // slug (por ejemplo sembradas antes de la migración de slugs).
        foreach (Categoria::whereNull('slug')->get() as $categoria) {
            $categoria->slug = Str::slug($categoria->nombre);
            $categoria->save();
        }
    }
}
