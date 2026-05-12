<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
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
        // Create roles if they don't exist (idempotent)
        Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Asistente', 'guard_name' => 'web']);

        // Create admin user if it doesn't exist (idempotent)
        $admin = User::firstOrCreate(
            ['email' => 'admin@sistema.com'],
            [
                'name'     => 'Administrador',
                'password' => bcrypt('password'),
            ]
        );

        // Assign the Administrador role (syncRoles is idempotent)
        $admin->syncRoles(['Administrador']);
    }
}
