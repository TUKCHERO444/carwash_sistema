<?php

namespace Database\Seeders;

use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class CambioAceiteSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        $clienteIds = Cliente::pluck('id')->toArray();
        $trabajadoresActivos = Trabajador::where('estado', true)->pluck('id')->toArray();
        $userIds = User::pluck('id')->toArray();

        for ($i = 0; $i < 40; $i++) {
            // precio base del servicio de cambio de aceite
            $precio = $faker->randomFloat(2, 30, 120);

            // total puede incluir descuento o ser igual al precio
            $total = $faker->boolean(20)
                ? round($precio * $faker->randomFloat(2, 0.8, 0.99), 2)
                : $precio;

            $clienteId = $faker->randomElement($clienteIds);
            $vehiculoPlaca = Vehiculo::where('cliente_id', $clienteId)->inRandomOrder()->value('placa');

            $cambio = CambioAceite::create([
                'cliente_id' => $clienteId,
                'vehiculo_id' => $vehiculoPlaca,
                'fecha' => $faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
                'precio' => $precio,
                'total' => $total,
                'descripcion' => $faker->optional(0.4)->sentence(),
                'user_id' => $faker->randomElement($userIds),
            ]);

            if (! empty($trabajadoresActivos)) {
                $cambio->trabajadores()->attach(
                    $faker->randomElements($trabajadoresActivos, min(count($trabajadoresActivos), $faker->numberBetween(1, 2)))
                );
            }

            $metodoId = MetodoPago::where('activo', true)->inRandomOrder()->value('id');
            if ($metodoId) {
                $cambio->pagos()->create([
                    'metodo_pago_id' => $metodoId,
                    'monto' => $total,
                    'user_id' => $cambio->user_id,
                ]);
            }
        }
    }
}
