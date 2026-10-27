<?php

namespace Tests\Feature\Cuenta;

use App\Models\OneTimeToken;
use App\Models\User;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Registro público + panel de bienvenida (Req. 1 y 10).
 */
class CuentaRegistroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        Mail::fake();
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'password' => 'secreta123',
            'password_confirmation' => 'secreta123',
            'dni' => '12345678',
            'telefono' => '987654321',
        ], $extra);
    }

    public function test_registro_muestra_formulario(): void
    {
        $this->get(route('cuenta.registro'))
            ->assertStatus(200)
            ->assertViewIs('cuenta.registro')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('Crear mi cuenta');
    }

    public function test_registro_crea_cuenta_pendiente_vinculo_y_token(): void
    {
        $this->post(route('cuenta.registro'), $this->payload())
            ->assertRedirect(route('cuenta.login'));

        $user = User::where('email', 'juan@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('PENDING_EMAIL_VERIFICATION', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('Cliente'));
        $this->assertFalse($user->hasPermissionTo('acceso-ventas'));
        $this->assertDatabaseHas('registros_auditoria', [
            'auditable_id' => $user->id,
            'accion' => 'asignar rol',
            'modulo' => 'cuenta',
        ]);

        $this->assertDatabaseHas('clientes', ['dni' => '12345678', 'telefono' => '987654321']);
        $this->assertDatabaseHas('customer_user_links', [
            'user_id' => $user->id,
            'cliente_id' => $user->customerLink->cliente_id,
            'estado' => 'ACTIVO',
        ]);

        $token = OneTimeToken::where('user_id', $user->id)
            ->where('purpose', OneTimeToken::PURPOSE_VERIFICACION)
            ->first();
        $this->assertNotNull($token);
    }

    public function test_registro_normaliza_email_a_minusculas(): void
    {
        $this->post(route('cuenta.registro'), $this->payload(['email' => '  JUAN@Example.COM ']));

        $this->assertNotNull(User::where('email', 'juan@example.com')->first());
    }

    public function test_registro_rechaza_email_duplicado(): void
    {
        User::factory()->create(['email' => 'juan@example.com']);

        $this->post(route('cuenta.registro'), $this->payload())
            ->assertSessionHasErrors('email');
    }

    public function test_registro_rechaza_dni_invalido(): void
    {
        $this->post(route('cuenta.registro'), $this->payload(['dni' => '123']))
            ->assertSessionHasErrors('dni');

        $this->assertNull(User::where('email', 'juan@example.com')->first());
    }

    public function test_cuenta_pendiente_no_puede_ver_panel(): void
    {
        $this->post(route('cuenta.registro'), $this->payload());

        // Sin verificar no hay sesión: guest redirige al login de cuenta.
        $this->get(route('cuenta.panel'))->assertRedirect(route('cuenta.login'));
    }

    public function test_panel_muestra_bienvenida_al_cliente_verificado(): void
    {
        $this->post(route('cuenta.registro'), $this->payload());
        $user = User::where('email', 'juan@example.com')->first();
        $user->update(['email_verified_at' => now(), 'status' => 'ACTIVE']);

        $this->actingAs($user)->get(route('cuenta.panel'))
            ->assertStatus(200)
            ->assertViewIs('cuenta.panel')
            ->assertSee('¡Bienvenido, Juan Pérez!');
    }

    public function test_header_ingresar_apunta_al_registro_publico(): void
    {
        // El visitante entra por INGRESAR al registro de clientes,
        // nunca al login operativo (/login → /dashboard).
        $this->get(route('inicio'))
            ->assertStatus(200)
            ->assertSee(route('cuenta.registro'), false);
    }

    public function test_operador_sin_rol_cliente_no_ve_panel(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('Administrador', 'web'));

        $this->actingAs($admin)->get(route('cuenta.panel'))
            ->assertRedirect(route('cuenta.login'));
    }
}
