<?php

namespace Database\Factories;

use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CambioAceiteFactory extends Factory
{
    protected $model = CambioAceite::class;

    public function definition(): array
    {
        return [
            'cliente_id'     => Cliente::factory(),
            'trabajador_id'  => Trabajador::factory(),
            'user_id'        => User::factory(),
            'fecha'          => fake()->date(),
            'precio'         => fake()->randomFloat(2, 10, 500),
            'total'          => function (array $attributes) {
                return $attributes['precio'];
            },
            'descripcion'    => fake()->optional()->sentence(),
            'metodo_pago'    => fake()->randomElement(['efectivo', 'yape', 'izipay']),
            'caja_id'        => null,
        ];
    }
}
