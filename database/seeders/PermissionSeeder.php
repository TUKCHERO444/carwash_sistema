<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Usuarios y Roles
            'acceso-usuarios',
            'acceso-roles',

            // Personal
            'acceso-trabajadores',

            // Inventario y Servicios
            'acceso-inventario',
            'acceso-servicios',

            // Operaciones Clientes
            'acceso-vehiculos',
            'acceso-clientes',

            // Ventas y Operaciones
            'acceso-ventas',

            // Caja
            'acceso-caja',
            'historial-caja',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
