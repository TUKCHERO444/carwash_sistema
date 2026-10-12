<?php

namespace Database\Seeders;

use App\Models\MetodoPago;
use Illuminate\Database\Seeder;

class MetodoPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $metodos = [
            ['codigo' => 'efectivo', 'nombre' => 'Efectivo', 'activo' => true, 'es_efectivo' => true, 'orden' => 1],
            ['codigo' => 'yape', 'nombre' => 'Yape', 'activo' => true, 'es_efectivo' => false, 'orden' => 2],
            ['codigo' => 'izipay', 'nombre' => 'Izipay', 'activo' => true, 'es_efectivo' => false, 'orden' => 3],
        ];

        foreach ($metodos as $metodo) {
            MetodoPago::firstOrCreate(
                ['codigo' => $metodo['codigo']],
                ['nombre' => $metodo['nombre'], 'activo' => $metodo['activo'], 'es_efectivo' => $metodo['es_efectivo'], 'orden' => $metodo['orden']]
            );
        }

        // Un solo método con efectivo físico.
        if (! MetodoPago::where('es_efectivo', true)->exists()) {
            MetodoPago::where('codigo', 'efectivo')->update(['es_efectivo' => true]);
        }
    }
}
