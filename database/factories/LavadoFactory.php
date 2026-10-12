<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\TipoVehiculo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LavadoFactory extends Factory
{
    protected $model = Lavado::class;

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'tipo_vehiculo_id' => TipoVehiculo::factory(),
            'user_id' => User::factory(),
            'fecha' => fake()->date(),
            'precio' => 0,
            'total' => 0,
            'estado' => 'pendiente',
            'caja_id' => null,
            'atencion_inicio_at' => now(),
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
            'estado' => 'confirmado',
            'precio' => fake()->randomFloat(2, 10, 500),
            'total' => fake()->randomFloat(2, 10, 500),
            'atencion_inicio_at' => now()->subMinutes(45),
            'atencion_fin_at' => now(),
            'tiempo_atencion_minutos' => 45,
        ]);
    }
}
