<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ClienteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('es_ES');
        
        for ($i = 0; $i < 20; $i++) {
            Cliente::create([
                'dni' => $faker->unique()->numerify('########'),
                'nombre' => $faker->name(),
                'placa' => $this->generarPlaca($faker),
            ]);
        }
    }

    /**
     * Generar placa con formato ABC123
     */
    private function generarPlaca($faker): string
    {
        $letras = strtoupper($faker->lexify('???'));
        $numeros = $faker->numerify('###');
        return $letras . $numeros;
    }
}
