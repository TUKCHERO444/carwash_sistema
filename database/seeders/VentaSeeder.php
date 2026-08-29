<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Venta;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class VentaSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        $userIds = User::pluck('id')->toArray();

        for ($i = 0; $i < 60; $i++) {
            $correlativo = 'VTA-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT);

            Venta::create([
                'correlativo' => $correlativo,
                'user_id' => $faker->randomElement($userIds),
                'observacion' => $faker->optional(0.3)->sentence(),
                'subtotal' => 0, // calculated in DetalleVentaSeeder
                'total' => 0, // calculated in DetalleVentaSeeder
            ]);
        }
    }
}
