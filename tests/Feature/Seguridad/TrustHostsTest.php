<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Http\Middleware\TrustHosts;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrustHostsTest extends TestCase
{
    #[Test]
    public function los_hosts_confiables_derivan_del_app_url(): void
    {
        config()->set('app.url', 'https://carwash.example.com');

        $hosts = (new TrustHosts($this->app))->hosts();

        $this->assertSame(['^(.+\.)?carwash\.example\.com$'], $hosts);
    }
}
