<?php

namespace Tests\Feature\UserActivationToggle;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Property-based tests for the users/index view rendering.
 *
 * Each test method runs 100 iterations to verify that the view
 * renders the correct badge and toggle button for any value of activo.
 *
 * Feature: user-activation-toggle
 */
class UserIndexViewPropertyTest extends TestCase
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

        $admin = User::factory()->create(['activo' => 1]);
        $admin->assignRole($role);

        return $admin;
    }

    // -------------------------------------------------------------------------
    // Property 9: La vista renderiza badge y botón correctos para cualquier valor de activo
    // Validates: Requirements 4.1, 4.2
    // -------------------------------------------------------------------------

    /**
     * Feature: user-activation-toggle, Property 9: La vista renderiza badge y botón correctos para cualquier valor de activo
     *
     * For any user with activo = 0 or activo = 1, the users/index view SHALL render
     * the correct status badge ("Activo"/"Inactivo" with corresponding colors) and
     * the toggle button with the correct text ("Inactivar"/"Activar").
     *
     * **Validates: Requirements 4.1, 4.2**
     */
    public function test_property_9_view_renders_correct_badge_and_button_for_any_activo(): void
    {
        $admin = $this->createAdminWithPermission();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            // Alternate between activo=1 and activo=0
            $activo = $i % 2 === 0 ? 1 : 0;

            $user = User::factory()->create(['activo' => $activo]);

            $response = $this->withoutVite()->actingAs($admin)->get(route('users.index'));

            $response->assertStatus(200);

            if ($activo === 1) {
                // Badge: green "Activo"
                $response->assertSee('bg-green-100 text-green-800', false);
                $response->assertSee('Activo');

                // Toggle button: yellow/amber "Inactivar"
                $response->assertSee('bg-yellow-100 text-yellow-800', false);
                $response->assertSee('Inactivar');

                // data-toggle-url attribute present
                $response->assertSee('data-toggle-url="' . route('users.toggle', $user) . '"', false);
                $response->assertSee('data-user-id="' . $user->id . '"', false);
            } else {
                // Badge: red "Inactivo"
                $response->assertSee('bg-red-100 text-red-800', false);
                $response->assertSee('Inactivo');

                // Toggle button: green "Activar"
                $response->assertSee('bg-green-100 text-green-800', false);
                $response->assertSee('Activar');

                // data-toggle-url attribute present
                $response->assertSee('data-toggle-url="' . route('users.toggle', $user) . '"', false);
                $response->assertSee('data-user-id="' . $user->id . '"', false);
            }

            // Clean up for next iteration
            $user->delete();
        }
    }

    /**
     * Verify that the activo field is NOT exposed in the edit form.
     *
     * **Validates: Requirements 4.1 (no activo in edit form)**
     */
    public function test_edit_form_does_not_expose_activo_field(): void
    {
        $admin = $this->createAdminWithPermission();
        $user = User::factory()->create(['activo' => 1]);

        $response = $this->actingAs($admin)->get(route('users.edit', $user));

        $response->assertStatus(200);
        // The edit form must not contain an input for 'activo'
        $response->assertDontSee('name="activo"', false);
        $response->assertDontSee("name='activo'", false);
    }
}
