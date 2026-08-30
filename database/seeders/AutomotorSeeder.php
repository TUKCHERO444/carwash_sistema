<?php

namespace Database\Seeders;

use App\Models\Automotor;
use App\Models\Cliente;
use App\Services\DataNormalizer;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class AutomotorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Crea automotores vinculados a clientes existentes (1 cliente puede tener 1..N).
     * La placa se normaliza a mayúsculas (estándar) y los campos textuales
     * se normalizan al formato alfanumérico / solo letras del sistema.
     */
    public function run(): void
    {
        $faker = Faker::create('es_PE');
        $normalizer = app(DataNormalizer::class);

        $clientes = Cliente::pluck('id')->toArray();

        if (empty($clientes)) {
            return;
        }

        $marcas = ['Toyota', 'Nissan', 'Hyundai', 'Kia', 'Chevrolet', 'Honda', 'Mazda', 'Mitsubishi', 'Renault', 'Suzuki'];
        $colores = ['Rojo', 'Azul', 'Negro', 'Blanco', 'Gris', 'Plateado', 'Verde', 'Amarillo'];

        for ($i = 0; $i < 25; $i++) {
            $placa = $normalizer->normalizarPlaca($this->generarPlaca($faker));

            if (Automotor::where('placa', $placa)->exists()) {
                continue;
            }

            Automotor::create([
                'placa' => $placa,
                'cliente_id' => $faker->randomElement($clientes),
                'marca' => $normalizer->normalizarAlfanumerico($faker->randomElement($marcas)),
                'modelo' => $normalizer->normalizarAlfanumerico($faker->bothify('Modelo ?#')),
                'serie' => $normalizer->normalizarAlfanumerico($faker->bothify('SERIE###??')),
                'color' => $normalizer->normalizarNombre($faker->randomElement($colores)),
                'motor' => $normalizer->normalizarAlfanumerico($faker->bothify('MOTOR####')),
                'vin' => $normalizer->normalizarAlfanumerico($faker->bothify('VIN##########')),
            ]);
        }
    }

    /**
     * Generar placa con formato ABC123
     */
    private function generarPlaca($faker): string
    {
        $letras = strtoupper($faker->lexify('???'));
        $numeros = $faker->numerify('###');

        return $letras.$numeros;
    }
}
