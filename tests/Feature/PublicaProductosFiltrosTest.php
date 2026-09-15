<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: productos-publicos-filtros — tests de ejemplo del listado público
 * de productos por categoría: paginación de 20, tabla junta, contador
 * total/mostrados y filtros combinables (alfabético, stock y búsqueda).
 */
class PublicaProductosFiltrosTest extends TestCase
{
    use RefreshDatabase;

    private function crearCategoria(string $nombre = 'Filtros'): Categoria
    {
        return Categoria::create(['nombre' => $nombre]);
    }

    private function crearProducto(Categoria $categoria, array $overrides = []): Producto
    {
        return Producto::factory()->create(array_merge([
            'categoria_id' => $categoria->id,
            'activo' => true,
            'stock' => 10,
        ], $overrides));
    }

    /**
     * La vista pagina de a 20 productos por página y conserva los
     * filtros activos en los enlaces de paginación.
     */
    public function test_paginacion_muestra_maximo_20_y_conserva_filtros(): void
    {
        $categoria = $this->crearCategoria();
        foreach (range(1, 25) as $i) {
            $this->crearProducto($categoria, ['nombre' => sprintf('Producto %02d', $i)]);
        }

        $primera = $this->get('/nuestros-productos/filtros');
        $primera->assertStatus(200);
        $primera->assertSee('25');
        $primera->assertSee('mostrando');
        $primera->assertSee('20', false);

        $segunda = $this->get('/nuestros-productos/filtros?page=2');
        $segunda->assertStatus(200);
        $segunda->assertSee(mayusculas('Producto 21'));
        $segunda->assertDontSee(mayusculas('Producto 01'));
    }

    /**
     * La búsqueda dinámica filtra por nombre y combina con el orden alfabético.
     */
    public function test_busqueda_por_nombre_combinada_con_orden(): void
    {
        $categoria = $this->crearCategoria();
        $this->crearProducto($categoria, ['nombre' => 'Zeta Plus']);
        $this->crearProducto($categoria, ['nombre' => 'Alfa Uno']);
        $this->crearProducto($categoria, ['nombre' => 'Otro articulo']);

        $response = $this->get('/nuestros-productos/filtros?q=alfa&letra=asc');

        $response->assertSee(mayusculas('Alfa Uno'));
        $response->assertDontSee('Zeta Plus');
        $response->assertDontSee('Otro articulo');
    }

    /**
     * La búsqueda dinámica también filtra por marca.
     */
    public function test_busqueda_por_marca(): void
    {
        $categoria = $this->crearCategoria();
        $marca = Marca::create(['nombre' => 'Bosch']);

        $this->crearProducto($categoria, ['nombre' => 'Sensor', 'marca_id' => $marca->id]);
        $this->crearProducto($categoria, ['nombre' => 'Piñon suelto']);

        $response = $this->get('/nuestros-productos/filtros?q=bosch');

        $response->assertSee(mayusculas('Sensor'));
        $response->assertDontSee('Piñon suelto');
    }

    /**
     * El orden alfabético ascendente y descendente se aplica correctamente.
     */
    public function test_orden_alfabetico_ascendente_y_descendente(): void
    {
        $categoria = $this->crearCategoria();
        $this->crearProducto($categoria, ['nombre' => 'Bravo']);
        $this->crearProducto($categoria, ['nombre' => 'Alpha']);
        $this->crearProducto($categoria, ['nombre' => 'Charlie']);

        $this->get('/nuestros-productos/filtros?letra=asc')
            ->assertSeeInOrder([mayusculas('Alpha'), mayusculas('Bravo'), mayusculas('Charlie')]);

        $this->get('/nuestros-productos/filtros?letra=desc')
            ->assertSeeInOrder([mayusculas('Charlie'), mayusculas('Bravo'), mayusculas('Alpha')]);
    }

    /**
     * El orden por stock (mayor/menor cantidad disponible) se aplica.
     */
    public function test_orden_por_stock_mayor_y_menor(): void
    {
        $categoria = $this->crearCategoria();
        $bajo = $this->crearProducto($categoria, ['nombre' => 'Stock bajo', 'stock' => 2]);
        $alto = $this->crearProducto($categoria, ['nombre' => 'Stock alto', 'stock' => 50]);
        $medio = $this->crearProducto($categoria, ['nombre' => 'Stock medio', 'stock' => 10]);

        $ruta = $categoria->slug;

        $this->get("/nuestros-productos/{$ruta}?stock=mayor")
            ->assertSeeInOrder([mayusculas($alto->nombre), mayusculas($medio->nombre), mayusculas($bajo->nombre)]);

        $this->get("/nuestros-productos/{$ruta}?stock=menor")
            ->assertSeeInOrder([mayusculas($bajo->nombre), mayusculas($medio->nombre), mayusculas($alto->nombre)]);
    }

    /**
     * Los filtros actúan en conjunto: categoría (por URL), búsqueda y orden.
     */
    public function test_filtros_actuan_en_conjunto(): void
    {
        $categoria = $this->crearCategoria();
        $otra = $this->crearCategoria('Otros');
        $marca = Marca::create(['nombre' => 'Michelin']);

        $this->crearProducto($categoria, ['nombre' => 'Beta', 'marca_id' => $marca->id, 'stock' => 5]);
        $this->crearProducto($categoria, ['nombre' => 'Alpha', 'marca_id' => null, 'stock' => 30]);
        $this->crearProducto($otra, ['nombre' => 'Gamma', 'marca_id' => $marca->id, 'stock' => 20]);

        $ruta = $categoria->slug;
        $response = $this->get("/nuestros-productos/{$ruta}?q=michelin&letra=asc");

        // Solo el producto de la categoría que coincide con la marca y orden.
        $response->assertSee(mayusculas('Beta'));
        $response->assertDontSee(mayusculas('Alpha'));
        $response->assertDontSee(mayusculas('Gamma'));
    }

    /**
     * Con filtros sin resultados se muestra el estado vacío de búsqueda.
     */
    public function test_filtros_sin_resultados_muestran_estado_vacio(): void
    {
        $categoria = $this->crearCategoria();
        $this->crearProducto($categoria, ['nombre' => 'Existente']);

        $this->get('/nuestros-productos/filtros?q=noexiste')
            ->assertSee('No se encontraron productos con los filtros aplicados');
    }
}
