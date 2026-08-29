<?php

namespace Database\Seeders;

use App\Models\Marca;
use Illuminate\Database\Seeder;

class MarcaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Crea marcas reales de repuestos automotrices.
     */
    public function run(): void
    {
        $marcasData = [
            ['nombre' => 'Motul',          'descripcion' => 'Fabricante francesa de aceites y lubricantes de alta performance'],
            ['nombre' => 'Castrol',        'descripcion' => 'Marca británica de lubricantes para motores automotrices e industriales'],
            ['nombre' => 'Bosch',          'descripcion' => 'Manufacturer alemán de componentes automotrices, frenos y电气'],
            ['nombre' => 'Monroe',         'descripcion' => 'Líder mundial en amortiguadores y componentes de suspensión'],
            ['nombre' => 'Continental',    'descripcion' => 'Fabricante alemán de neumáticos y componentes automotrices'],
            ['nombre' => 'Michelin',       'descripcion' => 'Marca francesa de neumáticos y cubiertas para todo tipo de vehículos'],
            ['nombre' => 'Brembo',         'descripcion' => 'Fabricante italiano de sistemas de frenado de alto rendimiento'],
            ['nombre' => 'Varta',          'descripcion' => 'Marca alemana de baterías automotrices e industriales'],
            ['nombre' => 'NGK',            'descripcion' => 'Fabricante japonés de bujías y sensores automotrices'],
            ['nombre' => 'Gates',          'descripcion' => 'Marca estadounidense de correas y componentes de transmisión'],
            ['nombre' => 'Sachs',          'descripcion' => 'Fabricante alemán de embragues y componentes de tren motriz'],
            ['nombre' => 'Fram',           'descripcion' => 'Marca estadounidense de filtros de aceite, aire y combustible'],
            ['nombre' => 'ACDelco',        'descripcion' => 'Marca de repuestos GM, componentes eléctricos y de motor'],
            ['nombre' => 'Denso',          'descripcion' => 'Fabricante japonés de componentes automotrices y sistemas eléctricos'],
            ['nombre' => 'Mobil',          'descripcion' => 'Marca de lubricantes automotrices e industriales de ExxonMobil'],
        ];

        foreach ($marcasData as $data) {
            Marca::firstOrCreate(
                ['nombre' => $data['nombre']],
                ['descripcion' => $data['descripcion']]
            );
        }
    }
}
