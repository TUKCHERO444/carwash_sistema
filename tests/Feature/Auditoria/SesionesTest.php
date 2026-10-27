<?php

namespace Tests\Feature\Auditoria;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SesionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_registra_inicio_de_sesion(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->post('/login', [
            'email' => 'usuario@example.com',
            'password' => 'password123',
        ])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'sesiones',
            'accion' => 'inicio de sesión',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_logout_registra_cierre_de_sesion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'sesiones',
            'accion' => 'cierre de sesión',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_login_fallido_genera_registro(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->post('/login', [
            'email' => 'usuario@example.com',
            'password' => 'incorrecta',
        ]);

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'sesiones',
            'accion' => 'intento fallido panel',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_eventos_de_login_guardan_ip_y_user_agent(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('User-Agent', 'Prueba-Seguridad/1.0')
            ->post('/login', [
                'email' => 'usuario@example.com',
                'password' => 'incorrecta',
            ]);

        $this->assertDatabaseHas('registros_auditoria', [
            'accion' => 'intento fallido panel',
            'auditable_id' => $user->id,
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Prueba-Seguridad/1.0',
        ]);
    }
}
