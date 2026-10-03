<?php

namespace Tests\Feature\Cuenta;

use App\Mail\CuentaGoogleMail;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Services\GoogleOidcException;
use App\Services\GoogleOidcService;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Login/registro con Google OIDC (Req. 8). Protocolo mockeado;
 * la cripto real se prueba en GoogleOidcServiceTest + Property 9.
 */
class CuentaGoogleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        Mail::fake();
    }

    private function cliente(array $extra = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'status' => 'ACTIVE',
            'activo' => true,
        ], $extra));
        $user->assignRole('Cliente');

        return $user;
    }

    private function mockOidc(callable $configuracion): MockInterface
    {
        return $this->mock(GoogleOidcService::class, $configuracion);
    }

    public function test_redirect_sin_configurar_va_al_login(): void
    {
        $this->mockOidc(fn (MockInterface $m) => $m->shouldReceive('configurado')->andReturn(false));

        $this->get(route('cuenta.google.redirect'))
            ->assertRedirect(route('cuenta.login'))
            ->assertSessionHasErrors('email');
    }

    public function test_redirect_configurado_va_a_google_con_pkce(): void
    {
        $this->mockOidc(function (MockInterface $m) {
            $m->shouldReceive('configurado')->andReturn(true);
            $m->shouldReceive('iniciarIntento')->andReturn(
                'https://accounts.google.com/o/oauth2/v2/auth?client_id=x&code_challenge=abc&code_challenge_method=S256&state=est&nonce=non&scope=openid+email+profile'
            );
        });

        $respuesta = $this->get(route('cuenta.google.redirect'));

        $respuesta->assertRedirect('https://accounts.google.com/o/oauth2/v2/auth?client_id=x&code_challenge=abc&code_challenge_method=S256&state=est&nonce=non&scope=openid+email+profile');
    }

    public function test_boton_google_visible_solo_si_configurado(): void
    {
        config()->set('services.google.client_id', null);
        $this->get(route('cuenta.login'))->assertDontSee('Continuar con Google');

        config()->set('services.google.client_id', 'test-id');
        $this->get(route('cuenta.login'))->assertSee('Continuar con Google');
    }

    public function test_callback_state_invalido_falla_generico(): void
    {
        $this->mockOidc(fn (MockInterface $m) => $m->shouldReceive('consumirIntento')->andReturn(null));

        $this->withServerVariables(['REMOTE_ADDR' => '10.40.0.1'])
            ->get(route('cuenta.google.callback', ['code' => 'x', 'state' => 'mal']))
            ->assertRedirect(route('cuenta.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_callback_canje_fallido_falla_generico(): void
    {
        $this->mockOidc(function (MockInterface $m) {
            $m->shouldReceive('consumirIntento')->andReturn(['verifier' => 'v', 'nonce' => 'n']);
            $m->shouldReceive('canjearCodigo')->andThrow(new GoogleOidcException('canje'));
        });

        $this->withServerVariables(['REMOTE_ADDR' => '10.40.0.2'])
            ->get(route('cuenta.google.callback', ['code' => 'x', 'state' => 's']))
            ->assertRedirect(route('cuenta.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_callback_id_token_invalido_falla_generico(): void
    {
        $this->mockOidc(function (MockInterface $m) {
            $m->shouldReceive('consumirIntento')->andReturn(['verifier' => 'v', 'nonce' => 'n']);
            $m->shouldReceive('canjearCodigo')->andReturn(['id_token' => 'mal']);
            $m->shouldReceive('validarIdToken')->andThrow(new GoogleOidcException('token'));
        });

        $this->withServerVariables(['REMOTE_ADDR' => '10.40.0.3'])
            ->get(route('cuenta.google.callback', ['code' => 'x', 'state' => 's']))
            ->assertRedirect(route('cuenta.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_callback_sub_conocido_inicia_sesion(): void
    {
        $user = $this->cliente(['email' => 'g@example.com']);
        $identidad = $user->socialIdentities()->create([
            'provider' => 'google',
            'provider_subject' => 'sub-1',
            'provider_email' => 'g@example.com',
            'provider_email_verified' => true,
            'linked_at' => now()->subDay(),
        ]);

        $this->mockOidc(function (MockInterface $m) {
            $m->shouldReceive('consumirIntento')->andReturn(['verifier' => 'v', 'nonce' => 'n']);
            $m->shouldReceive('canjearCodigo')->andReturn(['id_token' => 'ok']);
            $m->shouldReceive('validarIdToken')->andReturn([
                'sub' => 'sub-1', 'email' => 'g@example.com', 'email_verified' => true, 'name' => 'G',
            ]);
        });

        $this->withServerVariables(['REMOTE_ADDR' => '10.40.0.4'])
            ->get(route('cuenta.google.callback', ['code' => 'x', 'state' => 's']))
            ->assertRedirect(route('cuenta.panel'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($identidad->fresh()->last_login_at);
    }

    public function test_callback_sub_conocido_cuenta_suspendida_no_entra(): void
    {
        $user = $this->cliente(['email' => 'g@example.com', 'status' => 'SUSPENDED']);
        $user->socialIdentities()->create([
            'provider' => 'google', 'provider_subject' => 'sub-1', 'linked_at' => now(),
        ]);

        $this->mockOidc(function (MockInterface $m) {
            $m->shouldReceive('consumirIntento')->andReturn(['verifier' => 'v', 'nonce' => 'n']);
            $m->shouldReceive('canjearCodigo')->andReturn(['id_token' => 'ok']);
            $m->shouldReceive('validarIdToken')->andReturn(['sub' => 'sub-1', 'email' => 'g@example.com']);
        });

        $this->withServerVariables(['REMOTE_ADDR' => '10.40.0.5'])
            ->get(route('cuenta.google.callback', ['code' => 'x', 'state' => 's']))
            ->assertRedirect(route('cuenta.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_callback_sub_nuevo_email_nuevo_crea_cuenta_y_vinculo(): void
    {
        $this->mockOidc(function (MockInterface $m) {
            $m->shouldReceive('consumirIntento')->andReturn(['verifier' => 'v', 'nonce' => 'n']);
            $m->shouldReceive('canjearCodigo')->andReturn(['id_token' => 'ok']);
            $m->shouldReceive('validarIdToken')->andReturn([
                'sub' => 'sub-nuevo', 'email' => 'nueva@example.com',
                'email_verified' => true, 'name' => 'Nueva Usuaria',
            ]);
        });

        $this->withServerVariables(['REMOTE_ADDR' => '10.40.0.6'])
            ->get(route('cuenta.google.callback', ['code' => 'x', 'state' => 's']))
            ->assertRedirect(route('cuenta.panel'));

        $user = User::where('email', 'nueva@example.com')->firstOrFail();
        $this->assertSame('ACTIVE', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('Cliente'));
        $this->assertNotNull($user->customerLink);
        $this->assertDatabaseHas('social_identities', [
            'user_id' => $user->id, 'provider' => 'google', 'provider_subject' => 'sub-nuevo',
        ]);
        $this->assertAuthenticatedAs($user);
        Mail::assertSent(CuentaGoogleMail::class);
    }

    public function test_callback_email_coincidente_no_autovincula(): void
    {
        $existente = $this->cliente(['email' => 'dup@example.com']);

        $this->mockOidc(function (MockInterface $m) {
            $m->shouldReceive('consumirIntento')->andReturn(['verifier' => 'v', 'nonce' => 'n']);
            $m->shouldReceive('canjearCodigo')->andReturn(['id_token' => 'ok']);
            $m->shouldReceive('validarIdToken')->andReturn([
                'sub' => 'sub-otro', 'email' => 'dup@example.com',
                'email_verified' => true, 'name' => 'Otro',
            ]);
        });

        $this->withServerVariables(['REMOTE_ADDR' => '10.40.0.7'])
            ->get(route('cuenta.google.callback', ['code' => 'x', 'state' => 's']))
            ->assertRedirect(route('cuenta.login'))
            ->assertSessionHas('info');

        // Sin vínculo creado y sin sesión: exige la cuenta local.
        $this->assertGuest();
        $this->assertSame(0, $existente->socialIdentities()->count());
        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'cuenta', 'accion' => 'vinculacion_requerida',
        ]);
    }

    public function test_vincular_muestra_confirmacion_con_pendiente(): void
    {
        $user = $this->cliente(['email' => 'dup@example.com']);

        $this->actingAs($user)
            ->withSession(['google_oidc.pendiente' => [
                'sub' => 'sub-otro', 'email' => 'dup@example.com',
                'nombre' => 'Otro', 'verificado' => true,
                'expira' => now()->addMinutes(15)->getTimestamp(),
            ]])
            ->get(route('cuenta.google.vincular'))
            ->assertStatus(200)
            ->assertViewIs('cuenta.google-vincular');
    }

    public function test_confirmar_vinculo_email_coincidente_crea_identidad(): void
    {
        $user = $this->cliente(['email' => 'dup@example.com']);

        $this->actingAs($user)
            ->withSession(['google_oidc.pendiente' => [
                'sub' => 'sub-otro', 'email' => 'dup@example.com',
                'nombre' => 'Otro', 'verificado' => true,
                'expira' => now()->addMinutes(15)->getTimestamp(),
            ]])
            ->post(route('cuenta.google.confirmar'))
            ->assertRedirect(route('cuenta.panel'));

        $this->assertDatabaseHas('social_identities', [
            'user_id' => $user->id, 'provider' => 'google', 'provider_subject' => 'sub-otro',
        ]);
        Mail::assertSent(CuentaGoogleMail::class);
    }

    public function test_confirmar_vinculo_email_distinto_falla(): void
    {
        $user = $this->cliente(['email' => 'mia@example.com']);

        $this->actingAs($user)
            ->withSession(['google_oidc.pendiente' => [
                'sub' => 'sub-otro', 'email' => 'otra@example.com',
                'nombre' => 'Otra', 'verificado' => true,
                'expira' => now()->addMinutes(15)->getTimestamp(),
            ]])
            ->post(route('cuenta.google.confirmar'))
            ->assertRedirect(route('cuenta.panel'))
            ->assertSessionHasErrors('email');

        $this->assertSame(0, SocialIdentity::count());
    }

    public function test_desvincular_ultimo_metodo_se_rechaza(): void
    {
        $user = $this->cliente(['email' => 'solo@example.com']);
        $user->socialIdentities()->create([
            'provider' => 'google', 'provider_subject' => 'sub-solo', 'linked_at' => now(),
        ]);
        $this->assertNull($user->password_changed_at);

        $this->actingAs($user)
            ->post(route('cuenta.google.desvincular'))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, $user->socialIdentities()->count());
    }

    public function test_desvincular_con_clave_local_funciona(): void
    {
        $user = $this->cliente(['email' => 'ambos@example.com', 'password_changed_at' => now()]);
        $user->socialIdentities()->create([
            'provider' => 'google', 'provider_subject' => 'sub-ambos',
            'provider_email' => 'ambos@example.com', 'linked_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('cuenta.google.desvincular'))
            ->assertSessionHas('success');

        $this->assertSame(0, $user->socialIdentities()->count());
        Mail::assertSent(CuentaGoogleMail::class);
    }
}
