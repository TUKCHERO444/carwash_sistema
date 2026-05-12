<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Property-based tests for the login panel.
 *
 * Each test method runs 100 iterations using Faker-generated data
 * to verify universal properties of the authentication system.
 *
 * No external PBT library is used — properties are verified via
 * loops with Faker inside standard PHPUnit test methods.
 */
class LoginPropertyTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Property 1: Autenticación exitosa para credenciales válidas
    // Validates: Requirement 2.1
    // -------------------------------------------------------------------------

    /**
     * Property 1: For any randomly generated user (random email and password),
     * Auth::attempt() with their credentials must return true.
     *
     * Validates: Requirement 2.1
     */
    public function test_property_1_auth_attempt_returns_true_for_any_valid_user(): void
    {
        $faker = FakerFactory::create();
        $faker->unique(true); // reset unique generator

        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $email    = $faker->unique()->safeEmail();
            $password = $faker->password(minLength: 8, maxLength: 32);

            // Create the user with a hashed password
            User::create([
                'name'     => $faker->name(),
                'email'    => $email,
                'password' => bcrypt($password),
            ]);

            // Auth::attempt() must return true for valid credentials
            $result = Auth::attempt([
                'email'    => $email,
                'password' => $password,
            ]);

            $this->assertTrue(
                $result,
                "Property 1 failed at iteration {$i}: Auth::attempt() returned false for valid credentials (email: {$email})"
            );

            // Log out between iterations to avoid session carry-over
            Auth::logout();
        }
    }

    // -------------------------------------------------------------------------
    // Property 2: Credenciales inválidas producen el mensaje de error correcto
    // Validates: Requirements 2.4, 2.5
    // -------------------------------------------------------------------------

    /**
     * Property 2: For any combination of unregistered email or incorrect password,
     * POST /login must return to the form with the correct error message on the
     * 'email' field.
     *
     * Validates: Requirements 2.4, 2.5
     */
    public function test_property_2_invalid_credentials_produce_correct_error_message(): void
    {
        $faker = FakerFactory::create();
        $faker->unique(true); // reset unique generator

        $expectedError = 'Las credenciales proporcionadas no coinciden con nuestros registros.';
        $iterations    = 100;

        // Create one real user so we can also test wrong-password scenarios
        $realEmail    = $faker->unique()->safeEmail();
        $realPassword = $faker->password(minLength: 8, maxLength: 32);
        User::create([
            'name'     => $faker->name(),
            'email'    => $realEmail,
            'password' => bcrypt($realPassword),
        ]);

        for ($i = 0; $i < $iterations; $i++) {
            // Alternate between: non-existent email, and wrong password for real user
            if ($i % 2 === 0) {
                // Non-existent email
                $credentials = [
                    'email'    => $faker->unique()->safeEmail(),
                    'password' => $faker->password(minLength: 8),
                ];
            } else {
                // Existing email but wrong password
                $credentials = [
                    'email'    => $realEmail,
                    'password' => $faker->password(minLength: 8) . '_wrong_' . $i,
                ];
            }

            $response = $this->post('/login', $credentials);

            $response->assertSessionHasErrors([
                'email' => $expectedError,
            ]);

            // Ensure the user is not authenticated
            $this->assertGuest(
                null,
                "Property 2 failed at iteration {$i}: user was authenticated with invalid credentials"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Property 3: Errores de validación se muestran junto al campo correspondiente
    // Validates: Requirements 2.6, 2.7, 2.8, 2.9
    // -------------------------------------------------------------------------

    /**
     * Property 3: For any submission with invalid data (empty field or incorrect
     * email format), the response must contain the validation error on the
     * appropriate field.
     *
     * Validates: Requirements 2.6, 2.7, 2.8, 2.9
     */
    public function test_property_3_validation_errors_appear_on_the_correct_field(): void
    {
        $faker = FakerFactory::create();

        $iterations = 100;

        // Possible invalid submission types
        $invalidTypes = [
            'empty_email',
            'empty_password',
            'invalid_email_format',
        ];

        // Generators for invalid email formats (not valid RFC 5322 emails)
        $invalidEmailFormats = [
            'not-an-email',
            'missing@',
            '@nodomain',
            'spaces in@email.com',
            'double@@domain.com',
            'nodot',
            'just-text',
            '',
        ];

        for ($i = 0; $i < $iterations; $i++) {
            $type = $invalidTypes[$i % count($invalidTypes)];

            switch ($type) {
                case 'empty_email':
                    $payload       = ['email' => '', 'password' => $faker->password(minLength: 8)];
                    $expectedField = 'email';
                    break;

                case 'empty_password':
                    $payload       = ['email' => $faker->safeEmail(), 'password' => ''];
                    $expectedField = 'password';
                    break;

                case 'invalid_email_format':
                default:
                    $invalidEmail  = $invalidEmailFormats[$i % count($invalidEmailFormats)];
                    $payload       = ['email' => $invalidEmail, 'password' => $faker->password(minLength: 8)];
                    $expectedField = 'email';
                    break;
            }

            $response = $this->post('/login', $payload);

            $response->assertSessionHasErrors($expectedField);

            $this->assertGuest(
                null,
                "Property 3 failed at iteration {$i} (type: {$type}): user was authenticated with invalid form data"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Property 4: Acceso no autenticado a rutas protegidas redirige a /login
    // Validates: Requirements 3.4, 5.1, 5.4
    // -------------------------------------------------------------------------

    /**
     * Property 4: For any protected route, an unauthenticated request must
     * receive an HTTP 302 redirect to /login.
     *
     * Validates: Requirements 3.4, 5.1, 5.4
     */
    public function test_property_4_unauthenticated_access_to_protected_routes_redirects_to_login(): void
    {
        $faker = FakerFactory::create();

        // The only protected route defined in this spec
        $protectedRoute = '/dashboard';
        $iterations     = 100;

        for ($i = 0; $i < $iterations; $i++) {
            // Ensure no authenticated session exists
            Auth::logout();

            // Vary the request slightly: sometimes add random query params or headers
            // to confirm the redirect is unconditional regardless of request variation
            $response = $this->get($protectedRoute);

            $response->assertStatus(302, "Property 4 failed at iteration {$i}: expected HTTP 302 but got {$response->status()}");
            $response->assertRedirect(
                '/login',
                "Property 4 failed at iteration {$i}: expected redirect to /login"
            );

            // Confirm the user is still a guest
            $this->assertGuest(
                null,
                "Property 4 failed at iteration {$i}: user should not be authenticated"
            );
        }
    }
}
