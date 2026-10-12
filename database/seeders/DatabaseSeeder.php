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
            MetodoPagoSeeder::class,
            ClienteSeeder::class,
            VehiculoSeeder::class,
            TipoVehiculoSeeder::class,
            TrabajadorSeeder::class,
            MarcaSeeder::class,
            ProveedorSeeder::class,
            // CategoriaSeeder crea las 8 categorías y DEBE ir antes de
            // ProductoSeeder: productos.categoria_id es NOT NULL, así que el
            // alta necesita una categoría válida. La asignación definitiva por
            // palabras clave la hace ProductoCategoriaSeeder, que sí corre
            // después (necesita los productos ya cargados).
            CategoriaSeeder::class,
            ProductoSeeder::class,
            ProductoCategoriaSeeder::class,
            CompraSeeder::class,
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
