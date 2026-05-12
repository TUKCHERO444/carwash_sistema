<?php

namespace Database\Factories;

use App\Models\Venta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VentaFactory extends Factory
{
    protected $model = Venta::class;

    public function definition(): array
    {
        return [
            'correlativo'    => 'V' . fake()->unique()->numerify('#####'),
            'observacion'    => fake()->optional()->sentence(),
            'subtotal'       => fake()->randomFloat(2, 10, 500),
            'total'          => function (array $attributes) {
                return $attributes['subtotal'];
            },
            'metodo_pago'    => fake()->randomElement(['efectivo', 'yape', 'izipay']),
            'user_id'        => User::factory(),
            'caja_id'        => null,
        ];
    }
}
