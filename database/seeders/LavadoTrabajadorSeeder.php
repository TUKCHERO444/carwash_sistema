<?php

namespace Database\Seeders;

use App\Models\Lavado;
use App\Models\LavadoTrabajador;
use App\Models\Trabajador;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class LavadoTrabajadorSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        // Solo trabajadores activos
        $trabajadoresActivos = Trabajador::where('estado', true)->pluck('id')->toArray();

        $lavados = Lavado::all();

        foreach ($lavados as $lavado) {
            // Generar entre 1 y 3 trabajadores por lavado
            $cantidadTrabajadores = $faker->numberBetween(1, min(3, count($trabajadoresActivos)));

            // Seleccionar trabajadores únicos para este lavado
            $trabajadoresSeleccionados = $faker->randomElements(
                $trabajadoresActivos,
                $cantidadTrabajadores
            );

            foreach ($trabajadoresSeleccionados as $trabajadorId) {
                // Verificar que no exista ya la combinación (respeta unique constraint)
                $existe = LavadoTrabajador::where('lavado_id', $lavado->id)
                    ->where('trabajador_id', $trabajadorId)
                    ->exists();

                if (! $existe) {
                    LavadoTrabajador::create([
                        'lavado_id' => $lavado->id,
                        'trabajador_id' => $trabajadorId,
                    ]);
                }
            }
        }
    }
}
