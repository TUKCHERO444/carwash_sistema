<?php

namespace Tests\Feature;

use App\Models\ContenidoWeb;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicaServiciosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La página pública de servicios responde HTTP 200 en GET /nuestros-servicios.
     */
    public function test_servicios_returns_http_200(): void
    {
        $this->get('/nuestros-servicios')->assertStatus(200);
    }

    /**
     * La vista muestra el titular, el breadcrumb y el encabezado de la sección.
     */
    public function test_servicios_renders_heading_and_breadcrumb(): void
    {
        $response = $this->get('/nuestros-servicios');

        $response->assertSee('Nuestros servicios');
        $response->assertSee('Inicio');
        $response->assertSee('¿Qué necesita tu auto?');
    }

    /**
     * Feature: servicios-web, Property 1: solo muestra activos ordenados con precio.
     */
    public function test_servicios_muestra_solo_activos_ordenados_con_precio(): void
    {
        Servicio::factory()->create(['nombre' => 'Cambio de aceite', 'orden' => 2, 'precio' => 45.00, 'activo' => true]);
        Servicio::factory()->create(['nombre' => 'Lavado general', 'orden' => 1, 'precio' => 25.00, 'activo' => true]);
        Servicio::factory()->create(['nombre' => 'Retiro de manchas', 'orden' => 0, 'precio' => 999.00, 'activo' => false]);

        $response = $this->get('/nuestros-servicios');

        $response->assertStatus(200);
        $response->assertSeeInOrder(['LAVADO GENERAL', 'CAMBIO DE ACEITE']);
        $response->assertSee('S/ 25.00');
        $response->assertSee('S/ 45.00');
        $response->assertDontSee('Retiro de manchas');
    }

    /**
     * Feature: servicios-web, Propiedad 1: estado vacío sin servicios activos.
     */
    public function test_servicios_estado_vacio_sin_activos(): void
    {
        Servicio::factory()->create(['activo' => false]);

        $this->get('/nuestros-servicios')
            ->assertStatus(200)
            ->assertSee('Aún no tenemos servicios disponibles.');
    }

    /**
     * Feature: servicios-web, Requisito 8: el título proviene del panel de contenidos.
     */
    public function test_servicios_usar_titulo_desde_contenido_web(): void
    {
        ContenidoWeb::create([
            'clave' => 'servicios_titulo',
            'valor' => 'Cuidado con especialistas',
            'tipo' => 'string',
        ]);

        $this->get('/nuestros-servicios')
            ->assertStatus(200)
            ->assertSee('Cuidado con especialistas');
    }

    /**
     * El enlace "Servicios" de la navegación apunta a la ruta pública /servicios.
     */
    public function test_nav_servicios_links_to_public_route(): void
    {
        $this->get('/')->assertSee(route('publica.servicios'), false);
    }
}
