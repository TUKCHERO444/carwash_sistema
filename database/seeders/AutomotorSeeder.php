<?php

namespace Database\Seeders;

use App\Models\Automotor;
use App\Models\Cliente;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class AutomotorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Crea automotores vinculados a clientes existentes (1 cliente puede tener 1..N).
     */
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        $clientes = Cliente::pluck('id')->toArray();

        if (empty($clientes)) {
            return;
        }

        $marcas = ['Toyota', 'Nissan', 'Hyundai', 'Kia', 'Chevrolet', 'Honda', 'Mazda', 'Mitsubishi', 'Renault', 'Suzuki'];
        $colores = ['Rojo', 'Azul', 'Negro', 'Blanco', 'Gris', 'Plateado', 'Verde', 'Amarillo'];

        for ($i = 0; $i < 25; $i++) {
            Automotor::firstOrCreate(
                ['placa' => $this->generarPlaca($faker)],
                [
                    'cliente_id' => $faker->randomElement($clientes),
                    'marca' => $faker->randomElement($marcas),
                    'modelo' => $faker->bothify('Modelo ?#'),
                    'serie' => $faker->bothify('SERIE###??'),
                    'color' => $faker->randomElement($colores),
                    'motor' => $faker->bothify('MOTOR####'),
                    'vin' => $faker->bothify('VIN##########'),
                ]
            );
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
