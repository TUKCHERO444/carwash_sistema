<?php

namespace Tests\Feature\Cuenta;

use App\Models\OneTimeToken;
use App\Models\User;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// Feature: autenticacion-publica-clientes, Property 1: el registro crea
// identidad PENDING + vínculo ACTIVO + token hasheado y rol Cliente sin
// permisos operativos, para cualquier payload válido.

/**
 * Property 1: registro transaccional íntegro ante entradas aleatorias.
 */
class CuentaRegistroPropertyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        Mail::fake();
    }

    public function test_registro_siempre_crea_identidad_vinculo_token_y_rol(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $email = "prop{$i}@example.com";
            $dni = str_pad((string) fake()->numberBetween(0, 99999999), 8, '0', STR_PAD_LEFT);
            $telefono = '9'.str_pad((string) fake()->numberBetween(0, 99999999), 8, '0', STR_PAD_LEFT);
            $clave = 'clave-'.fake()->password(8, 12);

            // Nombre solo-letras (faker en_US puede traer apóstrofes/guiones).
            $nombre = trim(preg_replace('/ {2,}/', ' ', preg_replace(
                '/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]/u',
                '',
                fake()->firstName().' '.fake()->lastName()
            )));

            $respuesta = $this->withServerVariables(['REMOTE_ADDR' => "10.30.{$i}.1"])
                ->post(route('cuenta.registro'), [
                    'nombre' => $nombre,
                    'email' => $email,
                    'password' => $clave,
                    'password_confirmation' => $clave,
                    'dni' => $dni,
                    'telefono' => $telefono,
                ]);

            $respuesta->assertRedirect(route('cuenta.login'));

            $user = User::where('email', $email)->first();
            $this->assertNotNull($user, "iteración {$i}: usuario no creado");
            $this->assertSame('PENDING_EMAIL_VERIFICATION', $user->status);
            $this->assertTrue($user->hasRole('Cliente'));
            $this->assertFalse($user->hasPermissionTo('acceso-ventas'));
            $this->assertFalse($user->hasPermissionTo('acceso-caja'));
            $this->assertNotNull($user->customerLink);
            $this->assertSame('ACTIVO', $user->customerLink->estado);

            $token = OneTimeToken::where('user_id', $user->id)
                ->where('purpose', OneTimeToken::PURPOSE_VERIFICACION)
                ->first();
            $this->assertNotNull($token, "iteración {$i}: token no creado");
            $this->assertNotSame($clave, $token->token_hash);
            $this->assertNull($token->consumed_at);
        }
    }
}
