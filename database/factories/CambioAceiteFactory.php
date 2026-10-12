<?php

namespace Database\Factories;

use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CambioAceiteFactory extends Factory
{
    protected $model = CambioAceite::class;

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'user_id' => User::factory(),
            'fecha' => fake()->date(),
            'precio' => fake()->randomFloat(2, 10, 500),
            'total' => function (array $attributes) {
                return $attributes['precio'];
            },
            'descripcion' => fake()->optional()->sentence(),
            'caja_id' => null,
            'atencion_inicio_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (CambioAceite $cambio) {
            if ($cambio->trabajadores()->count() === 0) {
                $cambio->trabajadores()->attach(Trabajador::factory()->create()->id);
            }
        });
    }
}
