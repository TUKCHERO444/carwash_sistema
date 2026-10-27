<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\LoginLockoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLockoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoginLockoutService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LoginLockoutService::class);
    }

    public function test_no_bloquea_antes_del_maximo_de_intentos(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i < LoginLockoutService::MAX_INTENTOS; $i++) {
            $this->service->registrarFallo($user, 'intento fallido');
            $user->refresh();

            $this->assertSame($i, $user->failed_attempts);
            $this->assertNull($user->locked_until);
            $this->assertFalse($this->service->estaBloqueado($user));
        }
    }

    public function test_bloquea_al_alcanzar_el_maximo_de_intentos(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < LoginLockoutService::MAX_INTENTOS; $i++) {
            $this->service->registrarFallo($user, 'intento fallido');
        }

        $user->refresh();

        $this->assertSame(LoginLockoutService::MAX_INTENTOS, $user->failed_attempts);
        $this->assertTrue($this->service->estaBloqueado($user));
        $this->assertTrue(
            $user->locked_until->between(
                now()->addMinutes(LoginLockoutService::MINUTOS_BLOQUEO_BASE - 1),
                now()->addMinutes(LoginLockoutService::MINUTOS_BLOQUEO_BASE + 1)
            )
        );
    }

    public function test_no_extiende_un_bloqueo_vigente(): void
    {
        $bloqueo = now()->startOfSecond()->addMinutes(10);

        $user = User::factory()->create([
            'failed_attempts' => LoginLockoutService::MAX_INTENTOS,
            'locked_until' => $bloqueo,
        ]);

        $this->service->registrarFallo($user, 'intento fallido');

        $user->refresh();

        $this->assertSame(LoginLockoutService::MAX_INTENTOS, $user->failed_attempts);
        $this->assertTrue($user->locked_until->equalTo($bloqueo));
    }

    public function test_el_bloqueo_es_progresivo(): void
    {
        $user = User::factory()->create([
            'failed_attempts' => 9,
            'locked_until' => now()->subMinute(),
        ]);

        $this->service->registrarFallo($user, 'intento fallido');

        $user->refresh();

        $this->assertSame(10, $user->failed_attempts);
        $this->assertTrue(
            $user->locked_until->between(
                now()->addMinutes(29),
                now()->addMinutes(31)
            )
        );
    }

    public function test_un_bloqueo_vencido_no_impide_el_acceso(): void
    {
        $user = User::factory()->create([
            'failed_attempts' => LoginLockoutService::MAX_INTENTOS,
            'locked_until' => now()->subMinute(),
        ]);

        $this->assertFalse($this->service->estaBloqueado($user));
    }

    public function test_el_exito_reinicia_contadores_y_registra_el_acceso(): void
    {
        $user = User::factory()->create([
            'failed_attempts' => LoginLockoutService::MAX_INTENTOS,
            'locked_until' => now()->subMinute(),
        ]);

        $this->service->registrarExito($user);

        $user->refresh();

        $this->assertSame(0, $user->failed_attempts);
        $this->assertNull($user->locked_until);
        $this->assertNotNull($user->last_login_at);
        $this->assertDatabaseHas('registros_auditoria', [
            'accion' => 'cuenta desbloqueada',
            'auditable_id' => $user->id,
        ]);
    }
}
