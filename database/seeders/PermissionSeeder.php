<?php

namespace Database\Seeders;

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
            'acceso-automotores',

            // Ventas y Operaciones
            'acceso-ventas',

            // Caja
            'acceso-caja',
            'historial-caja',

            // Auditoría
            'acceso-auditoria',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
