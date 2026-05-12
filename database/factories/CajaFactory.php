<?php

namespace Database\Factories;

use App\Models\Caja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CajaFactory extends Factory
{
    protected $model = Caja::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'estado'         => 'abierta',
            'monto_inicial'  => fake()->randomFloat(2, 50, 500),
            'fecha_apertura' => now(),
            'fecha_cierre'   => null,
        ];
    }

    public function abierta(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado'       => 'abierta',
            'fecha_cierre' => null,
        ]);
    }

    public function cerrada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado'       => 'cerrada',
            'fecha_cierre' => now(),
        ]);
    }
}
