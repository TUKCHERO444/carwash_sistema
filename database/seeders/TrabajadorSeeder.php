<?php

namespace Database\Seeders;

use App\Models\Trabajador;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class TrabajadorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Genera 10 trabajadores: 80% activos (8), 20% inactivos (2).
     */
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        for ($i = 0; $i < 10; $i++) {
            Trabajador::create([
                'nombre' => $faker->name(),
                // Primeros 8 (índices 0-7) activos, últimos 2 (8-9) inactivos
                'estado' => $i < 8,
            ]);
        }
    }
}
