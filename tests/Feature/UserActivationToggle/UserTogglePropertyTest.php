<?php

namespace Tests\Feature\UserActivationToggle;

use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Property-based tests for UserToggleController.
 *
 * Each test method runs 100 iterations using Faker-generated data
 * to verify universal properties of the toggle functionality.
 *
 * Feature: user-activation-toggle
 */
class UserTogglePropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an admin user with the 'editar usuarios' permission.
     */
    private function createAdminWithPermission(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'editar usuarios', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $admin = User::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    // -------------------------------------------------------------------------
    // Property 2: Toggle invierte el valor de activo (round-trip)
    // Validates: Requirements 2.1
    // -------------------------------------------------------------------------

    /**
     * Feature: user-activation-toggle, Property 2: Toggle invierte el valor de activo (round-trip)
     *
     * For any user with activo = 0 or activo = 1, applying toggle SHALL invert the value.
     * Applying toggle twice SHALL return the user to their original state.
     *
     * **Validates: Requirements 2.1**
     */
    public function test_property_2_toggle_inverts_activo_round_trip(): void
    {
        $faker = FakerFactory::create();
        $admin = $this->createAdminWithPermission();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            // Alternate between starting with activo=1 and activo=0
            $initialActivo = $i % 2 === 0 ? 1 : 0;

            $user = User::factory()->create([
                'activo' => $initialActivo,
            ]);

            // First toggle: value must be inverted
            $response = $this->actingAs($admin)
                ->patchJson("/users/{$user->id}/toggle");

            $response->assertStatus(200);

            $user->refresh();
            $expectedAfterFirst = $initialActivo === 1 ? 0 : 1;

            $this->assertEquals(
                $expectedAfterFirst,
                (int) $user->activo,
                "Property 2 failed at iteration {$i}: after first toggle, expected activo={$expectedAfterFirst} but got {$user->activo}"
            );

            // Second toggle: value must return to original
            $response = $this->actingAs($admin)
                ->patchJson("/users/{$user->id}/toggle");

            $response->assertStatus(200);

            $user->refresh();

            $this->assertEquals(
                $initialActivo,
                (int) $user->activo,
                "Property 2 failed at iteration {$i}: after second toggle (round-trip), expected activo={$initialActivo} but got {$user->activo}"
            );

            // Clean up for next iteration
            $user->delete();
        }
    }

    // -------------------------------------------------------------------------
    // Property 3: Respuesta JSON del toggle contiene el nuevo valor y mensaje
    // Validates: Requirements 2.2
    // -------------------------------------------------------------------------

    /**
     * Feature: user-activation-toggle, Property 3: Respuesta JSON del toggle contiene el nuevo valor y mensaje
     *
     * For any user with any value of activo, when the toggle is processed successfully,
     * the JSON response SHALL contain the new activo value (inverse of previous) and
     * a non-empty message field, with HTTP 200.
     *
     * **Validates: Requirements 2.2**
     */
    public function test_property_3_json_response_contains_new_value_and_message(): void
    {
        $admin = $this->createAdminWithPermission();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $initialActivo = $i % 2 === 0 ? 1 : 0;

            $user = User::factory()->create([
                'activo' => $initialActivo,
            ]);

            $response = $this->actingAs($admin)
                ->patchJson("/users/{$user->id}/toggle");

            $response->assertStatus(200);
            $response->assertJsonStructure(['activo', 'message']);

            $json = $response->json();

            // activo in response must be the inverse of the initial value
            $expectedActivo = $initialActivo === 1 ? 0 : 1;
            $this->assertEquals(
                $expectedActivo,
                $json['activo'],
                "Property 3 failed at iteration {$i}: expected activo={$expectedActivo} in response but got {$json['activo']}"
            );

            // message must be non-empty
            $this->assertNotEmpty(
                $json['message'],
                "Property 3 failed at iteration {$i}: message field must not be empty"
            );

            // message must match the expected text based on new activo value
            if ($expectedActivo === 1) {
                $this->assertEquals(
                    'Usuario activado correctamente.',
                    $json['message'],
                    "Property 3 failed at iteration {$i}: wrong message for activo=1"
                );
            } else {
                $this->assertEquals(
                    'Usuario inactivado correctamente.',
                    $json['message'],
                    "Property 3 failed at iteration {$i}: wrong message for activo=0"
                );
            }

            $user->delete();
        }
    }

    // -------------------------------------------------------------------------
    // Property 4: Toggle de usuario inexistente retorna 404
    // Validates: Requirements 2.3
    // -------------------------------------------------------------------------

    /**
     * Feature: user-activation-toggle, Property 4: Toggle de usuario inexistente retorna 404
     *
     * For any identifier that does not exist in the database, the toggle request
     * SHALL return HTTP 404.
     *
     * **Validates: Requirements 2.3**
     */
    public function test_property_4_toggle_nonexistent_user_returns_404(): void
    {
        $admin = $this->createAdminWithPermission();
        $iterations = 100;

        // Find the current max ID to generate IDs that definitely don't exist
        $maxId = User::max('id') ?? 0;

        for ($i = 0; $i < $iterations; $i++) {
            // Use IDs well beyond any existing user
            $nonExistentId = $maxId + 1000 + $i;

            $response = $this->actingAs($admin)
                ->patchJson("/users/{$nonExistentId}/toggle");

            $response->assertStatus(404);
        }
    }
}
