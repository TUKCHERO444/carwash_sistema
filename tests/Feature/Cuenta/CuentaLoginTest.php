<?php

namespace Tests\Feature\Cuenta;

use App\Models\User;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Login/logout público + separación con el panel operativo (Req. 3, 4 y 6).
 */
class CuentaLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
    }

    private function clienteVerificado(array $extra = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'status' => 'ACTIVE',
            'activo' => true,
        ], $extra));
        $user->assignRole('Cliente');

        return $user;
    }

    public function test_login_muestra_formulario_y_autenticado_va_al_panel(): void
    {
        $this->get(route('cuenta.login'))
            ->assertStatus(200)
            ->assertViewIs('cuenta.login');

        $this->actingAs($this->clienteVerificado())
            ->get(route('cuenta.login'))
            ->assertRedirect(route('cuenta.panel'));
    }

    public function test_login_valido_inicia_sesion_y_va_al_panel(): void
    {
        $this->clienteVerificado(['email' => 'cli@example.com']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.0.1'])
            ->post(route('cuenta.login'), ['email' => 'cli@example.com', 'password' => 'password'])
            ->assertRedirect(route('cuenta.panel'));

        $this->assertAuthenticated();
    }

    public function test_login_invalido_responde_generico(): void
    {
        $this->clienteVerificado(['email' => 'cli@example.com']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.0.2'])
            ->post(route('cuenta.login'), ['email' => 'cli@example.com', 'password' => 'otra-clave'])
            ->assertSessionHasErrors('email', 'Las credenciales no son válidas o la cuenta no está activa.');

        $this->assertGuest();
    }

    public function test_login_inexistente_responde_igual_que_invalido(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.10.0.3'])
            ->post(route('cuenta.login'), ['email' => 'nadie@example.com', 'password' => 'x'])
            ->assertSessionHasErrors('email', 'Las credenciales no son válidas o la cuenta no está activa.');

        $this->assertGuest();
    }

    public function test_login_bloquea_tras_cinco_fallos(): void
    {
        $user = $this->clienteVerificado(['email' => 'cli@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.10.0.4'])
                ->post(route('cuenta.login'), ['email' => 'cli@example.com', 'password' => 'mal']);
        }

        $user->refresh();
        $this->assertNotNull($user->locked_until);

        // Aun con la clave correcta, bloqueada responde genérico (otra IP
        // para no chocar con el throttle:5,1 de la ráfaga anterior).
        $this->withServerVariables(['REMOTE_ADDR' => '10.10.0.5'])
            ->post(route('cuenta.login'), ['email' => 'cli@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email')
            ->assertSessionHas('bloqueo_hasta');

        $this->get(route('cuenta.login'))
            ->assertOk()
            ->assertSee('Demasiados intentos fallidos')
            ->assertSee('Volver al inicio de sesión');

        $this->assertGuest();
    }

    public function test_logout_cierra_sesion_y_va_al_inicio(): void
    {
        $this->actingAs($this->clienteVerificado())
            ->post(route('cuenta.logout'))
            ->assertRedirect(route('inicio'));

        $this->assertGuest();
    }

    public function test_cliente_va_a_su_panel_en_lugar_del_dashboard(): void
    {
        $this->actingAs($this->clienteVerificado())
            ->get(route('dashboard'))
            ->assertRedirect(route('cuenta.panel'))
            ->assertSessionHas('info');
    }

    public function test_cliente_en_login_operativo_va_a_su_panel(): void
    {
        $this->actingAs($this->clienteVerificado())
            ->get(route('login'))
            ->assertRedirect(route('cuenta.panel'));
    }

    public function test_operador_sigue_entrando_al_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('Administrador', 'web'));

        $this->actingAs($admin)->get(route('dashboard'))->assertStatus(200);
    }
}
