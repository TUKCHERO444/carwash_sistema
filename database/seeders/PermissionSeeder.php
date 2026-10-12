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
            'acceso-asistencia',

            // Inventario y Servicios
            'acceso-inventario',
            'acceso-servicios',
            'acceso-contenido-web',
            'acceso-metodos-pago',

            // Operaciones Clientes
            'acceso-tipos-vehiculo',
            'acceso-clientes',
            'acceso-vehiculos',

            // Proveedores
            'acceso-proveedores',
            'acceso-ajustes',

            // Ventas y Operaciones
            'acceso-ventas',
            'acceso-compras',

            // Caja
            'acceso-caja',
            'historial-caja',

            // Zona pública de clientes (/cuenta/*): puerta de acceso,
            // NO es un permiso operativo. El rol Cliente solo lleva este.
            'acceso-cuenta',

            // Auditoría
            'acceso-auditoria',

            // Reportes
            'acceso-reportes',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
