<?php

namespace Tests\Feature;

use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Toggle activo/inactivo y campos web del servicio.
 * Feature: servicios-web
 */
class ServicioWebToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'acceso-servicios', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    /**
     * Feature: servicios-web, Property 2: Toggle invierte el estado y devuelve JSON.
     */
    public function test_toggle_invierte_el_estado_y_devuelve_json(): void
    {
        $servicio = Servicio::factory()->create(['activo' => true]);

        $response = $this->actingAs($this->user)
            ->patchJson(route('servicios.toggleStatus', $servicio));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'activo' => false,
            ]);

        $this->assertDatabaseHas('servicios', [
            'id' => $servicio->id,
            'activo' => false,
        ]);

        $this->actingAs($this->user)
            ->patchJson(route('servicios.toggleStatus', $servicio))
            ->assertOk()
            ->assertJson(['activo' => true]);

        $this->assertDatabaseHas('servicios', [
            'id' => $servicio->id,
            'activo' => true,
        ]);
    }

    /**
     * Feature: servicios-web, Property 6: Toggle sin permiso retorna 403 sin cambios.
     */
    public function test_toggle_sin_permiso_retorna_403_sin_cambiar(): void
    {
        $sinPermiso = User::factory()->create();
        $servicio = Servicio::factory()->create(['activo' => true]);

        $this->actingAs($sinPermiso)
            ->patchJson(route('servicios.toggleStatus', $servicio))
            ->assertStatus(403);

        $this->assertDatabaseHas('servicios', [
            'id' => $servicio->id,
            'activo' => true,
        ]);
    }

    /**
     * Feature: servicios-web, Property 6: Toggle sin autenticación redirige a login.
     */
    public function test_toggle_sin_autenticacion_redirige_a_login(): void
    {
        $servicio = Servicio::factory()->create();

        $this->patch(route('servicios.toggleStatus', $servicio))
            ->assertRedirect(route('login'));
    }

    /**
     * Feature: servicios-web, Requisito 3: store persiste los campos web.
     */
    public function test_store_persiste_campos_web(): void
    {
        $this->actingAs($this->user)->post(route('servicios.store'), [
            'nombre' => 'Lavado premium',
            'descripcion' => 'Lavado exterior e interior',
            'precio' => 50.00,
            'activo' => 1,
            'orden' => 3,
            'icono' => 'shield',
        ])->assertRedirect(route('servicios.index'));

        $this->assertDatabaseHas('servicios', [
            'nombre' => 'Lavado premium',
            'activo' => true,
            'orden' => 3,
            'icono' => 'shield',
        ]);
    }

    /**
     * Feature: servicios-web, Requisito 3: Sin checkbox activo conserva el default publicado.
     */
    public function test_store_sin_activo_queda_publicado(): void
    {
        $this->actingAs($this->user)->post(route('servicios.store'), [
            'nombre' => 'Lavado básico',
            'precio' => 20.00,
        ])->assertRedirect(route('servicios.index'));

        $this->assertDatabaseHas('servicios', [
            'nombre' => 'Lavado básico',
            'activo' => true,
            'orden' => 0,
            'icono' => 'sparkles',
        ]);
    }

    /**
     * Feature: servicios-web, Property 4: icono inválido es rechazado sin persistir.
     */
    public function test_icono_invalido_es_rechazado(): void
    {
        $this->actingAs($this->user)
            ->post(route('servicios.store'), [
                'nombre' => 'Lavado premium',
                'precio' => 50.00,
                'icono' => 'dragon',
            ])
            ->assertSessionHasErrors('icono');

        $this->assertDatabaseCount('servicios', 0);
    }

    /**
     * Feature: servicios-web, Property 4: orden negativa es rechazada sin persistir.
     */
    public function test_orden_negativa_es_rechazada(): void
    {
        $this->actingAs($this->user)
            ->post(route('servicios.store'), [
                'nombre' => 'Lavado premium',
                'precio' => 50.00,
                'orden' => -1,
            ])
            ->assertSessionHasErrors('orden');

        $this->assertDatabaseCount('servicios', 0);
    }

    /**
     * Feature: servicios-web, Requisitos 2 y 7: index muestra badge y botón toggle.
     */
    public function test_index_muestra_badge_y_toggle(): void
    {
        $servicio = Servicio::factory()->create(['activo' => false]);

        $this->actingAs($this->user)
            ->get(route('servicios.index'))
            ->assertOk()
            ->assertSee('Inactivo')
            ->assertSee('data-toggle-status', false)
            ->assertSee('data-badge', false)
            ->assertSee(route('servicios.toggleStatus', $servicio), false);
    }
}
