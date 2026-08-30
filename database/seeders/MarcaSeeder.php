<?php

namespace Database\Seeders;

use App\Models\Marca;
use App\Services\DataNormalizer;
use Illuminate\Database\Seeder;

class MarcaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Crea marcas reales de repuestos automotrices.
     * Las descripciones siguen el estándar alfanumérico (letras, números y
     * espacios; sin símbolos) y se limitan a 100 caracteres.
     */
    public function run(): void
    {
        $normalizer = app(DataNormalizer::class);

        $marcasData = [
            ['nombre' => 'Motul', 'descripcion' => 'Fabricante francesa de aceites y lubricantes de alta performance'],
            ['nombre' => 'Castrol', 'descripcion' => 'Marca britanica de lubricantes para motores automotrices e industriales'],
            ['nombre' => 'Bosch', 'descripcion' => 'Fabricante aleman de componentes automotrices frenos y sistemas electricos'],
            ['nombre' => 'Monroe', 'descripcion' => 'Lider mundial en amortiguadores y componentes de suspension'],
            ['nombre' => 'Continental', 'descripcion' => 'Fabricante aleman de neumaticos y componentes automotrices'],
            ['nombre' => 'Michelin', 'descripcion' => 'Marca francesa de neumaticos y cubiertas para todo tipo de vehiculos'],
            ['nombre' => 'Brembo', 'descripcion' => 'Fabricante italiano de sistemas de frenado de alto rendimiento'],
            ['nombre' => 'Varta', 'descripcion' => 'Marca alemana de baterias automotrices e industriales'],
            ['nombre' => 'NGK', 'descripcion' => 'Fabricante japones de bujias y sensores automotrices'],
            ['nombre' => 'Gates', 'descripcion' => 'Marca estadounidense de correas y componentes de transmision'],
            ['nombre' => 'Sachs', 'descripcion' => 'Fabricante aleman de embragues y componentes de tren motriz'],
            ['nombre' => 'Fram', 'descripcion' => 'Marca estadounidense de filtros de aceite aire y combustible'],
            ['nombre' => 'ACDelco', 'descripcion' => 'Marca de repuestos GM componentes electricos y de motor'],
            ['nombre' => 'Denso', 'descripcion' => 'Fabricante japones de componentes automotrices y sistemas electricos'],
            ['nombre' => 'Mobil', 'descripcion' => 'Marca de lubricantes automotrices e industriales de ExxonMobil'],
        ];

        foreach ($marcasData as $data) {
            $data['nombre'] = $normalizer->normalizarNombre($data['nombre']);
            $data['descripcion'] = $normalizer->normalizarAlfanumerico($data['descripcion']);

            Marca::firstOrCreate(
                ['nombre' => $data['nombre']],
                ['descripcion' => $data['descripcion']]
            );
        }
    }
}
