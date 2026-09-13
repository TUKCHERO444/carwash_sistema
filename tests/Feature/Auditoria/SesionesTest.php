<?php

namespace Tests\Feature\Auditoria;

use App\Models\RegistroAuditoria;
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

    public function test_login_fallido_no_genera_registro(): void
    {
        User::factory()->create([
            'email' => 'usuario@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->post('/login', [
            'email' => 'usuario@example.com',
            'password' => 'incorrecta',
        ]);

        $this->assertSame(0, RegistroAuditoria::where('modulo', 'sesiones')->count());
    }
}
