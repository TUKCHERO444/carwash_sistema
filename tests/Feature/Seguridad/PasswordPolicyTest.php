<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-usuarios', 'guard_name' => 'web']);

        $adminRole = Role::firstOrCreate(['name' => RoleNames::ADMINISTRADOR, 'guard_name' => 'web']);
        $adminRole->givePermissionTo('acceso-usuarios');

        Role::firstOrCreate(['name' => 'Cliente', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'name' => 'Administrador Seguro',
            'email' => 'admin-seguro@example.com',
        ]);
        $this->admin->assignRole($adminRole);
        $this->actingAs($this->admin);
    }

    public static function passwordsInvalidas(): array
    {
        return [
            'menos de 12 caracteres' => ['Abcde123!'],
            'sin mayuscula' => ['abcdef12345!'],
            'sin minuscula' => ['ABCDEF12345!'],
            'sin numero' => ['Abcdefghijk!'],
            'sin simbolo' => ['Abcdef123456'],
            'simbolo no permitido' => ['Abcdef12345 '],
            'mas de 64 caracteres' => [str_repeat('Aa1!', 17)],
            'clave comun' => ['Password123!'],
            'contiene nombre' => ['JuanPerez#2026', ['name' => 'Juan Perez']],
            'contiene correo' => ['Seguridad#2026', ['email' => 'seguridad@example.com']],
        ];
    }

    #[Test]
    #[DataProvider('passwordsInvalidas')]
    public function store_rechaza_passwords_que_no_cumplen_la_politica(string $password, array $overrides = []): void
    {
        $response = $this->post(route('users.store'), $this->storePayload(array_merge($overrides, [
            'password' => $password,
            'password_confirmation' => $password,
        ])));

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => $overrides['email'] ?? 'nuevo@example.com']);
    }

    #[Test]
    public function store_acepta_una_password_que_cumple_la_politica(): void
    {
        $response = $this->post(route('users.store'), $this->storePayload([
            'password' => 'M4r!posaAzul27',
            'password_confirmation' => 'M4r!posaAzul27',
        ]));

        $response->assertRedirect(route('users.index'));

        $user = User::where('email', 'nuevo@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('M4r!posaAzul27', $user->password));
    }

    #[Test]
    public function store_rechaza_una_confirmacion_distinta(): void
    {
        $response = $this->post(route('users.store'), $this->storePayload([
            'password' => 'M4r!posaAzul27',
            'password_confirmation' => 'OtraClave#2026',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
    }

    #[Test]
    public function update_rechaza_una_password_debil(): void
    {
        $target = $this->crearUsuarioObjetivo();

        $response = $this->put(route('users.update', $target), $this->payloadActualizacion($target, [
            'password' => 'Abcdef123456',
            'password_confirmation' => 'Abcdef123456',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('ClaveActual#2026', $target->fresh()->password));
    }

    #[Test]
    public function update_rechaza_reutilizar_la_password_actual(): void
    {
        $target = $this->crearUsuarioObjetivo();

        $response = $this->put(route('users.update', $target), $this->payloadActualizacion($target, [
            'password' => 'ClaveActual#2026',
            'password_confirmation' => 'ClaveActual#2026',
        ]));

        $response->assertSessionHasErrors('password');
    }

    #[Test]
    public function update_sin_password_conserva_la_actual(): void
    {
        $target = $this->crearUsuarioObjetivo();

        $response = $this->put(route('users.update', $target), $this->payloadActualizacion($target));

        $response->assertRedirect(route('users.index'));
        $this->assertTrue(Hash::check('ClaveActual#2026', $target->fresh()->password));
    }

    #[Test]
    public function update_con_password_valida_actualiza_la_clave(): void
    {
        $target = $this->crearUsuarioObjetivo();

        $response = $this->put(route('users.update', $target), $this->payloadActualizacion($target, [
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
        ]));

        $response->assertRedirect(route('users.index'));
        $this->assertTrue(Hash::check('NuevaClave#2026', $target->fresh()->password));
    }

    #[Test]
    public function formularios_muestran_los_requisitos_de_la_politica(): void
    {
        $this->get(route('users.create'))
            ->assertOk()
            ->assertSee('data-password-policy', false)
            ->assertSee('minlength="12"', false)
            ->assertSee('Mínimo 12 caracteres', false);

        $this->get(route('users.edit', $this->crearUsuarioObjetivo()))
            ->assertOk()
            ->assertSee('data-password-policy', false)
            ->assertSee('minlength="12"', false)
            ->assertSee('Mínimo 12 caracteres', false);
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Usuario Nuevo',
            'email' => 'nuevo@example.com',
            'password' => 'M4r!posaAzul27',
            'password_confirmation' => 'M4r!posaAzul27',
            'role' => 'Cliente',
        ], $overrides);
    }

    private function crearUsuarioObjetivo(): User
    {
        $target = User::factory()->create([
            'name' => 'Carlos Objetivo',
            'email' => 'carlos@example.com',
            'password' => 'ClaveActual#2026',
        ]);
        $target->assignRole('Cliente');

        return $target;
    }

    private function payloadActualizacion(User $target, array $overrides = []): array
    {
        return array_merge([
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'Cliente',
        ], $overrides);
    }
}
