<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->word(),
            'precio_compra' => $this->faker->randomFloat(2, 1, 100),
            'precio_venta' => $this->faker->randomFloat(2, 101, 200),
            'stock' => $this->faker->numberBetween(0, 500),
            'inventario' => $this->faker->numberBetween(0, 500),
            'activo' => true,
            'foto' => null,
            'categoria_id' => null,
        ];
    }
}
