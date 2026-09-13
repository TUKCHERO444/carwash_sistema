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
            AutomotorSeeder::class,
            VehiculoSeeder::class,
            TrabajadorSeeder::class,
            MarcaSeeder::class,
            ProductoSeeder::class,
            // CategoriaSeeder DEBE ir después de ProductoSeeder: asigna a cada
            // producto su categoría por palabras clave del nombre. Si se ejecuta
            // antes, no encuentra productos y la web pública queda sin
            // categorías (dropdown vacío y mosaico sin tiles).
            CategoriaSeeder::class,
            ServicioSeeder::class,
            CambioAceiteSeeder::class,
            CambioProductoSeeder::class,
            LavadoSeeder::class,
            LavadoTrabajadorSeeder::class,
            DetalleServicioSeeder::class,
            VentaSeeder::class,
            DetalleVentaSeeder::class,
            KardexSeeder::class,
        ]);
    }
}
