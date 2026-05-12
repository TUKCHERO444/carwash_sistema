<?php

namespace Tests\Feature\UserActivationToggle;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Example-based tests for UserToggleController.
 *
 * Covers specific business scenarios:
 * - Auto-toggle protection (403)
 * - Unauthenticated access (302)
 * - Missing permission (redirect to dashboard)
 *
 * Feature: user-activation-toggle
 */
class UserToggleExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a user with the 'editar usuarios' permission.
     */
    private function createUserWithTogglePermission(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'editar usuarios', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * Create a user without the 'editar usuarios' permission.
     */
    private function createUserWithoutTogglePermission(): User
    {
        Role::firstOrCreate(['name' => 'Asistente', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('Asistente');

        return $user;
    }

    // -------------------------------------------------------------------------
    // Requirement 2.4: Auto-toggle protection → HTTP 403
    // -------------------------------------------------------------------------

    /**
     * WHEN the authenticated admin attempts to toggle their own account,
     * THE ToggleController SHALL reject the operation with HTTP 403.
     *
     * Validates: Requirements 2.4
     */
    public function test_toggle_own_user_returns_403(): void
    {
        $admin = $this->createUserWithTogglePermission();

        $response = $this->actingAs($admin)
            ->patchJson("/users/{$admin->id}/toggle");

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'No puedes modificar tu propio estado de activación.',
        ]);
    }

    /**
     * WHEN the authenticated admin attempts to toggle their own account,
     * THE activo value of the admin SHALL remain unchanged.
     *
     * Validates: Requirements 2.4
     */
    public function test_toggle_own_user_does_not_change_activo(): void
    {
        $admin = $this->createUserWithTogglePermission();
        $originalActivo = (int) $admin->activo;

        $this->actingAs($admin)
            ->patchJson("/users/{$admin->id}/toggle");

        $admin->refresh();
        $this->assertEquals(
            $originalActivo,
            (int) $admin->activo,
            'activo should not change when attempting to toggle own account'
        );
    }

    // -------------------------------------------------------------------------
    // Requirement 2.7: Route protected by auth middleware → HTTP 302
    // -------------------------------------------------------------------------

    /**
     * WHEN an unauthenticated request is made to the toggle route,
     * THE system SHALL redirect to /login with HTTP 302.
     *
     * Validates: Requirements 2.7
     */
    public function test_unauthenticated_toggle_request_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->patchJson("/users/{$user->id}/toggle");

        // JSON requests get 401 from Laravel's auth middleware
        $response->assertStatus(401);
    }

    /**
     * WHEN an unauthenticated browser request is made to the toggle route,
     * THE system SHALL redirect to /login with HTTP 302.
     *
     * Validates: Requirements 2.7
     */
    public function test_unauthenticated_browser_toggle_request_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->patch("/users/{$user->id}/toggle");

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    // -------------------------------------------------------------------------
    // Requirement 2.7: Route protected by permission:editar usuarios
    // -------------------------------------------------------------------------

    /**
     * WHEN an authenticated user without 'editar usuarios' permission
     * attempts to toggle another user, THE system SHALL redirect to dashboard.
     *
     * Validates: Requirements 2.7
     */
    public function test_user_without_permission_is_redirected_to_dashboard(): void
    {
        $actor = $this->createUserWithoutTogglePermission();
        $target = User::factory()->create();

        $response = $this->actingAs($actor)
            ->patch("/users/{$target->id}/toggle");

        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));
    }
}
