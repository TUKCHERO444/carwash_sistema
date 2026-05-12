<?php

namespace Database\Seeders;

use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

class VehiculoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehiculos = [
            [
                'nombre' => 'Sedan Compacto',
                'descripcion' => 'Vehículo sedan de 4 puertas, ideal para ciudad. Motor 1.4L, bajo consumo de combustible.',
                'precio' => 15000.00,
            ],
            [
                'nombre' => 'SUV Mediana',
                'descripcion' => 'SUV de 5 pasajeros con tracción 4x4. Motor 2.0L turbo, apta para todo terreno.',
                'precio' => 28000.00,
            ],
            [
                'nombre' => 'Pickup Doble Cabina',
                'descripcion' => 'Camioneta pickup con capacidad de carga de 1 tonelada. Motor 2.5L diésel, robusta y versátil.',
                'precio' => 32000.00,
            ],
            [
                'nombre' => 'Hatchback',
                'descripcion' => 'Auto compacto de 5 puertas, económico y ágil. Motor 1.2L, perfecto para uso urbano.',
                'precio' => 12000.00,
            ],
            [
                'nombre' => 'Minivan',
                'descripcion' => 'Vehículo familiar de 7 pasajeros con amplio espacio interior. Motor 2.0L, cómodo y espacioso.',
                'precio' => 25000.00,
            ],
            [
                'nombre' => 'Sedan Ejecutivo',
                'descripcion' => 'Sedan de lujo con acabados premium y tecnología avanzada. Motor 3.0L V6, máximo confort.',
                'precio' => 45000.00,
            ],
            [
                'nombre' => 'SUV Compacta',
                'descripcion' => 'SUV urbana de 5 pasajeros, ágil y eficiente. Motor 1.6L, ideal para ciudad y carretera.',
                'precio' => 22000.00,
            ],
            [
                'nombre' => 'Coupe Deportivo',
                'descripcion' => 'Auto deportivo de 2 puertas con alto rendimiento. Motor 2.0L turbo, diseño aerodinámico.',
                'precio' => 50000.00,
            ],
            [
                'nombre' => 'Station Wagon',
                'descripcion' => 'Familiar con amplio espacio de carga y maletero. Motor 1.8L, versátil para familia y trabajo.',
                'precio' => 20000.00,
            ],
            [
                'nombre' => 'Pickup Simple',
                'descripcion' => 'Camioneta pickup de cabina simple, ideal para trabajo. Motor 2.2L diésel, resistente y económica.',
                'precio' => 18000.00,
            ],
            [
                'nombre' => 'Crossover',
                'descripcion' => 'Vehículo crossover urbano que combina comodidad y versatilidad. Motor 1.5L turbo, moderno y eficiente.',
                'precio' => 24000.00,
            ],
            [
                'nombre' => 'Van Comercial',
                'descripcion' => 'Furgoneta para transporte de carga y pasajeros. Motor 2.5L diésel, alta capacidad de carga.',
                'precio' => 30000.00,
            ],
            [
                'nombre' => 'Sedan Medio',
                'descripcion' => 'Sedan de tamaño medio, confortable y equilibrado. Motor 1.6L, buena relación calidad-precio.',
                'precio' => 18000.00,
            ],
            [
                'nombre' => 'SUV Grande',
                'descripcion' => 'SUV de 7 pasajeros con alto rendimiento y tracción integral. Motor 3.5L V6, potente y espacioso.',
                'precio' => 42000.00,
            ],
            [
                'nombre' => 'Convertible',
                'descripcion' => 'Auto descapotable de 2 puertas, elegante y deportivo. Motor 2.0L turbo, capota eléctrica.',
                'precio' => 48000.00,
            ],
        ];

        foreach ($vehiculos as $vehiculo) {
            Vehiculo::create($vehiculo);
        }
    }
}
