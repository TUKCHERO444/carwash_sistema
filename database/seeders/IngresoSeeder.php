<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ingreso;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Vehiculo;
use Faker\Factory as Faker;

class IngresoSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        $clienteIds = Cliente::pluck('id')->toArray();
        $vehiculoIds = Vehiculo::pluck('id')->toArray();
        $userIds = User::pluck('id')->toArray();

        for ($i = 0; $i < 50; $i++) {
            $tieneFoto = $faker->boolean(30); // 30% chance of having a photo
            $precio = $faker->randomFloat(2, 20, 500);
            $total = $faker->boolean(20) // 20% chance of discount
                ? round($precio * $faker->randomFloat(2, 0.7, 0.99), 2)
                : $precio;

            Ingreso::create([
                'cliente_id' => $faker->randomElement($clienteIds),
                'vehiculo_id' => $faker->randomElement($vehiculoIds),
                'fecha' => $faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
                'foto' => $tieneFoto ? 'fotos/ingreso_' . ($i + 1) . '.jpg' : null,
                'precio' => $precio,
                'total' => $total,
                'user_id' => $faker->randomElement($userIds),
            ]);
        }
    }
}
