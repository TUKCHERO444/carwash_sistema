<?php

namespace Database\Seeders;

use App\Models\User;
use App\Rules\PasswordSegura;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates roles and, when configured, the initial admin user idempotently.
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
            'registrar-egresos-caja',
            'acceso-clientes',
            'acceso-tipos-vehiculo',
            'acceso-vehiculos',
        ]);

        // El rol Cliente solo lleva el permiso puerta de la zona pública.
        // Nunca recibe permisos operativos (acceso-ventas, acceso-caja, ...).
        $clienteRole->syncPermissions(['acceso-cuenta']);

        $this->crearAdministradorInicial();
    }

    private function crearAdministradorInicial(): void
    {
        $entornoDemo = app()->environment(['local', 'testing']);
        $nombre = trim((string) config('seed.admin.name', 'Administrador'));
        $email = strtolower(trim((string) config('seed.admin.email', 'admin@sistema.com')));
        $password = config('seed.admin.password');
        $passwordExplicita = is_string($password) && $password !== '';

        if (! $passwordExplicita) {
            if (! $entornoDemo) {
                $this->command?->warn('AuthSeeder: no se creó el administrador inicial. Define ADMIN_EMAIL y ADMIN_PASSWORD con una contraseña fuerte.');

                return;
            }

            $password = 'password';
        }

        if ($passwordExplicita || ! $entornoDemo) {
            $validator = Validator::make(
                ['name' => $nombre, 'email' => $email, 'password' => $password],
                [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'string', 'email', 'max:255'],
                    'password' => ['required', 'string', new PasswordSegura(null, $nombre, $email)],
                ]
            );

            if ($validator->fails()) {
                throw new RuntimeException('Credenciales iniciales inválidas: '.$validator->errors()->first());
            }
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $nombre,
                'password' => $password,
                'activo' => true,
                'status' => 'ACTIVE',
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles(['Administrador']);
    }
}
