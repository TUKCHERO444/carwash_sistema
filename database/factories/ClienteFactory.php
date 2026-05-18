<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'placa' => strtoupper($this->faker->bothify('???-###')),
            'nombre' => $this->faker->name(),
            'dni' => $this->faker->numerify('########'),
            'telefono' => $this->faker->phoneNumber(),
        ];
    }
}
