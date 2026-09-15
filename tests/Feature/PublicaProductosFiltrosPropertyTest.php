<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: productos-publicos-filtros
 * Tests de propiedad del listado público de productos por categoría:
 * paginación de 20, filtros combinables y consistencia del conteo.
 */
class PublicaProductosFiltrosPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 1: Para cualquier cantidad N > 20 de productos activos, la
     * paginación nunca excede 20 elementos por página y el conteo total
     * de todas las páginas iguala N.
     */
    public function test_property_1_paginacion_nunca_excede_20(): void
    {
        $categoria = Categoria::create(['nombre' => 'Property Cat']);

        for ($iter = 0; $iter < 100; $iter++) {
            $cantidad = rand(21, 60);

            Producto::factory()->count($cantidad)->create([
                'categoria_id' => $categoria->id,
                'activo' => true,
            ]);

            $totalRecuperados = 0;
            $pagina = 1;

            while (true) {
                $res = $this->get("/nuestros-productos/{$categoria->slug}?page={$pagina}");
                $res->assertStatus(200);

                $body = $res->getContent();
                $enPagina = substr_count($body, 'VER DETALLE');

                if ($enPagina === 0) {
                    break;
                }

                $this->assertLessThanOrEqual(20, $enPagina, "Página {$pagina} excede 20 (iter {$iter}, cantidad {$cantidad})");
                $totalRecuperados += $enPagina;
                $pagina++;
            }

            $this->assertSame($cantidad, $totalRecuperados, "Total recuperado no coincide en iter {$iter}");

            // Limpieza para siguiente iteración (evitar conteos acumulados).
            Producto::where('categoria_id', $categoria->id)->delete();
        }
    }

    /**
     * Property 2: Para cualquier subcadena del nombre de un producto existente,
     * la búsqueda por esa subcadena (q) incluye al menos ese producto.
     */
    public function test_property_2_busqueda_incluye_match(): void
    {
        $categoria = Categoria::create(['nombre' => 'Property Cat B']);

        for ($iter = 0; $iter < 100; $iter++) {
            $nombreUnico = "Propiedad {$iter} ".uniqid();
            $producto = Producto::factory()->create([
                'categoria_id' => $categoria->id,
                'activo' => true,
                'nombre' => $nombreUnico,
            ]);

            // Subcadena aleatoria: primeros 3+ caracteres del nombre.
            $longSub = min(strlen($nombreUnico), rand(3, 6));
            $subcadena = substr($nombreUnico, 0, $longSub);

            $res = $this->get("/nuestros-productos/{$categoria->slug}?q=".urlencode($subcadena));
            $res->assertStatus(200);
            $res->assertSee(mayusculas($nombreUnico));

            // Limpieza.
            Producto::where('categoria_id', $categoria->id)->delete();
        }
    }

    /**
     * Property 3: Para cualquier combinación válida de filtros (letra + stock),
     * la página responde 200 y el conteo de cards no excede 20.
     */
    public function test_property_3_filtros_validos_retornan_max_20(): void
    {
        $categoria = Categoria::create(['nombre' => 'Property Cat C']);
        $letras = ['', 'asc', 'desc'];
        $stocks = ['', 'mayor', 'menor'];

        for ($iter = 0; $iter < 100; $iter++) {
            $cantidad = rand(25, 50);
            Producto::factory()->count($cantidad)->create([
                'categoria_id' => $categoria->id,
                'activo' => true,
                'stock' => rand(0, 100),
            ]);

            $letra = $letras[array_rand($letras)];
            $stock = $stocks[array_rand($stocks)];
            $params = http_build_query(array_filter(['letra' => $letra, 'stock' => $stock]));

            $res = $this->get("/nuestros-productos/{$categoria->slug}".($params ? "?{$params}" : ''));
            $res->assertStatus(200);

            $body = $res->getContent();
            $enPagina = substr_count($body, 'VER DETALLE');
            $this->assertLessThanOrEqual(20, $enPagina);

            Producto::where('categoria_id', $categoria->id)->delete();
        }
    }
}
