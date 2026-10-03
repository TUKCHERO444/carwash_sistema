<?php

namespace Tests\Feature\Cuenta;

use App\Services\GoogleOidcException;
use App\Services\GoogleOidcService;
use Database\Seeders\AuthSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\GoogleOidcLlaves;
use Tests\TestCase;

// Feature: autenticacion-publica-clientes, Property 9: Google vincula por
// provider+sub con ID token válido (firma, iss, aud, exp, nonce), nunca por
// email solo; cualquier token adulterado o claim inválido siempre falla.

/**
 * Property 9: solo el ID token exacto pasa la validación.
 */
class CuentaGooglePropertyTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'property-client-id';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);

        config()->set('services.google.client_id', self::CLIENT_ID);
        Cache::flush();

        Http::fake([
            GoogleOidcService::JWKS_URL => Http::response(GoogleOidcLlaves::jwks(), 200),
        ]);
    }

    public function test_solo_token_exacto_pasa_validacion(): void
    {
        $servicio = app(GoogleOidcService::class);

        for ($i = 0; $i < 100; $i++) {
            $nonce = 'nonce-'.$i.'-'.fake()->lexify('????');
            $variante = $i % 7;

            $claims = [
                'iss' => 'https://accounts.google.com',
                'aud' => self::CLIENT_ID,
                'sub' => 'sub-'.$i,
                'email' => fake()->unique()->safeEmail(),
                'email_verified' => true,
                'nonce' => $nonce,
                'exp' => now()->addHour()->getTimestamp(),
                'iat' => now()->getTimestamp(),
            ];

            $llave = GoogleOidcLlaves::PRIVADA_A;
            $nonceEsperado = $nonce;

            switch ($variante) {
                case 1: $claims['aud'] = 'cliente-distinto';
                    break;
                case 2: $claims['exp'] = now()->subMinute()->getTimestamp();
                    break;
                case 3: $nonceEsperado = 'nonce-otro';
                    break;
                case 4: $claims['iss'] = 'https://malicioso.example.com';
                    break;
                case 5: unset($claims['sub']);
                    break;
                case 6: $llave = GoogleOidcLlaves::PRIVADA_B;
                    break;
            }

            $token = JWT::encode($claims, $llave, 'RS256', GoogleOidcLlaves::KID_A);

            if ($variante === 0) {
                $resultado = $servicio->validarIdToken($token, $nonceEsperado);
                $this->assertSame('sub-'.$i, $resultado['sub'], "iteración {$i}: token válido rechazado");
            } else {
                try {
                    $servicio->validarIdToken($token, $nonceEsperado);
                    $this->fail("iteración {$i} (variante {$variante}): token adulterado aceptado");
                } catch (GoogleOidcException) {
                    $this->assertTrue(true);
                }
            }
        }
    }
}
