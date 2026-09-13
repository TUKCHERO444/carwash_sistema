<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Services\ContenidoWebService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicaProductosTest extends TestCase
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
     * La página pública de productos responde HTTP 200 en GET /nuestros-productos.
     */
    public function test_productos_returns_http_200(): void
    {
        $this->get('/nuestros-productos')->assertStatus(200);
    }

    /**
     * La vista muestra el título/intro del panel (defaults) y el breadcrumb.
     */
    public function test_productos_renders_heading_and_breadcrumb(): void
    {
        $response = $this->get('/nuestros-productos');

        $response->assertSee('Inicio');
        $response->assertSee('Productos');
        $response->assertSee('¿Qué producto buscas para tu auto?');
    }

    /**
     * El mosaico renderiza categorías reales con productos activos y su CTA.
     */
    public function test_productos_renders_category_tiles_from_database(): void
    {
        $categoria = Categoria::create(['nombre' => 'Lavado']);
        Producto::factory()->create(['categoria_id' => $categoria->id, 'activo' => true]);

        $response = $this->get('/nuestros-productos');

        $response->assertSee('LAVADO');
        $response->assertSee('VER MÁS');
        $response->assertSee(route('publica.productos.categoria', ['categoria' => $categoria->slug]), false);
    }

    /**
     * Solo las categorías con al menos un producto activo aparecen en el mosaico.
     */
    public function test_productos_mosaic_ignores_categories_without_active_products(): void
    {
        $conActivos = Categoria::create(['nombre' => 'Lavado']);
        Producto::factory()->create(['categoria_id' => $conActivos->id, 'activo' => true]);

        $soloInactivos = Categoria::create(['nombre' => 'Cerámico']);
        Producto::factory()->create(['categoria_id' => $soloInactivos->id, 'activo' => false]);

        $vacia = Categoria::create(['nombre' => 'Filtros']);

        $response = $this->get('/nuestros-productos');

        $response->assertSee('LAVADO');
        $response->assertDontSee('CERÁMICO');
        $response->assertDontSee('FILTROS');
    }

    /**
     * Sin categorías con productos activos se muestra el estado vacío del mosaico.
     */
    public function test_productos_shows_empty_mosaic_state(): void
    {
        $this->get('/nuestros-productos')
            ->assertSee('Aún no tenemos categorías con productos disponibles.');
    }

    /**
     * El toggle del panel oculta el mosaico de categorías.
     */
    public function test_productos_mosaic_hidden_when_toggle_off(): void
    {
        $categoria = Categoria::create(['nombre' => 'Lavado']);
        Producto::factory()->create(['categoria_id' => $categoria->id, 'activo' => true]);

        $this->actingAs($this->admin)
            ->put(route('contenido-web.update'), ['productos_mostrar_mosaico' => '0'])
            ->assertRedirect(route('contenido-web.edit'));

        app()->forgetInstance(ContenidoWebService::class);

        $response = $this->get('/nuestros-productos');

        $response->assertStatus(200);
        $response->assertDontSee('LAVADO');
        $response->assertDontSee('VER MÁS');
    }

    /**
     * El enlace "Productos" de la navegación apunta a la ruta pública /nuestros-productos.
     */
    public function test_nav_productos_links_to_public_route(): void
    {
        $this->get('/')->assertSee(route('publica.productos'), false);
    }

    /**
     * La vista filtrada responde HTTP 200 y muestra solo los productos
     * activos de la categoría (excluye inactivos y de otras categorías).
     */
    public function test_categoria_filtered_listing_returns_only_its_active_products(): void
    {
        $categoria = Categoria::create(['nombre' => 'Filtros']);
        $otra = Categoria::create(['nombre' => 'Aceites']);

        $visible = Producto::factory()->create([
            'categoria_id' => $categoria->id,
            'activo' => true,
            'nombre' => 'Filtro de aceite premium',
        ]);
        Producto::factory()->create([
            'categoria_id' => $categoria->id,
            'activo' => false,
            'nombre' => 'Filtro sin vigencia',
        ]);
        $foraneo = Producto::factory()->create([
            'categoria_id' => $otra->id,
            'activo' => true,
            'nombre' => 'Aceite 5W30',
        ]);

        $response = $this->get('/nuestros-productos/filtros');

        $response->assertStatus(200);
        $response->assertSee('FILTROS');
        $response->assertSee(mayusculas($visible->nombre));
        $response->assertSeeInOrder(['Mostrando', '1', 'producto'], false);
        $response->assertDontSee('Filtro sin vigencia');
        $response->assertDontSee($foraneo->nombre);
    }

    /**
     * La vista filtrada marca "Agotado" a los productos sin stock.
     */
    public function test_categoria_filtered_listing_marks_agotado_when_no_stock(): void
    {
        $categoria = Categoria::create(['nombre' => 'Frenos']);

        Producto::factory()->create([
            'categoria_id' => $categoria->id,
            'activo' => true,
            'stock' => 0,
            'nombre' => 'Pastillas de freno',
        ]);

        $response = $this->get('/nuestros-productos/frenos');

        $response->assertStatus(200);
        $response->assertSee('Agotado');
        $response->assertSee('Sin stock disponible');
    }

    /**
     * Un slug de categoría inexistente responde HTTP 404.
     */
    public function test_categoria_filtered_listing_returns_404_for_unknown_slug(): void
    {
        $this->get('/nuestros-productos/categoria-que-no-existe')->assertStatus(404);
    }

    /**
     * El dropdown del navbar solo lista categorías con al menos un
     * producto activo (deja fuera las que solo tienen inactivos o vacías).
     */
    public function test_nav_dropdown_lists_only_categories_with_active_products(): void
    {
        $conProductos = Categoria::create(['nombre' => 'Filtros']);
        Producto::factory()->create(['categoria_id' => $conProductos->id, 'activo' => true]);

        $soloInactivos = Categoria::create(['nombre' => 'Suspensión']);
        Producto::factory()->create(['categoria_id' => $soloInactivos->id, 'activo' => false]);

        $vacia = Categoria::create(['nombre' => 'Correas y Cadenas']);

        $response = $this->get('/');

        $response->assertSee('Todos los productos', false);
        $response->assertSee(route('publica.productos.categoria', 'filtros'), false);
        $response->assertDontSee(route('publica.productos.categoria', 'suspension'), false);
        $response->assertDontSee(route('publica.productos.categoria', 'correas-y-cadenas'), false);
    }
}
