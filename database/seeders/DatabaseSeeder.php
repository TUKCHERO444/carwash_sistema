<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AuthSeeder::class,
            ClienteSeeder::class,
            VehiculoSeeder::class,
            TrabajadorSeeder::class,
            CategoriaSeeder::class,
            ProductoSeeder::class,
            ServicioSeeder::class,
            CambioAceiteSeeder::class,
            CambioProductoSeeder::class,
            IngresoSeeder::class,
            IngresoTrabajadorSeeder::class,
            DetalleServicioSeeder::class,
            VentaSeeder::class,
            DetalleVentaSeeder::class,
        ]);
    }
}
