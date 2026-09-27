<?php

namespace Database\Seeders;

use App\Models\Proveedor;
use App\Services\DataNormalizer;
use Illuminate\Database\Seeder;

class ProveedorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Crea proveedores ficticios con RUC válido de 11 dígitos.
     * Es idempotente: usa firstOrCreate por ruc.
     */
    public function run(): void
    {
        $normalizer = app(DataNormalizer::class);

        $proveedoresData = [
            ['ruc' => '20512345678', 'razon_social' => 'Distribuidora Andina de Lubricantes S.A.C.', 'direccion' => 'Av. Republica de Panama 3721, Surco', 'estado_tributario' => 'Activo', 'condicion' => 'Contribuyente'],
            ['ruc' => '20598765432', 'razon_social' => 'Importaciones Motor Peru S.R.L.', 'direccion' => 'Jr. Union 1055, Cercado de Lima', 'estado_tributario' => 'Activo', 'condicion' => 'Nuevo Contributor'],
            ['ruc' => '10456789123', 'razon_social' => 'Corporacion Automotriz del Sur E.I.R.L.', 'direccion' => 'Calle Bolognesi 245, Arequipa', 'estado_tributario' => 'Activo', 'condicion' => 'Contribuyente'],
            ['ruc' => '20678945213', 'razon_social' => 'Suministros y Repuestos El Motor S.A.C.', 'direccion' => 'Av. Venezuela 1290, San Miguel', 'estado_tributario' => 'Activo', 'condicion' => 'Contribuyente'],
            ['ruc' => '20345216789', 'razon_social' => 'Lubricantes Valle Grande S.A.C.', 'direccion' => 'Jr. Los Frutales 480, Trujillo', 'estado_tributario' => 'No Activo', 'condicion' => 'Contribuyente'],
            ['ruc' => '10551234678', 'razon_social' => 'Quimica Automotriz Andina S.A.C.', 'direccion' => 'Av. Tumbes 320, Chiclayo', 'estado_tributario' => 'Activo', 'condicion' => 'Nuevo Contributor'],
        ];

        foreach ($proveedoresData as $data) {
            $ruc = $normalizer->normalizarRuc($data['ruc']);

            Proveedor::firstOrCreate(
                ['ruc' => $ruc],
                [
                    'razon_social' => $data['razon_social'],
                    'direccion' => $data['direccion'],
                    'estado_tributario' => $data['estado_tributario'],
                    'condicion' => $data['condicion'],
                    'estado' => 1,
                ]
            );
        }
    }
}
