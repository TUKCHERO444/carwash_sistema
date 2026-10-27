<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CredencialesInicialesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function local_sin_password_explicita_mantiene_el_admin_demo(): void
    {
        config()->set('seed.admin.password', null);

        $this->ejecutarSeeder();

        $admin = User::where('email', 'admin@sistema.com')->firstOrFail();

        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue($admin->hasRole('Administrador'));
    }

    #[Test]
    public function produccion_sin_password_explicita_no_crea_administrador(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config()->set('seed.admin.password', null);

        $this->ejecutarSeeder();

        $this->assertDatabaseMissing('users', ['email' => 'admin@sistema.com']);
    }

    #[Test]
    public function produccion_con_password_fuerte_crea_el_administrador_configurado(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config()->set([
            'seed.admin.name' => 'Operador Inicial',
            'seed.admin.email' => 'inicial@example.com',
            'seed.admin.password' => 'M4r!posaAzul27',
        ]);

        $this->ejecutarSeeder();

        $admin = User::where('email', 'inicial@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('M4r!posaAzul27', $admin->password));
        $this->assertTrue($admin->hasRole('Administrador'));
        $this->assertSame('ACTIVE', $admin->status);
        $this->assertNotNull($admin->email_verified_at);
    }

    #[Test]
    public function produccion_rechaza_una_password_debil(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config()->set([
            'seed.admin.name' => 'Operador Inicial',
            'seed.admin.email' => 'inicial@example.com',
            'seed.admin.password' => 'password',
        ]);

        $this->expectException(RuntimeException::class);

        $this->ejecutarSeeder();
    }

    #[Test]
    public function local_rechaza_una_password_explicita_debil(): void
    {
        config()->set([
            'seed.admin.name' => 'Operador Local',
            'seed.admin.email' => 'local@example.com',
            'seed.admin.password' => 'password',
        ]);

        $this->expectException(RuntimeException::class);

        $this->ejecutarSeeder();
    }

    private function ejecutarSeeder(): void
    {
        $this->app->make(AuthSeeder::class)->setContainer($this->app)->run();
    }
}
