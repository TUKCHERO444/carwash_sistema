<?php

namespace Database\Factories;

use App\Models\DetalleCompra;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DetalleCompra>
 */
class DetalleCompraFactory extends Factory
{
    protected $model = DetalleCompra::class;

    public function definition(): array
    {
        return [
            'compra_id' => null,
            'producto_id' => Producto::inRandomOrder()->first()?->id ?? Producto::factory(),
            'cantidad' => $this->faker->numberBetween(1, 50),
            'costo_unitario' => $this->faker->randomFloat(2, 1, 200),
            'subtotal' => 0,
        ];
    }

    public function withCompra(int $compraId): static
    {
        return $this->state(fn () => ['compra_id' => $compraId]);
    }
}
