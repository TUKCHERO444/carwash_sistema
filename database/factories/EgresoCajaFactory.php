<?php

namespace Database\Factories;

use App\Models\EgresoCaja;
use App\Models\Caja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EgresoCajaFactory extends Factory
{
    protected $model = EgresoCaja::class;

    public function definition(): array
    {
        return [
            'caja_id'     => Caja::factory(),
            'monto'       => fake()->randomFloat(2, 5, 100),
            'descripcion' => fake()->sentence(3),
            'tipo_pago'   => fake()->randomElement(['efectivo', 'yape']),
            'user_id'     => User::factory(),
        ];
    }
}
