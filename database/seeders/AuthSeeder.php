<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

use Spatie\Permission\Models\Permission;

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

        // Assign ALL permissions to Administrador
        $adminRole->syncPermissions(Permission::all());

        // Assign specific permissions to Vendedor
        $vendedorRole->syncPermissions([
            'acceso-ventas',
            'acceso-caja',
            'acceso-clientes',
            'acceso-vehiculos'
        ]);

        // Create admin user if it doesn't exist (idempotent)
        $admin = User::firstOrCreate(
            ['email' => 'admin@sistema.com'],
            [
                'name'     => 'Administrador',
                'password' => bcrypt('password'),
                'activo'   => true,
            ]
        );

        // Assign the Administrador role (syncRoles is idempotent)
        $admin->syncRoles(['Administrador']);
    }
}
