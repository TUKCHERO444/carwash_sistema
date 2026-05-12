<?php

namespace Database\Seeders;

use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Trabajador;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class CambioAceiteSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        $clienteIds        = Cliente::pluck('id')->toArray();
        $trabajadoresActivos = Trabajador::where('estado', true)->pluck('id')->toArray();
        $userIds           = User::pluck('id')->toArray();

        for ($i = 0; $i < 40; $i++) {
            // precio base del servicio de cambio de aceite
            $precio = $faker->randomFloat(2, 30, 120);

            // total puede incluir descuento o ser igual al precio
            $total = $faker->boolean(20)
                ? round($precio * $faker->randomFloat(2, 0.8, 0.99), 2)
                : $precio;

            CambioAceite::create([
                'cliente_id'    => $faker->randomElement($clienteIds),
                'trabajador_id' => $faker->randomElement($trabajadoresActivos),
                'fecha'         => $faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
                'precio'        => $precio,
                'total'         => $total,
                'descripcion'   => $faker->optional(0.4)->sentence(),
                'user_id'       => $faker->randomElement($userIds),
            ]);
        }
    }
}
