<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DetalleServicio;
use App\Models\Ingreso;
use App\Models\Servicio;
use Faker\Factory as Faker;

class DetalleServicioSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        $servicioIds = Servicio::pluck('id')->toArray();

        $ingresos = Ingreso::all();

        foreach ($ingresos as $ingreso) {
            // Generar entre 1 y 5 servicios por ingreso
            $cantidadServicios = $faker->numberBetween(1, min(5, count($servicioIds)));

            // Seleccionar servicios únicos para este ingreso
            $serviciosSeleccionados = $faker->randomElements(
                $servicioIds,
                $cantidadServicios
            );

            foreach ($serviciosSeleccionados as $servicioId) {
                // Verificar que no exista ya la combinación (respeta unique constraint)
                $existe = DetalleServicio::where('ingreso_id', $ingreso->id)
                    ->where('servicio_id', $servicioId)
                    ->exists();

                if (!$existe) {
                    DetalleServicio::create([
                        'ingreso_id'  => $ingreso->id,
                        'servicio_id' => $servicioId,
                    ]);
                }
            }
        }
    }
}
