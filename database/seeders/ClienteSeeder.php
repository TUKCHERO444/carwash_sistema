<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

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
            ]);
        }
    }
}
