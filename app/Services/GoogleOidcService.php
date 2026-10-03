<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Login con Google mediante OpenID Connect (Authorization Code + PKCE).
 *
 * Checklist Req 8: intento temporal con state/nonce/code_verifier y
 * expiración breve; scopes mínimos openid/email/profile; canje con
 * code_verifier; validación criptográfica Y semántica del ID token
 * (firma JWKS, iss, aud/client_id, exp, nonce). El email NUNCA identifica.
 */
class GoogleOidcService
{
    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    public const SCOPES = ['openid', 'email', 'profile'];

    private const SESSION_KEY = 'google_oidc.intento';

    private const INTENTO_MINUTOS = 10;

    /**
     * ¿Hay credenciales configuradas para operar?
     */
    public function configurado(): bool
    {
        return (bool) config('services.google.client_id')
            && (bool) config('services.google.client_secret');
    }

    /**
     * Inicia el intento: guarda state/nonce/verifier en sesión y
     * devuelve la URL de autorización con challenge S256.
     */
    public function iniciarIntento(Request $request): string
    {
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $request->session()->put(self::SESSION_KEY, [
            'state' => Str::random(40),
            'nonce' => Str::random(40),
            'verifier' => $verifier,
            'expira' => now()->addMinutes(self::INTENTO_MINUTOS)->getTimestamp(),
        ]);

        $intento = $request->session()->get(self::SESSION_KEY);

        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'state' => $intento['state'],
            'nonce' => $intento['nonce'],
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ]);
    }

    /**
     * Consume el intento validando state y expiración.
     *
     * @return array{verifier: string, nonce: string}|null
     */
    public function consumirIntento(Request $request): ?array
    {
        $intento = $request->session()->pull(self::SESSION_KEY);

        if (! is_array($intento)) {
            return null;
        }

        if (($intento['expira'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        if (! hash_equals((string) ($intento['state'] ?? ''), (string) $request->query('state'))) {
            return null;
        }

        return ['verifier' => $intento['verifier'], 'nonce' => $intento['nonce']];
    }

    /**
     * Canjea el authorization code con el code_verifier (un solo uso).
     *
     * @return array{access_token?: string, id_token?: string, ...}
     *
     * @throws GoogleOidcException
     */
    public function canjearCodigo(string $code, string $verifier): array
    {
        try {
            $respuesta = Http::timeout(10)->asForm()->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
                'code_verifier' => $verifier,
            ]);
        } catch (\Throwable $e) {
            throw new GoogleOidcException('No se pudo contactar a Google: '.$e->getMessage(), 0, $e);
        }

        if (! $respuesta->successful()) {
            throw new GoogleOidcException('Google rechazó el canje del código.');
        }

        $datos = $respuesta->json();

        if (! is_array($datos) || empty($datos['id_token'])) {
            throw new GoogleOidcException('Google no devolvió ID token.');
        }

        return $datos;
    }

    /**
     * Valida firma (JWKS), iss, aud, exp y nonce del ID token.
     *
     * @return array{sub: string, email?: string, email_verified?: bool, name?: string, ...}
     *
     * @throws GoogleOidcException
     */
    public function validarIdToken(string $idToken, string $nonceEsperado): array
    {
        try {
            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($this->certificados()));
        } catch (\Throwable $e) {
            throw new GoogleOidcException('ID token inválido (firma o expiración): '.$e->getMessage(), 0, $e);
        }

        if (! in_array($claims['iss'] ?? null, self::ISSUERS, true)) {
            throw new GoogleOidcException('Emisor del ID token no reconocido.');
        }

        if (($claims['aud'] ?? null) !== config('services.google.client_id')) {
            throw new GoogleOidcException('Audiencia del ID token no corresponde.');
        }

        if (empty($claims['sub']) || ! is_string($claims['sub'])) {
            throw new GoogleOidcException('ID token sin sujeto (sub).');
        }

        if (! hash_equals($nonceEsperado, (string) ($claims['nonce'] ?? ''))) {
            throw new GoogleOidcException('Nonce del ID token no coincide.');
        }

        return $claims;
    }

    /**
     * Certificados públicos de Google (cache 1h, nunca secretos).
     *
     * @throws GoogleOidcException
     */
    public function certificados(): array
    {
        try {
            return Cache::remember('google_oidc.jwks', 3600, function () {
                $respuesta = Http::timeout(10)->get(self::JWKS_URL);

                if (! $respuesta->successful() || ! is_array($respuesta->json())) {
                    throw new GoogleOidcException('No se pudieron obtener los certificados de Google.');
                }

                return $respuesta->json();
            });
        } catch (GoogleOidcException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new GoogleOidcException('No se pudieron obtener los certificados de Google.', 0, $e);
        }
    }
}
