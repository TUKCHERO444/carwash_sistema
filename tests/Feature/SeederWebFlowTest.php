<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del flujo de llegada de productos a la web.
 *
 * Este test fija el contrato de siembra: `DatabaseSeeder` debe ejecutar
 * `CategoriaSeeder` DESPUÉS de `ProductoSeeder` (CategoriaSeeder asigna a
 * cada producto su categoría por palabras clave del nombre). Si el orden se
 * invierte, los productos quedan sin `categoria_id` y la web pública se ve
 * vacía (dropdown sin categorías y mosaico sin tiles).
 */
class SeederWebFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tras correr la siembra completa, todos los productos quedan catalogados
     * y todas las categorías quedan publicadas en el flujo web (navbar +
     * mosaico).
     */
    public function test_seed_deja_todos_los_productos_con_categoria(): void
    {
        $this->seed();

        $sinCategoria = Producto::whereNull('categoria_id')->count();
        $this->assertSame(0, $sinCategoria, 'Todo producto sembrado debe tener categoria_id asignado.');

        $this->assertSame(8, Categoria::count());

        $slugs = Categoria::pluck('slug');
        $this->assertTrue($slugs->every(fn ($slug) => is_string($slug) && $slug !== ''), 'Toda categoría debe tener slug no vacío.');
        $this->assertTrue($slugs->unique()->count() === $slugs->count(), 'Los slugs de categorías deben ser únicos.');
    }

    /**
     * Con los datos sembrados, el dropdown del navbar y el mosaico general
     * listan las 8 categorías con productos activos (mismo query del
     * AppServiceProvider), y la página pública de productos renderiza sus
     * tiles.
     */
    public function test_seed_publica_las_categorias_en_el_flujo_web(): void
    {
        $this->seed();

        $categoriasMenu = Categoria::whereHas('productos', fn ($q) => $q->where('activo', true))
            ->get();

        $this->assertSame(8, $categoriasMenu->count(), 'El dropdown/mosaico debe listar las 8 categorías.');

        $this->get('/nuestros-productos')
            ->assertStatus(200)
            ->assertSee('VER MÁS');
        $this->assertStringNotContainsString(
            'Aún no tenemos categorías con productos disponibles.',
            $this->get('/nuestros-productos')->getContent()
        );
    }

    /**
     * El mosaico enlaza cada tile a su página de categoría (compendio con
     * botón que redirecciona).
     */
    public function test_seed_mosaico_enlaza_tiles_a_sus_categorias(): void
    {
        $this->seed();

        $response = $this->get('/nuestros-productos');
        $response->assertStatus(200);

        foreach (Categoria::pluck('slug') as $slug) {
            $response->assertSee(route('publica.productos.categoria', ['categoria' => $slug]), false);
        }
    }
}
