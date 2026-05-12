<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Example-based Feature Tests for ProductoController@updateStock.
 *
 * Covers:
 * - Successful stock update (Req. 3.1, 3.2, 3.3)
 * - Empty field validation (Req. 2.1)
 * - Value ≤ 0 validation (Req. 2.3)
 * - Value > 9999 validation (Req. 2.4)
 * - Non-existent product (Req. 5.3, 5.4)
 * - Unauthenticated user (Req. 5.1)
 *
 * Feature: stock-update-modal
 */
class StockUpdateModalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the Administrador role
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);

        // Create an admin user and assign the role
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);

        // Create a product with known stock
        $this->producto = Producto::create([
            'nombre'        => 'Aceite Motor 5W30',
            'precio_compra' => 15.00,
            'precio_venta'  => 25.00,
            'stock'         => 10,
            'inventario'    => 10,
            'activo'        => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Successful update (Req. 3.1, 3.2, 3.3)
    // -------------------------------------------------------------------------

    /**
     * WHEN an admin sends a valid cantidad_adicional,
     * THE server SHALL return JSON {success: true, nuevo_stock: N}
     * AND update both stock and inventario in the database.
     *
     * Validates: Requirements 3.1, 3.2, 3.3
     */
    public function test_successful_stock_update_returns_json_and_updates_db(): void
    {
        $stockInicial = $this->producto->stock; // 10
        $cantidadAdicional = 5;
        $nuevoStockEsperado = $stockInicial + $cantidadAdicional; // 15

        $response = $this->actingAs($this->admin)
            ->patchJson(
                route('productos.updateStock', $this->producto),
                ['cantidad_adicional' => $cantidadAdicional]
            );

        $response->assertStatus(200);
        $response->assertJson([
            'success'     => true,
            'nuevo_stock' => $nuevoStockEsperado,
        ]);

        $this->assertDatabaseHas('productos', [
            'id'        => $this->producto->id,
            'stock'     => $nuevoStockEsperado,
            'inventario' => $nuevoStockEsperado,
        ]);
    }

    // -------------------------------------------------------------------------
    // Validation: empty field (Req. 2.1)
    // -------------------------------------------------------------------------

    /**
     * WHEN the admin submits the form with an empty cantidad_adicional,
     * THE server SHALL return HTTP 422 with a required field error message.
     *
     * Validates: Requirements 2.1
     */
    public function test_empty_cantidad_adicional_returns_422_with_required_message(): void
    {
        $response = $this->actingAs($this->admin)
            ->patchJson(
                route('productos.updateStock', $this->producto),
                ['cantidad_adicional' => '']
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cantidad_adicional']);
    }

    /**
     * WHEN the admin submits the form without the cantidad_adicional field,
     * THE server SHALL return HTTP 422 with a required field error message.
     *
     * Validates: Requirements 2.1
     */
    public function test_missing_cantidad_adicional_returns_422(): void
    {
        $response = $this->actingAs($this->admin)
            ->patchJson(
                route('productos.updateStock', $this->producto),
                []
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cantidad_adicional']);
    }

    // -------------------------------------------------------------------------
    // Validation: value ≤ 0 (Req. 2.3)
    // -------------------------------------------------------------------------

    /**
     * WHEN the admin sends cantidad_adicional = 0,
     * THE server SHALL return HTTP 422.
     *
     * Validates: Requirements 2.3
     */
    public function test_zero_cantidad_adicional_returns_422(): void
    {
        $response = $this->actingAs($this->admin)
            ->patchJson(
                route('productos.updateStock', $this->producto),
                ['cantidad_adicional' => 0]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cantidad_adicional']);
    }

    /**
     * WHEN the admin sends a negative cantidad_adicional,
     * THE server SHALL return HTTP 422.
     *
     * Validates: Requirements 2.3
     */
    public function test_negative_cantidad_adicional_returns_422(): void
    {
        $response = $this->actingAs($this->admin)
            ->patchJson(
                route('productos.updateStock', $this->producto),
                ['cantidad_adicional' => -5]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cantidad_adicional']);
    }

    // -------------------------------------------------------------------------
    // Validation: value > 9999 (Req. 2.4)
    // -------------------------------------------------------------------------

    /**
     * WHEN the admin sends cantidad_adicional = 10000 (> 9999),
     * THE server SHALL return HTTP 422.
     *
     * Validates: Requirements 2.4
     */
    public function test_cantidad_adicional_exceeding_9999_returns_422(): void
    {
        $response = $this->actingAs($this->admin)
            ->patchJson(
                route('productos.updateStock', $this->producto),
                ['cantidad_adicional' => 10000]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cantidad_adicional']);
    }

    /**
     * WHEN the admin sends cantidad_adicional = 9999 (boundary — valid),
     * THE server SHALL return HTTP 200.
     *
     * Validates: Requirements 2.4
     */
    public function test_cantidad_adicional_at_9999_boundary_is_accepted(): void
    {
        $response = $this->actingAs($this->admin)
            ->patchJson(
                route('productos.updateStock', $this->producto),
                ['cantidad_adicional' => 9999]
            );

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // Non-existent product (Req. 5.3, 5.4)
    // -------------------------------------------------------------------------

    /**
     * WHEN the admin requests a stock update for a product that does not exist,
     * THE server SHALL return HTTP 404.
     *
     * Validates: Requirements 5.3, 5.4
     */
    public function test_nonexistent_product_returns_404(): void
    {
        $nonExistentId = $this->producto->id + 9999;

        $response = $this->actingAs($this->admin)
            ->patchJson(
                "/productos/{$nonExistentId}/stock",
                ['cantidad_adicional' => 5]
            );

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Unauthenticated user (Req. 5.1)
    // -------------------------------------------------------------------------

    /**
     * WHEN an unauthenticated browser request is made to the stock update route,
     * THE server SHALL redirect to the login page.
     *
     * Validates: Requirements 5.1
     */
    public function test_unauthenticated_browser_request_redirects_to_login(): void
    {
        $response = $this->patch(
            route('productos.updateStock', $this->producto),
            ['cantidad_adicional' => 5]
        );

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * WHEN an unauthenticated JSON request is made to the stock update route,
     * THE server SHALL return HTTP 401.
     *
     * Validates: Requirements 5.1
     */
    public function test_unauthenticated_json_request_returns_401(): void
    {
        $response = $this->patchJson(
            route('productos.updateStock', $this->producto),
            ['cantidad_adicional' => 5]
        );

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Authorization: non-admin user (Req. 5.2)
    // -------------------------------------------------------------------------

    /**
     * WHEN an authenticated user without the Administrador role
     * attempts to update stock via a browser request,
     * THE server SHALL redirect to the dashboard (the app converts
     * Spatie's UnauthorizedException into a 302 redirect to dashboard).
     *
     * Validates: Requirements 5.2
     */
    public function test_user_without_admin_role_is_redirected_to_dashboard(): void
    {
        $role = Role::firstOrCreate(['name' => 'Asistente', 'guard_name' => 'web']);
        $nonAdmin = User::factory()->create();
        $nonAdmin->assignRole($role);

        $response = $this->actingAs($nonAdmin)
            ->patch(
                route('productos.updateStock', $this->producto),
                ['cantidad_adicional' => 5]
            );

        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));
    }
}
