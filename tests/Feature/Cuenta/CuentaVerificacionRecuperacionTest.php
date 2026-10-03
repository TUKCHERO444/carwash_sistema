<?php

namespace Tests\Feature\Cuenta;

use App\Mail\CuentaRecuperacionMail;
use App\Mail\CuentaVerificacionMail;
use App\Models\OneTimeToken;
use App\Models\User;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Verificación de email y recuperación de acceso (Req. 2 y 5).
 */
class CuentaVerificacionRecuperacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        Mail::fake();
    }

    private static int $ipSecuencia = 0;

    private function registrar(string $email = 'cli@example.com'): User
    {
        // IP única por llamada: el throttle (array en tests) persiste
        // en memoria durante todo el proceso phpunit.
        self::$ipSecuencia++;
        $ip = '10.20.'.(intdiv(self::$ipSecuencia, 250) % 250).'.'.((self::$ipSecuencia % 250) + 1);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('cuenta.registro'), [
            'nombre' => 'Ana Torres',
            'email' => $email,
            'password' => 'secreta123',
            'password_confirmation' => 'secreta123',
        ]);

        return User::where('email', $email)->firstOrFail();
    }

    private function secretoVerificacion(User $user): string
    {
        // El secreto solo existe en el correo; en tests se re-emite uno
        // equivalente para simular el clic del enlace.
        [, $secreto] = OneTimeToken::emitir($user, OneTimeToken::PURPOSE_VERIFICACION, 60 * 24);

        return $secreto;
    }

    public function test_verificacion_valida_activa_e_inicia_sesion(): void
    {
        $user = $this->registrar();

        $this->get(route('cuenta.verificacion', ['token' => $this->secretoVerificacion($user)]))
            ->assertRedirect(route('cuenta.panel'));

        $user->refresh();
        $this->assertSame('ACTIVE', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_verificacion_doble_consumo_falla(): void
    {
        $user = $this->registrar();
        $secreto = $this->secretoVerificacion($user);

        $this->get(route('cuenta.verificacion', ['token' => $secreto]))->assertRedirect(route('cuenta.panel'));
        auth()->logout();

        $this->get(route('cuenta.verificacion', ['token' => $secreto]))
            ->assertRedirect(route('cuenta.login'))
            ->assertSessionHasErrors('email');
    }

    public function test_verificacion_expirada_falla(): void
    {
        $user = $this->registrar();
        $secreto = $this->secretoVerificacion($user);

        OneTimeToken::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $this->get(route('cuenta.verificacion', ['token' => $secreto]))
            ->assertRedirect(route('cuenta.login'))
            ->assertSessionHasErrors('email');
    }

    public function test_recuperacion_responde_generico_y_emite_token(): void
    {
        $user = $this->registrar();

        $this->withServerVariables(['REMOTE_ADDR' => '10.20.0.2'])
            ->post(route('cuenta.recuperacion'), ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertNotNull(OneTimeToken::valido(
            $this->ultimoSecreto($user, OneTimeToken::PURPOSE_RECUPERACION),
            OneTimeToken::PURPOSE_RECUPERACION
        ));
    }

    public function test_recuperacion_inexistente_responde_igual(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.0.3'])
            ->post(route('cuenta.recuperacion'), ['email' => 'nadie@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    public function test_recuperacion_cambia_clave_y_consume_token(): void
    {
        $user = $this->registrar();
        [, $secreto] = OneTimeToken::emitir($user, OneTimeToken::PURPOSE_RECUPERACION, 60);

        $this->withServerVariables(['REMOTE_ADDR' => '10.20.0.4'])
            ->post(route('cuenta.recuperacion.actualizar', ['token' => $secreto]), [
                'password' => 'nueva-clave-1',
                'password_confirmation' => 'nueva-clave-1',
            ])
            ->assertRedirect(route('cuenta.panel'));

        $user->refresh();
        $this->assertNotNull($user->password_changed_at);
        $this->assertNull(OneTimeToken::valido($secreto, OneTimeToken::PURPOSE_RECUPERACION));
        $this->assertAuthenticatedAs($user);

        // Reutilizar el enlace falla.
        auth()->logout();
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.0.5'])
            ->post(route('cuenta.recuperacion.actualizar', ['token' => $secreto]), [
                'password' => 'otra-clave-22',
                'password_confirmation' => 'otra-clave-22',
            ])
            ->assertRedirect(route('cuenta.login'));
    }

    /**
     * Recupera el secreto del último correo enviado (Mail::fake).
     */
    private function ultimoSecreto(User $user, string $purpose): string
    {
        $mailable = null;
        Mail::assertSent($purpose === OneTimeToken::PURPOSE_RECUPERACION
            ? CuentaRecuperacionMail::class
            : CuentaVerificacionMail::class, function ($mail) use ($user, &$mailable) {
                if ($mail->hasTo($user->email)) {
                    $mailable = $mail;

                    return true;
                }

                return false;
            });

        $url = $purpose === OneTimeToken::PURPOSE_RECUPERACION
            ? $mailable->urlRecuperacion
            : $mailable->urlVerificacion;

        return basename($url);
    }
}
