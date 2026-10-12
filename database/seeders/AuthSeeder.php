<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates roles and the initial admin user idempotently using firstOrCreate.
     */
    public function run(): void
    {
        // Execute permissions seeder first
        $this->call(PermissionSeeder::class);

        // Create roles if they don't exist (idempotent)
        $adminRole = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $vendedorRole = Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']);
        $clienteRole = Role::firstOrCreate(['name' => 'Cliente', 'guard_name' => 'web']);

        // Assign ALL permissions to Administrador
        $adminRole->syncPermissions(Permission::all());

        // Assign specific permissions to Vendedor
        $vendedorRole->syncPermissions([
            'acceso-ventas',
            'acceso-caja',
            'acceso-clientes',
            'acceso-tipos-vehiculo',
            'acceso-vehiculos',
        ]);

        // El rol Cliente solo lleva el permiso puerta de la zona pública.
        // Nunca recibe permisos operativos (acceso-ventas, acceso-caja, ...).
        $clienteRole->syncPermissions(['acceso-cuenta']);

        // Create admin user if it doesn't exist (idempotent)
        $admin = User::firstOrCreate(
            ['email' => 'admin@sistema.com'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('password'),
                'activo' => true,
            ]
        );

        // Assign the Administrador role (syncRoles is idempotent)
        $admin->syncRoles(['Administrador']);
    }
}
