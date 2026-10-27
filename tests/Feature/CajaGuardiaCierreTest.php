<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CajaGuardiaCierreTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioCon(array $permisos): User
    {
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permisos);

        return $user;
    }

    private function cajaAbierta(User $user): Caja
    {
        return Caja::create([
            'user_id' => $user->id,
            'estado' => 'abierta',
            'monto_inicial' => 100,
            'fecha_apertura' => now(),
        ]);
    }

    public function test_layout_marca_caja_abierta_para_usuario_con_acceso(): void
    {
        $user = $this->usuarioCon(['acceso-caja']);
        $this->cajaAbierta($user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-caja-abierta="1"', false)
            ->assertSee('data-caja-url="'.route('caja.index').'"', false)
            ->assertSee('data-caja-guard="always"', false);
    }

    public function test_layout_marca_caja_cerrada_para_usuario_con_acceso(): void
    {
        $user = $this->usuarioCon(['acceso-caja']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-caja-abierta="0"', false);
    }

    public function test_layout_no_activa_guardia_para_usuario_sin_acceso_a_caja(): void
    {
        $admin = $this->usuarioCon(['acceso-caja']);
        $this->cajaAbierta($admin);

        $user = $this->usuarioCon([]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-caja-abierta="0"', false);
    }

    public function test_estado_de_caja_se_expone_en_otra_vista_del_panel(): void
    {
        $user = $this->usuarioCon(['acceso-caja']);
        $this->cajaAbierta($user);

        $this->actingAs($user)
            ->get(route('caja.index'))
            ->assertOk()
            ->assertSee('data-caja-abierta="1"', false);
    }
}
