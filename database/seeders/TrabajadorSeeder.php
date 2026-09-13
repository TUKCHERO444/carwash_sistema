<?php

namespace Database\Seeders;

use App\Models\Trabajador;
use App\Services\DataNormalizer;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class TrabajadorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Genera 10 trabajadores con la estructura estándar:
     * dni (8 dígitos), nombre / apellidos (solo letras).
     * 80% activos (8), 20% inactivos (2).
     * Idempotente: usa firstOrCreate por DNI.
     */
    public function run(): void
    {
        if ($this->debePoblar() === false) {
            return;
        }

        $faker = Faker::create('es_PE');
        $normalizer = app(DataNormalizer::class);

        for ($i = 0; $i < 10; $i++) {
            $dni = $normalizer->normalizarDni($faker->unique()->numerify('########'));

            if (Trabajador::where('dni', $dni)->exists()) {
                continue;
            }

            Trabajador::create([
                'dni' => $dni,
                'nombre' => $normalizer->normalizarNombre($faker->firstName()),
                'apellido_paterno' => $normalizer->normalizarNombre($faker->lastName()),
                'apellido_materno' => $normalizer->normalizarNombre($faker->lastName()),
                'estado' => $i < 8,
                'pago_diario' => 50,
            ]);
        }
    }

    /**
     * Solo puebla trabajadores si no existen aún (evita duplicar en re-seeds).
     */
    private function debePoblar(): bool
    {
        return Trabajador::count() === 0;
    }
}
