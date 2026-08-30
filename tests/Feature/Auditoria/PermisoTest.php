<?php

namespace Tests\Feature\Auditoria;

use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermisoTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_sin_autenticar_es_redirigido_a_login(): void
    {
        $this->get(route('auditoria.acciones.index'))->assertRedirect('/');
    }

    public function test_usuario_sin_permiso_es_redirigido_al_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('auditoria.acciones.index'))->assertRedirect(route('dashboard'));
    }

    public function test_usuario_con_permiso_ve_listado(): void
    {
        Permission::firstOrCreate(['name' => 'acceso-auditoria', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo('acceso-auditoria');

        $this->actingAs($user)->get(route('auditoria.acciones.index'))->assertOk();
    }

    public function test_show_sin_permiso_es_redirigido_al_dashboard(): void
    {
        $user = User::factory()->create();

        $registro = RegistroAuditoria::create([
            'modulo' => 'productos',
            'accion' => 'crear',
            'auditable_type' => Producto::class,
            'auditable_id' => 1,
            'usuario_id' => 1,
            'fecha_movimiento' => now(),
        ]);

        $this->actingAs($user)->get(route('auditoria.acciones.show', $registro))
            ->assertRedirect(route('dashboard'));
    }
}
