<?php

namespace Tests\Feature\Seguridad;

use App\Support\ProductionSecurityGuard;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ConfiguracionSesionTest extends TestCase
{
    #[Test]
    public function produccion_con_debug_activo_falla_el_arranque(): void
    {
        $this->configurarProduccion(['app.debug' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG');

        ProductionSecurityGuard::assertSafeConfiguration();
    }

    #[Test]
    public function produccion_sin_cookie_segura_falla_el_arranque(): void
    {
        $this->configurarProduccion(['session.secure' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SESSION_SECURE_COOKIE');

        ProductionSecurityGuard::assertSafeConfiguration();
    }

    #[Test]
    public function produccion_sin_httponly_falla_el_arranque(): void
    {
        $this->configurarProduccion(['session.http_only' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SESSION_HTTP_ONLY');

        ProductionSecurityGuard::assertSafeConfiguration();
    }

    #[Test]
    public function produccion_con_samesite_none_falla_el_arranque(): void
    {
        $this->configurarProduccion(['session.same_site' => 'none']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SESSION_SAME_SITE');

        ProductionSecurityGuard::assertSafeConfiguration();
    }

    #[Test]
    public function produccion_con_configuracion_segura_arranca(): void
    {
        $this->configurarProduccion();

        ProductionSecurityGuard::assertSafeConfiguration();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function local_con_debug_activo_no_falla_el_arranque(): void
    {
        config()->set('app.env', 'local');
        config()->set('app.debug', true);

        ProductionSecurityGuard::assertSafeConfiguration();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function respuesta_https_incluye_hsts(): void
    {
        $response = $this->get('https://localhost/ping');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    #[Test]
    public function respuesta_http_no_incluye_hsts(): void
    {
        $response = $this->get('http://localhost/ping');

        $response->assertOk();
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    #[Test]
    public function cookies_de_sesion_mantienen_httponly_y_samesite_lax(): void
    {
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configurarProduccion(array $overrides = []): void
    {
        config()->set([
            'app.env' => 'production',
            'app.debug' => false,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            ...$overrides,
        ]);
    }
}
