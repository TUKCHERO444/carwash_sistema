<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\LoginLockoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_bloquea_la_cuenta_tras_cinco_fallos(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'activo' => true,
        ]);

        $response = null;

        for ($i = 0; $i < LoginLockoutService::MAX_INTENTOS; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => "10.80.1.{$i}"])
                ->post('/login', [
                    'email' => 'admin@example.com',
                    'password' => 'incorrecta',
                ])
                ->assertSessionHasErrors('email');
        }

        $response->assertSessionHas('bloqueo_hasta');

        $user->refresh();

        $this->assertSame(LoginLockoutService::MAX_INTENTOS, $user->failed_attempts);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
        $this->assertGuest();
    }

    public function test_login_rechaza_la_clave_correcta_mientras_la_cuenta_esta_bloqueada(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'activo' => true,
            'failed_attempts' => LoginLockoutService::MAX_INTENTOS,
            'locked_until' => now()->addMinutes(15),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.80.2.1'])
            ->post('/login', [
                'email' => 'admin@example.com',
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email')
            ->assertSessionHas('bloqueo_hasta');

        $this->get('/login')
            ->assertOk()
            ->assertSee('Demasiados intentos fallidos')
            ->assertSee('Volver al inicio de sesión');

        $this->assertGuest();
        $this->assertSame(LoginLockoutService::MAX_INTENTOS, $user->fresh()->failed_attempts);
    }

    public function test_login_permite_entrar_cuando_el_bloqueo_expiro_y_reinicia_contadores(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'activo' => true,
            'failed_attempts' => LoginLockoutService::MAX_INTENTOS,
            'locked_until' => now()->subMinute(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.80.3.1'])
            ->post('/login', [
                'email' => 'admin@example.com',
                'password' => 'password',
            ])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertSame(0, $user->failed_attempts);
        $this->assertNull($user->locked_until);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_login_reinicia_contadores_tras_un_acceso_exitoso(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'activo' => true,
            'failed_attempts' => 3,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.80.4.1'])
            ->post('/login', [
                'email' => 'admin@example.com',
                'password' => 'password',
            ])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertSame(0, $user->failed_attempts);
        $this->assertNull($user->locked_until);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_login_normaliza_el_email_antes_de_autenticar(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'activo' => true,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.80.5.1'])
            ->post('/login', [
                'email' => '  ADMIN@EXAMPLE.COM ',
                'password' => 'password',
            ])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rechaza_cuentas_inactivas_o_suspendidas(): void
    {
        User::factory()->create([
            'email' => 'inactivo@example.com',
            'password' => bcrypt('password'),
            'activo' => false,
        ]);

        User::factory()->create([
            'email' => 'suspendido@example.com',
            'password' => bcrypt('password'),
            'activo' => true,
            'status' => 'SUSPENDED',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.80.6.1'])
            ->post('/login', [
                'email' => 'inactivo@example.com',
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->withServerVariables(['REMOTE_ADDR' => '10.80.6.2'])
            ->post('/login', [
                'email' => 'suspendido@example.com',
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_limita_los_intentos_por_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.80.7.1'])
                ->post('/login', [
                    'email' => 'nadie@example.com',
                    'password' => 'incorrecta',
                ])
                ->assertSessionHasErrors('email');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.80.7.1'])
            ->post('/login', [
                'email' => 'nadie@example.com',
                'password' => 'incorrecta',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertSee('Demasiados intentos')
            ->assertSee('Volver al inicio de sesión')
            ->assertSee('data-retry-countdown', false);
    }

    public function test_login_audita_intentos_fallidos_y_bloqueos(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'activo' => true,
        ]);

        for ($i = 0; $i < LoginLockoutService::MAX_INTENTOS; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.80.8.{$i}"])
                ->post('/login', [
                    'email' => 'admin@example.com',
                    'password' => 'incorrecta',
                ]);
        }

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'sesiones',
            'accion' => 'intento fallido panel',
        ]);

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'sesiones',
            'accion' => 'cuenta bloqueada',
        ]);
    }
}
