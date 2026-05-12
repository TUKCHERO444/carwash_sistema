<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests for Requirement 1: Login Form
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1.5 WHEN el usuario accede a la ruta /login,
     * THE Sistema SHALL mostrar el Panel_Login (HTTP 200).
     */
    public function test_login_page_returns_http_200(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /**
     * 1.5 WHEN el usuario accede a la ruta /login,
     * THE Sistema SHALL renderizar la vista auth.login.
     */
    public function test_login_page_renders_auth_login_view(): void
    {
        $response = $this->get('/login');

        $response->assertViewIs('auth.login');
    }

    /**
     * 1.1 THE Panel_Login SHALL mostrar un campo de entrada de tipo email
     * con etiqueta "Correo electrónico".
     */
    public function test_login_page_contains_email_field_with_label(): void
    {
        $response = $this->get('/login');

        // Email input field
        $response->assertSee('name="email"', false);
        // Label text
        $response->assertSee('Correo electrónico');
    }

    /**
     * 1.2 THE Panel_Login SHALL mostrar un campo de entrada de tipo password
     * con etiqueta "Contraseña".
     */
    public function test_login_page_contains_password_field_with_label(): void
    {
        $response = $this->get('/login');

        // Password input field
        $response->assertSee('name="password"', false);
        // Label text
        $response->assertSee('Contraseña');
    }

    /**
     * 1.3 THE Panel_Login SHALL mostrar un botón de envío
     * con el texto "Iniciar sesión".
     */
    public function test_login_page_contains_submit_button_with_correct_text(): void
    {
        $response = $this->get('/login');

        $response->assertSee('Iniciar sesión');
    }

    /**
     * 1.6 WHEN el usuario ya está autenticado y accede a la ruta /login,
     * THE Sistema SHALL redirigirlo al dashboard.
     */
    public function test_authenticated_user_is_redirected_from_login_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/dashboard');
    }

    // -------------------------------------------------------------------------
    // Requirement 2: Autenticación de Credenciales
    // -------------------------------------------------------------------------

    /**
     * 2.1, 2.2, 2.3 WHEN el usuario envía credenciales válidas,
     * THE LoginController SHALL crear una sesión autenticada y redirigir a /dashboard.
     */
    public function test_post_login_with_valid_credentials_redirects_to_dashboard_and_creates_session(): void
    {
        $user = User::factory()->create([
            'email'    => 'usuario@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email'    => 'usuario@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * 2.4 IF el email no existe en la base de datos,
     * THEN THE LoginController SHALL retornar al Panel_Login con error en el campo email.
     */
    public function test_post_login_with_nonexistent_email_returns_to_login_with_email_error(): void
    {
        $response = $this->post('/login', [
            'email'    => 'noexiste@example.com',
            'password' => 'cualquierpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * 2.5 IF la contraseña es incorrecta,
     * THEN THE LoginController SHALL retornar al Panel_Login con el mensaje de error genérico.
     */
    public function test_post_login_with_incorrect_password_returns_generic_error_message(): void
    {
        User::factory()->create([
            'email'    => 'usuario@example.com',
            'password' => bcrypt('passwordcorrecto'),
        ]);

        $response = $this->post('/login', [
            'email'    => 'usuario@example.com',
            'password' => 'passwordincorrecto',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ]);
        $this->assertGuest();
    }

    /**
     * 2.7 WHEN el formulario se envía con el campo email vacío,
     * THE Sistema SHALL retornar un error de validación indicando que el campo es obligatorio.
     */
    public function test_post_login_with_empty_email_returns_required_validation_error(): void
    {
        $response = $this->post('/login', [
            'email'    => '',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * 2.8 WHEN el formulario se envía con el campo password vacío,
     * THE Sistema SHALL retornar un error de validación indicando que el campo es obligatorio.
     */
    public function test_post_login_with_empty_password_returns_required_validation_error(): void
    {
        $response = $this->post('/login', [
            'email'    => 'usuario@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    /**
     * 2.9 WHEN el formulario se envía con un email con formato inválido,
     * THE Sistema SHALL retornar un error de validación indicando el formato incorrecto.
     */
    public function test_post_login_with_invalid_email_format_returns_format_validation_error(): void
    {
        $response = $this->post('/login', [
            'email'    => 'esto-no-es-un-email',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // -------------------------------------------------------------------------
    // Requirement 3: Cierre de Sesión
    // Requirement 5: Protección de Rutas
    // -------------------------------------------------------------------------

    /**
     * 3.1, 3.2, 3.3 WHEN el usuario autenticado envía POST a /logout,
     * THE Sistema SHALL invalidar la sesión, regenerar el token CSRF
     * y redirigir a /login.
     */
    public function test_post_logout_invalidates_session_and_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * 3.4, 5.1, 5.2 WHILE el usuario no está autenticado,
     * THE Sistema SHALL denegar el acceso a /dashboard y redirigirlo a /login.
     */
    public function test_get_dashboard_without_authentication_redirects_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    /**
     * 5.3 WHEN el usuario autenticado accede a /dashboard,
     * THE Sistema SHALL mostrar la vista del dashboard sin redireccionamiento (HTTP 200).
     */
    public function test_get_dashboard_with_authentication_returns_http_200(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Requirement 4: Gestión de Roles con Spatie Laravel Permission
    // -------------------------------------------------------------------------

    /**
     * 4.6 WHEN un usuario con rol `Administrador` inicia sesión,
     * THE Sistema SHALL permitir el acceso a todos los módulos del sistema
     * (accede a /dashboard sin redirección, HTTP 200).
     */
    public function test_administrador_role_can_access_dashboard_without_redirection(): void
    {
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    /**
     * 4.7 WHEN un usuario con rol `Asistente` inicia sesión,
     * THE Sistema SHALL permitir el acceso al dashboard sin permisos adicionales
     * (accede a /dashboard sin redirección, HTTP 200).
     */
    public function test_asistente_role_can_access_dashboard_without_redirection(): void
    {
        $role = Role::firstOrCreate(['name' => 'Asistente', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }
}
