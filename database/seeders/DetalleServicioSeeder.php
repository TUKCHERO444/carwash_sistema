<?php

namespace Database\Seeders;

use App\Models\DetalleServicio;
use App\Models\Lavado;
use App\Models\Servicio;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class DetalleServicioSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        $servicioIds = Servicio::pluck('id')->toArray();

        $lavados = Lavado::all();

        foreach ($lavados as $lavado) {
            // Generar entre 1 y 5 servicios por lavado
            $cantidadServicios = $faker->numberBetween(1, min(5, count($servicioIds)));

            // Seleccionar servicios únicos para este lavado
            $serviciosSeleccionados = $faker->randomElements(
                $servicioIds,
                $cantidadServicios
            );

            foreach ($serviciosSeleccionados as $servicioId) {
                // Verificar que no exista ya la combinación (respeta unique constraint)
                $existe = DetalleServicio::where('lavado_id', $lavado->id)
                    ->where('servicio_id', $servicioId)
                    ->exists();

                if (! $existe) {
                    DetalleServicio::create([
                        'lavado_id' => $lavado->id,
                        'servicio_id' => $servicioId,
                    ]);
                }
            }
        }
    }
}
