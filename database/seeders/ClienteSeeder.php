<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Services\DataNormalizer;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Genera 20 clientes con la estructura estándar:
     * dni (8 dígitos), nombre / apellidos (solo letras), teléfono (9 dígitos).
     * Idempotente: usa firstOrCreate por DNI.
     */
    public function run(): void
    {
        if (! $this->debePoblar()) {
            return;
        }

        $faker = Faker::create('es_PE');
        $normalizer = app(DataNormalizer::class);

        for ($i = 0; $i < 20; $i++) {
            $dni = $normalizer->normalizarDni($faker->unique()->numerify('########'));

            if (Cliente::where('dni', $dni)->exists()) {
                continue;
            }

            Cliente::create([
                'dni' => $dni,
                'nombre' => $normalizer->normalizarNombre($faker->firstName()),
                'apellido_paterno' => $normalizer->normalizarNombre($faker->lastName()),
                'apellido_materno' => $normalizer->normalizarNombre($faker->lastName()),
                'telefono' => $normalizer->normalizarTelefono($faker->numerify('9########')),
            ]);
        }
    }

    /**
     * Solo puebla clientes si no existen aún (evita duplicar en re-seeds).
     */
    private function debePoblar(): bool
    {
        return Cliente::count() === 0;
    }
}
