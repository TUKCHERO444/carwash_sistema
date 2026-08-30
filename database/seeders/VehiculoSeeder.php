<?php

namespace Database\Seeders;

use App\Models\Vehiculo;
use App\Services\DataNormalizer;
use Illuminate\Database\Seeder;

class VehiculoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Las descripciones siguen el estándar alfanumérico (letras, números y
     * espacios; sin símbolos) definido por la validación de VehiculoController.
     */
    public function run(): void
    {
        $normalizer = app(DataNormalizer::class);

        $vehiculos = [
            [
                'nombre' => 'Sedan Compacto',
                'descripcion' => 'Vehículo sedan de 4 puertas ideal para la ciudad motor 1 4 litros de bajo consumo',
                'precio' => 15000.00,
            ],
            [
                'nombre' => 'SUV Mediana',
                'descripcion' => 'SUV de 5 pasajeros con tracción 4x4 motor 2 0 litros turbo apta para todo terreno',
                'precio' => 28000.00,
            ],
            [
                'nombre' => 'Pickup Doble Cabina',
                'descripcion' => 'Camioneta pickup con capacidad de carga de una tonelada motor 2 5 litros diesel robusta y versátil',
                'precio' => 32000.00,
            ],
            [
                'nombre' => 'Hatchback',
                'descripcion' => 'Auto compacto de 5 puertas económico y ágil motor 1 2 litros perfecto para uso urbano',
                'precio' => 12000.00,
            ],
            [
                'nombre' => 'Minivan',
                'descripcion' => 'Vehículo familiar de 7 pasajeros con amplio espacio interior motor 2 0 litros cómodo y espacioso',
                'precio' => 25000.00,
            ],
            [
                'nombre' => 'Sedan Ejecutivo',
                'descripcion' => 'Sedan de lujo con acabados premium y tecnología avanzada motor 3 0 litros V6 máximo confort',
                'precio' => 45000.00,
            ],
            [
                'nombre' => 'SUV Compacta',
                'descripcion' => 'SUV urbana de 5 pasajeros ágil y eficiente motor 1 6 litros ideal para ciudad y carretera',
                'precio' => 22000.00,
            ],
            [
                'nombre' => 'Coupe Deportivo',
                'descripcion' => 'Auto deportivo de 2 puertas con alto rendimiento motor 2 0 litros turbo diseño aerodinámico',
                'precio' => 50000.00,
            ],
            [
                'nombre' => 'Station Wagon',
                'descripcion' => 'Familiar con amplio espacio de carga y maletero motor 1 8 litros versátil para familia y trabajo',
                'precio' => 20000.00,
            ],
            [
                'nombre' => 'Pickup Simple',
                'descripcion' => 'Camioneta pickup de cabina simple ideal para trabajo motor 2 2 litros diesel resistente y económica',
                'precio' => 18000.00,
            ],
            [
                'nombre' => 'Crossover',
                'descripcion' => 'Vehículo crossover urbano que combina comodidad y versatilidad motor 1 5 litros turbo moderno y eficiente',
                'precio' => 24000.00,
            ],
            [
                'nombre' => 'Van Comercial',
                'descripcion' => 'Furgoneta para transporte de carga y pasajeros motor 2 5 litros diesel de alta capacidad',
                'precio' => 30000.00,
            ],
            [
                'nombre' => 'Sedan Medio',
                'descripcion' => 'Sedan de tamaño medio confortable y equilibrado motor 1 6 litros buena relación calidad precio',
                'precio' => 18000.00,
            ],
            [
                'nombre' => 'SUV Grande',
                'descripcion' => 'SUV de 7 pasajeros con alto rendimiento y tracción integral motor 3 5 litros V6 potente y espacioso',
                'precio' => 42000.00,
            ],
            [
                'nombre' => 'Convertible',
                'descripcion' => 'Auto descapotable de 2 puertas elegante y deportivo motor 2 0 litros turbo con capota eléctrica',
                'precio' => 48000.00,
            ],
        ];

        foreach ($vehiculos as $vehiculo) {
            $vehiculo['nombre'] = $normalizer->normalizarAlfanumerico($vehiculo['nombre']);
            $vehiculo['descripcion'] = $normalizer->normalizarAlfanumerico($vehiculo['descripcion']);

            Vehiculo::firstOrCreate(
                ['nombre' => $vehiculo['nombre']],
                ['descripcion' => $vehiculo['descripcion'], 'precio' => $vehiculo['precio']]
            );
        }
    }
}
