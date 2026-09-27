<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruc' => $this->faker->unique()->numerify('###########'),
            'razon_social' => $this->faker->company(),
            'direccion' => $this->faker->address(),
            'estado_tributario' => 'Activo',
            'condicion' => 'Contribuyente',
            'estado' => 1,
        ];
    }

    /**
     * Proveedor inactivo (estado = 0).
     */
    public function inactivo(): static
    {
        return $this->state(fn () => ['estado' => 0]);
    }
}
