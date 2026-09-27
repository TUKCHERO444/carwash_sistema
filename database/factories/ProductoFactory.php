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
        $precioCompra = $this->faker->randomFloat(2, 1, 100);

        return [
            'nombre' => $this->faker->word(),
            'precio_compra' => $precioCompra,
            // El margen se mantiene, como antes: la venta nunca queda bajo el costo.
            'precio_venta' => round($precioCompra + $this->faker->randomFloat(2, 1, 100), 2),
            // Un producto nace sin existencias: la reposición es una operación de
            // inventario, no un dato de catálogo. Ver spec compras-ingreso-mercaderia.
            'stock' => 0,
            'inventario' => 0,
            'activo' => true,
            'foto' => null,
            'categoria_id' => null,
        ];
    }

    /**
     * Producto con existencias, para los tests que_parten de un stock conocido.
     */
    public function conExistencias(int $stock = 20, ?int $inventario = null): static
    {
        $inventario ??= $stock;

        return $this->state(fn () => [
            'stock' => $stock,
            'inventario' => $inventario,
        ]);
    }

    /**
     * Producto con un margen perdido: el precio de venta quedó por debajo del costo.
     */
    public function conMargenPerdido(): static
    {
        return $this->state(function () {
            $precioCompra = $this->faker->randomFloat(2, 30, 100);
            $precioVenta = round($precioCompra * $this->faker->randomFloat(2, 0.5, 0.95), 2);

            return [
                'precio_compra' => $precioCompra,
                'precio_venta' => $precioVenta,
            ];
        });
    }
}
