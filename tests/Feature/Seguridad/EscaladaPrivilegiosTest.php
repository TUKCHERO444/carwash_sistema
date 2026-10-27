<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use App\Services\AdministratorProtectionService;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EscaladaPrivilegiosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearRol(RoleNames::ADMINISTRADOR, ['acceso-usuarios', 'acceso-roles']);
        $this->crearRol('GestorUsuarios', ['acceso-usuarios']);
        $this->crearRol('GestorRoles', ['acceso-roles']);
        $this->crearRol('Vendedor', ['acceso-ventas']);
        $this->crearRol('Cliente');
    }

    public function test_gestor_de_usuarios_no_puede_asignar_el_rol_administrador(): void
    {
        $gestor = $this->usuarioConRol('GestorUsuarios');

        $response = $this->actingAs($gestor)->post(route('users.store'), [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@example.com',
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
            'role' => RoleNames::ADMINISTRADOR,
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
    }

    public function test_gestor_de_usuarios_no_puede_asignar_un_rol_con_permisos_que_no_posee(): void
    {
        $gestor = $this->usuarioConRol('GestorUsuarios');

        $response = $this->actingAs($gestor)->post(route('users.store'), [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@example.com',
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
            'role' => 'Vendedor',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
    }

    public function test_gestor_de_usuarios_puede_crear_usuarios_con_roles_dentro_de_su_alcance(): void
    {
        $gestor = $this->usuarioConRol('GestorUsuarios');

        $response = $this->actingAs($gestor)->post(route('users.store'), [
            'name' => 'Nuevo Cliente',
            'email' => 'cliente@example.com',
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
            'role' => 'Cliente',
        ]);

        $response->assertRedirect(route('users.index'));

        $user = User::where('email', 'cliente@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('Cliente'));
    }

    public function test_gestor_de_usuarios_no_puede_gestionar_usuarios_con_mas_permisos(): void
    {
        $gestor = $this->usuarioConRol('GestorUsuarios');
        $target = $this->usuarioConRol('Vendedor');

        $this->actingAs($gestor)->get(route('users.edit', $target))->assertForbidden();
        $this->actingAs($gestor)->put(route('users.update', $target), $this->datosActualizacion($target, 'Cliente'))->assertForbidden();
        $this->actingAs($gestor)->patch(route('users.toggle', $target))->assertForbidden();
        $this->actingAs($gestor)->delete(route('users.destroy', $target))->assertForbidden();
    }

    public function test_gestor_de_usuarios_no_puede_gestionar_administradores(): void
    {
        $gestor = $this->usuarioConRol('GestorUsuarios');
        $admin = $this->usuarioConRol(RoleNames::ADMINISTRADOR);

        $this->actingAs($gestor)->get(route('users.edit', $admin))->assertForbidden();
        $this->actingAs($gestor)->delete(route('users.destroy', $admin))->assertForbidden();
        $this->actingAs($gestor)->patch(route('users.toggle', $admin))->assertForbidden();
    }

    public function test_administrador_puede_asignar_el_rol_administrador(): void
    {
        $admin = $this->usuarioConRol(RoleNames::ADMINISTRADOR);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Nuevo Admin',
            'email' => 'nuevo-admin@example.com',
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
            'role' => RoleNames::ADMINISTRADOR,
        ]);

        $response->assertRedirect(route('users.index'));

        $user = User::where('email', 'nuevo-admin@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole(RoleNames::ADMINISTRADOR));
    }

    public function test_usuario_no_puede_cambiar_su_propio_rol(): void
    {
        $admin = $this->usuarioConRol(RoleNames::ADMINISTRADOR);

        $response = $this->actingAs($admin)->put(
            route('users.update', $admin),
            $this->datosActualizacion($admin, 'Cliente')
        );

        $response->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->hasRole(RoleNames::ADMINISTRADOR));
    }

    public function test_rol_administrador_esta_protegido_contra_edicion_y_eliminacion(): void
    {
        $admin = $this->usuarioConRol(RoleNames::ADMINISTRADOR);
        $role = Role::where('name', RoleNames::ADMINISTRADOR)->firstOrFail();

        $this->actingAs($admin)->get(route('roles.edit', $role))->assertForbidden();

        $this->actingAs($admin)->put(route('roles.update', $role), [
            'name' => 'SuperAdmin',
            'permissions' => ['acceso-roles'],
        ])->assertForbidden();

        $this->actingAs($admin)->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['name' => RoleNames::ADMINISTRADOR]);
    }

    public function test_gestor_de_roles_no_puede_crear_roles_con_permisos_ajenos(): void
    {
        $gestor = $this->usuarioConRol('GestorRoles');

        $response = $this->actingAs($gestor)->post(route('roles.store'), [
            'name' => 'Escalada',
            'permissions' => ['acceso-ventas'],
        ]);

        $response->assertSessionHasErrors('permissions.0');
        $this->assertDatabaseMissing('roles', ['name' => 'Escalada']);
    }

    public function test_gestor_de_roles_no_puede_editar_roles_con_permisos_ajenos(): void
    {
        $gestor = $this->usuarioConRol('GestorRoles');
        $role = Role::where('name', 'Vendedor')->firstOrFail();

        $this->actingAs($gestor)->get(route('roles.edit', $role))->assertForbidden();

        $this->actingAs($gestor)->put(route('roles.update', $role), [
            'name' => 'Vendedor',
            'permissions' => ['acceso-roles'],
        ])->assertForbidden();
    }

    public function test_gestor_de_roles_no_puede_agregar_permisos_ajenos_a_su_propio_rol(): void
    {
        $gestor = $this->usuarioConRol('GestorRoles');
        $role = Role::where('name', 'GestorRoles')->firstOrFail();

        $response = $this->actingAs($gestor)->put(route('roles.update', $role), [
            'name' => 'GestorRoles',
            'permissions' => ['acceso-roles', 'acceso-ventas'],
        ]);

        $response->assertSessionHasErrors('permissions.1');
        $this->assertFalse($role->fresh()->hasPermissionTo('acceso-ventas'));
    }

    public function test_ultimo_administrador_se_considera_critico(): void
    {
        $service = app(AdministratorProtectionService::class);
        $admin = $this->usuarioConRol(RoleNames::ADMINISTRADOR);

        $this->assertTrue($service->esUltimoAdministrador($admin));
        $this->assertTrue($service->esAdministradorCritico($admin));

        $segundoAdmin = $this->usuarioConRol(RoleNames::ADMINISTRADOR);

        $this->assertFalse($service->esUltimoAdministrador($admin));
        $this->assertFalse($service->esAdministradorCritico($admin));

        $segundoAdmin->update(['activo' => false]);

        $this->assertFalse($service->esUltimoAdministrador($admin));
        $this->assertTrue($service->esUltimoAdministradorUtilizable($admin));
        $this->assertTrue($service->esAdministradorCritico($admin));
    }

    public function test_se_auditan_asignaciones_y_cambios_de_rol(): void
    {
        $admin = $this->usuarioConRol(RoleNames::ADMINISTRADOR);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Cliente Auditado',
            'email' => 'auditado@example.com',
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
            'role' => 'Cliente',
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'auditado@example.com')->firstOrFail();

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'usuarios',
            'accion' => 'asignar rol',
            'auditable_id' => $user->id,
        ]);

        $this->actingAs($admin)->put(
            route('users.update', $user),
            $this->datosActualizacion($user, 'Vendedor')
        )->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'usuarios',
            'accion' => 'cambiar rol',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_se_auditan_asignaciones_y_cambios_de_permisos(): void
    {
        $admin = $this->usuarioConRol(RoleNames::ADMINISTRADOR);

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'Auditado',
            'permissions' => ['acceso-roles'],
        ])->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'Auditado')->firstOrFail();

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'roles',
            'accion' => 'asignar permisos',
            'auditable_id' => $role->id,
        ]);

        $this->actingAs($admin)->put(route('roles.update', $role), [
            'name' => 'Auditado',
            'permissions' => ['acceso-roles', 'acceso-ventas'],
        ])->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'roles',
            'accion' => 'cambiar permisos',
            'auditable_id' => $role->id,
        ]);
    }

    private function crearRol(string $name, array $permissions = []): Role
    {
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role->syncPermissions($permissions);

        return $role;
    }

    private function usuarioConRol(string $roleName): User
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }

    private function datosActualizacion(User $user, string $role): array
    {
        return [
            'name' => 'Usuario Prueba',
            'email' => $user->email,
            'role' => $role,
        ];
    }
}
