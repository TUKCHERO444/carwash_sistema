<?php

namespace Tests\Unit;

use App\Services\GoogleOidcException;
use App\Services\GoogleOidcService;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\GoogleOidcLlaves;
use Tests\TestCase;

/**
 * Validación criptográfica y semántica del ID token con llaves reales.
 * Sin red: JWKS servido vía Http::fake (llaves estáticas de test).
 */
class GoogleOidcServiceTest extends TestCase
{
    private const CLIENT_ID = 'test-client-id';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google.client_id', self::CLIENT_ID);
        Cache::flush();

        Http::fake([
            GoogleOidcService::JWKS_URL => Http::response(GoogleOidcLlaves::jwks(), 200),
        ]);
    }

    private function token(array $claims, ?string $privadaPem = null): string
    {
        return JWT::encode($claims, $privadaPem ?? GoogleOidcLlaves::PRIVADA_A, 'RS256', GoogleOidcLlaves::KID_A);
    }

    private function claimsValidas(): array
    {
        return [
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'sub' => 'sub-123',
            'email' => 'user@example.com',
            'email_verified' => true,
            'name' => 'Usuaria',
            'nonce' => 'nonce-abc',
            'exp' => now()->addHour()->getTimestamp(),
            'iat' => now()->getTimestamp(),
        ];
    }

    public function test_id_token_valido_devuelve_claims(): void
    {
        $claims = app(GoogleOidcService::class)->validarIdToken(
            $this->token($this->claimsValidas()), 'nonce-abc'
        );

        $this->assertSame('sub-123', $claims['sub']);
        $this->assertSame('user@example.com', $claims['email']);
    }

    public function test_iss_alternativo_tambien_vale(): void
    {
        $claims = $this->claimsValidas();
        $claims['iss'] = 'accounts.google.com';

        $resultado = app(GoogleOidcService::class)->validarIdToken($this->token($claims), 'nonce-abc');

        $this->assertSame('sub-123', $resultado['sub']);
    }

    public function test_firma_con_otra_llave_falla(): void
    {
        $this->expectException(GoogleOidcException::class);

        app(GoogleOidcService::class)->validarIdToken(
            $this->token($this->claimsValidas(), GoogleOidcLlaves::PRIVADA_B), 'nonce-abc'
        );
    }

    public function test_audiencia_distinta_falla(): void
    {
        $claims = $this->claimsValidas();
        $claims['aud'] = 'otro-cliente';

        $this->expectException(GoogleOidcException::class);

        app(GoogleOidcService::class)->validarIdToken($this->token($claims), 'nonce-abc');
    }

    public function test_expirado_falla(): void
    {
        $claims = $this->claimsValidas();
        $claims['exp'] = now()->subMinute()->getTimestamp();

        $this->expectException(GoogleOidcException::class);

        app(GoogleOidcService::class)->validarIdToken($this->token($claims), 'nonce-abc');
    }

    public function test_nonce_distinto_falla(): void
    {
        $this->expectException(GoogleOidcException::class);

        app(GoogleOidcService::class)->validarIdToken($this->token($this->claimsValidas()), 'nonce-otro');
    }

    public function test_emisor_desconocido_falla(): void
    {
        $claims = $this->claimsValidas();
        $claims['iss'] = 'https://malicioso.example.com';

        $this->expectException(GoogleOidcException::class);

        app(GoogleOidcService::class)->validarIdToken($this->token($claims), 'nonce-abc');
    }

    public function test_sin_sub_falla(): void
    {
        $claims = $this->claimsValidas();
        unset($claims['sub']);

        $this->expectException(GoogleOidcException::class);

        app(GoogleOidcService::class)->validarIdToken($this->token($claims), 'nonce-abc');
    }
}
