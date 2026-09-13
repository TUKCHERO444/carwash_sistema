<?php

namespace Tests\Feature;

use App\Models\Marca;
use App\Models\User;
use App\Services\ContenidoWebService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicaMarcasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'acceso-contenido-web', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    /**
     * La página pública de marcas responde HTTP 200 en GET /nuestras-marcas.
     */
    public function test_marcas_returns_http_200(): void
    {
        $this->get('/nuestras-marcas')->assertStatus(200);
    }

    /**
     * La vista muestra el breadcrumb y el encabezado del panel (defaults).
     */
    public function test_marcas_renders_heading_and_section(): void
    {
        $response = $this->get('/nuestras-marcas');

        $response->assertSee('Inicio');
        $response->assertSee('Trabajamos con las mejores marcas');
    }

    /**
     * La sección de marcas renderiza las marcas reales de la BD.
     */
    public function test_marcas_renders_brands_from_database(): void
    {
        foreach (['CARPRO', 'WURTH', 'RUPES'] as $nombre) {
            Marca::create(['nombre' => $nombre]);
        }

        $response = $this->get('/nuestras-marcas');

        foreach (['CARPRO', 'WURTH', 'RUPES'] as $nombre) {
            $response->assertSee($nombre);
        }
    }

    /**
     * Sin marcas registradas se muestra el estado vacío.
     */
    public function test_marcas_shows_empty_state_without_brands(): void
    {
        $this->get('/nuestras-marcas')->assertSee('Aún no tenemos marcas registradas.');
    }

    /**
     * Sin curaduría del panel se muestran todas las marcas por nombre.
     */
    public function test_marcas_fallback_orders_all_brands_by_name(): void
    {
        Marca::create(['nombre' => 'Beta']);
        Marca::create(['nombre' => 'Alpha']);
        Marca::create(['nombre' => 'Gamma']);

        $this->get('/nuestras-marcas')->assertSeeInOrder(['Alpha', 'Beta', 'Gamma']);
    }

    /**
     * La curaduría del panel filtra y ordena las marcas de la página pública.
     */
    public function test_marcas_curaduria_filters_and_orders(): void
    {
        Marca::create(['nombre' => 'Alpha']);
        Marca::create(['nombre' => 'Beta']);
        $curada = Marca::create(['nombre' => 'Gamma']);

        $this->actingAs($this->admin)
            ->put(route('contenido-web.update'), [
                'marcas_web' => [$curada->id],
                'marcas_orden' => [$curada->id => 0],
            ])
            ->assertRedirect(route('contenido-web.edit'));

        app()->forgetInstance(ContenidoWebService::class);

        $response = $this->get('/nuestras-marcas');

        $response->assertSee('Gamma');
        $response->assertDontSee('Alpha');
        $response->assertDontSee('Beta');
    }

    /**
     * El enlace "Marcas" de la navegación apunta a la ruta pública /nuestras-marcas.
     */
    public function test_nav_marcas_links_to_public_route(): void
    {
        $this->get('/')->assertSee(route('publica.marcas'), false);
    }
}
