<?php

namespace Database\Seeders;

use App\Models\Servicio;
use Illuminate\Database\Seeder;

class ServicioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Genera 12 servicios predefinidos típicos de un taller mecánico.
     */
    public function run(): void
    {
        $servicios = [
            ['nombre' => 'Cambio de Aceite y Filtro',           'precio' => 80.00],
            ['nombre' => 'Alineación y Balanceo',               'precio' => 60.00],
            ['nombre' => 'Revisión de Frenos',                  'precio' => 50.00],
            ['nombre' => 'Cambio de Pastillas de Freno',        'precio' => 150.00],
            ['nombre' => 'Cambio de Neumáticos',                'precio' => 100.00],
            ['nombre' => 'Diagnóstico Computarizado',           'precio' => 70.00],
            ['nombre' => 'Cambio de Batería',                   'precio' => 120.00],
            ['nombre' => 'Revisión de Suspensión',              'precio' => 90.00],
            ['nombre' => 'Cambio de Correa de Distribución',    'precio' => 350.00],
            ['nombre' => 'Limpieza de Inyectores',              'precio' => 180.00],
            ['nombre' => 'Cambio de Líquido de Frenos',         'precio' => 65.00],
            ['nombre' => 'Mantenimiento Preventivo Completo',   'precio' => 250.00],
        ];

        foreach ($servicios as $servicio) {
            Servicio::create($servicio);
        }
    }
}
