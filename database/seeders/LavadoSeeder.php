<?php

namespace Database\Seeders;

use App\Models\Automotor;
use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\User;
use App\Models\Vehiculo;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class LavadoSeeder extends Seeder
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

            $clienteId = $faker->randomElement($clienteIds);
            $automotorPlaca = Automotor::where('cliente_id', $clienteId)->inRandomOrder()->value('placa');

            Lavado::create([
                'cliente_id' => $clienteId,
                'automotor_id' => $automotorPlaca,
                'vehiculo_id' => $faker->randomElement($vehiculoIds),
                'fecha' => $faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
                'foto' => $tieneFoto ? 'fotos/lavado_'.($i + 1).'.jpg' : null,
                'precio' => $precio,
                'total' => $total,
                'user_id' => $faker->randomElement($userIds),
            ]);
        }
    }
}
