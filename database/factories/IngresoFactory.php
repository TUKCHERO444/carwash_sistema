<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Ingreso;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

class IngresoFactory extends Factory
{
    protected $model = Ingreso::class;

    public function definition(): array
    {
        return [
            'cliente_id'  => Cliente::factory(),
            'vehiculo_id' => Vehiculo::factory(),
            'user_id'     => User::factory(),
            'fecha'       => fake()->date(),
            'precio'      => 0,
            'total'       => 0,
            'estado'      => 'pendiente',
            'caja_id'     => null,
        ];
    }

    public function pendiente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'pendiente',
        ]);
    }

    public function confirmado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado'      => 'confirmado',
            'precio'      => fake()->randomFloat(2, 10, 500),
            'total'       => fake()->randomFloat(2, 10, 500),
            'metodo_pago' => fake()->randomElement(['efectivo', 'yape', 'izipay']),
        ]);
    }
}
