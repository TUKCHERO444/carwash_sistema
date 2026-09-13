<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicaProductoDetalleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea una categoría, una marca y un producto activo para el detalle.
     */
    private function crearCategoriaConProducto(array $override = []): array
    {
        $categoria = Categoria::create([
            'nombre' => $override['categoria'] ?? 'Frenos',
            'descripcion' => 'Frenos para tu auto.',
        ]);

        $producto = Producto::factory()->create(array_merge([
            'categoria_id' => $categoria->id,
            'marca_id' => null,
            'activo' => true,
            'nombre' => 'Pastillas de freno Premium',
            'descripcion' => 'Pastillas de alto rendimiento para frenado seguro.',
            'precio_venta' => 89.9,
            'foto' => null,
        ], $override['producto'] ?? []));

        return [$categoria, $producto];
    }

    /**
     * El detalle de un producto activo responde HTTP 200 con marca, precio,
     * descripción, disponibilidad, breadcrumb y CTA de cotización.
     */
    public function test_detalle_de_producto_activo_muestra_toda_su_informacion(): void
    {
        [$categoria, $producto] = $this->crearCategoriaConProducto();

        $response = $this->get(route('publica.productos.detalle', ['categoria' => $categoria, 'producto' => $producto]));

        $response->assertStatus(200);
        $response->assertSee(mayusculas($producto->nombre));
        $response->assertSee($producto->descripcion);
        $response->assertSee('S/ 89.90');
        $response->assertSeeInOrder(['Inicio', '/', 'Productos'], false);
        $response->assertSee('Disponible');
        $response->assertSee('Cotizar este producto');
        $response->assertSee(route('publica.cotiza'), false);
        $response->assertSee(route('publica.productos.categoria', ['categoria' => $categoria]), false);
    }

    /**
     * Un producto sin stock muestra el sello/sello "Agotado" y no ofrece cotizar.
     */
    public function test_detalle_de_producto_agotado_muestra_estado_y_bloquea_el_cta(): void
    {
        [$categoria] = $this->crearCategoriaConProducto([
            'producto' => ['stock' => 0, 'inventario' => 10],
        ]);
        $producto = $categoria->productos()->first();

        $response = $this->get(route('publica.productos.detalle', ['categoria' => $categoria, 'producto' => $producto]));

        $response->assertStatus(200);
        $response->assertSee('Agotado');
        $response->assertSee('Sin stock disponible');
        $response->assertDontSee('Cotizar este producto');
    }

    /**
     * Un producto activo de otra categoría responde HTTP 404 (no se expone
     * fuera de su colección).
     */
    public function test_detalle_devuelve_404_si_el_producto_no_pertenece_a_la_categoria(): void
    {
        $categoriaA = Categoria::create(['nombre' => 'Frenos']);
        $categoriaB = Categoria::create(['nombre' => 'Filtros']);
        $producto = Producto::factory()->create([
            'categoria_id' => $categoriaA->id,
            'activo' => true,
        ]);

        $this->get(route('publica.productos.detalle', ['categoria' => $categoriaB, 'producto' => $producto]))
            ->assertStatus(404);
    }

    /**
     * Un producto inactivo (oculto del panel) responde HTTP 404.
     */
    public function test_detalle_devuelve_404_si_el_producto_esta_inactivo(): void
    {
        [$categoria] = $this->crearCategoriaConProducto([
            'producto' => ['activo' => false],
        ]);
        $producto = $categoria->productos()->first();

        $this->get(route('publica.productos.detalle', ['categoria' => $categoria, 'producto' => $producto]))
            ->assertStatus(404);
    }

    /**
     * Un id de producto inexistente responde HTTP 404.
     */
    public function test_detalle_devuelve_404_si_el_producto_no_existe(): void
    {
        [$categoria] = $this->crearCategoriaConProducto();

        $this->get('/nuestros-productos/'.$categoria->slug.'/99999')->assertStatus(404);
    }

    /**
     * El detalle sugiere otros productos activos de la misma categoría y
     * excluye el actual de los relacionados.
     */
    public function test_detalle_muestra_relacionados_de_la_misma_categoria(): void
    {
        $categoria = Categoria::create(['nombre' => 'Frenos']);
        $principal = Producto::factory()->create([
            'categoria_id' => $categoria->id,
            'activo' => true,
            'nombre' => 'Principal único',
        ]);

        $rel1 = Producto::factory()->create(['categoria_id' => $categoria->id, 'activo' => true, 'nombre' => 'Relacionado Uno']);
        $rel2 = Producto::factory()->create(['categoria_id' => $categoria->id, 'activo' => true, 'nombre' => 'Relacionado Dos']);
        Producto::factory()->create(['categoria_id' => $categoria->id, 'activo' => false, 'nombre' => 'Oculto']);
        $otra = Categoria::create(['nombre' => 'Filtros']);
        Producto::factory()->create(['categoria_id' => $otra->id, 'activo' => true, 'nombre' => 'De otra categoría']);

        $response = $this->get(route('publica.productos.detalle', ['categoria' => $categoria, 'producto' => $principal]));

        $response->assertStatus(200);
        $response->assertSee('También te puede interesar');
        $response->assertSee('RELACIONADO UNO');
        $response->assertSee('RELACIONADO DOS');
        $response->assertDontSee('Oculto');
        $response->assertDontSee('De otra categoría');
    }

    /**
     * La card del listado de categoría enlaza al detalle de cada producto.
     */
    public function test_card_del_listado_enlaza_al_detalle(): void
    {
        [$categoria] = $this->crearCategoriaConProducto();
        $producto = $categoria->productos()->first();

        $ruta = route('publica.productos.detalle', ['categoria' => $categoria, 'producto' => $producto]);

        $this->get(route('publica.productos.categoria', ['categoria' => $categoria]))
            ->assertStatus(200)
            ->assertSee('VER DETALLE')
            ->assertSee($ruta, false);
    }

    /**
     * El mosaico usa la foto del primer producto activo de la categoría como
     * imagen de portada del tile (con gradiente de respaldo si no hay foto).
     */
    public function test_mosaico_usa_la_foto_del_producto_como_imagen_del_tile(): void
    {
        $categoria = Categoria::create(['nombre' => 'Neumáticos']);
        $producto = Producto::factory()->create([
            'categoria_id' => $categoria->id,
            'activo' => true,
            'foto' => 'fotos/neumatico.png',
        ]);

        $response = $this->get('/nuestros-productos');

        $response->assertStatus(200);
        $response->assertSee($producto->foto_url, false);
    }
}
