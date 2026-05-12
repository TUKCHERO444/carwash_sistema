<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ingreso;
use App\Models\IngresoTrabajador;
use App\Models\Trabajador;
use Faker\Factory as Faker;

class IngresoTrabajadorSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        // Solo trabajadores activos
        $trabajadoresActivos = Trabajador::where('estado', true)->pluck('id')->toArray();

        $ingresos = Ingreso::all();

        foreach ($ingresos as $ingreso) {
            // Generar entre 1 y 3 trabajadores por ingreso
            $cantidadTrabajadores = $faker->numberBetween(1, min(3, count($trabajadoresActivos)));

            // Seleccionar trabajadores únicos para este ingreso
            $trabajadoresSeleccionados = $faker->randomElements(
                $trabajadoresActivos,
                $cantidadTrabajadores
            );

            foreach ($trabajadoresSeleccionados as $trabajadorId) {
                // Verificar que no exista ya la combinación (respeta unique constraint)
                $existe = IngresoTrabajador::where('ingreso_id', $ingreso->id)
                    ->where('trabajador_id', $trabajadorId)
                    ->exists();

                if (!$existe) {
                    IngresoTrabajador::create([
                        'ingreso_id'    => $ingreso->id,
                        'trabajador_id' => $trabajadorId,
                    ]);
                }
            }
        }
    }
}
