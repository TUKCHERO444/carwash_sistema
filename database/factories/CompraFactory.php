<?php

namespace Database\Factories;

use App\Models\Compra;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Compra>
 */
class CompraFactory extends Factory
{
    protected $model = Compra::class;

    public function definition(): array
    {
        $estados = ['borrador', 'recibida', 'anulada'];
        $tipos = ['Factura', 'Boleta', 'Ticket', 'Nota de venta', 'Guia de remision', 'Otros'];

        return [
            'correlativo' => null,
            'proveedor_id' => Proveedor::inRandomOrder()->first()?->id ?? Proveedor::factory(),
            'fecha' => $this->faker->date(),
            'tipo_documento' => $this->faker->randomElement($tipos),
            'numero_documento' => $this->faker->bothify('???-####'),
            'estado' => $this->faker->randomElement($estados),
            'subtotal' => $this->faker->randomFloat(2, 10, 5000),
            'total' => $this->faker->randomFloat(2, 10, 5000),
            'observaciones' => $this->faker->optional()->sentence(),
            'user_id' => 1,
            'caja_id' => null,
            'egreso_caja_id' => null,
            'fecha_recepcion' => null,
        ];
    }

    public function borrador(): static
    {
        return $this->state(fn () => [
            'estado' => 'borrador',
            'correlativo' => null,
            'fecha_recepcion' => null,
            'caja_id' => null,
            'egreso_caja_id' => null,
        ]);
    }

    public function recibida(): static
    {
        return $this->state(fn () => [
            'estado' => 'recibida',
            'correlativo' => 'CMP-'.str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'fecha_recepcion' => now(),
        ]);
    }

    public function anulada(): static
    {
        return $this->state(fn () => [
            'estado' => 'anulada',
            'correlativo' => null,
            'fecha_recepcion' => null,
        ]);
    }
}
