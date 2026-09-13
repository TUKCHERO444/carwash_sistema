<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicaQuienesSomosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La página pública "¿Quiénes somos?" responde HTTP 200 en GET /quienes-somos.
     */
    public function test_quienes_somos_returns_http_200(): void
    {
        $this->get('/quienes-somos')->assertStatus(200);
    }

    /**
     * La vista muestra el titular, el breadcrumb y las secciones principales.
     */
    public function test_quienes_somos_renders_heading_and_breadcrumb(): void
    {
        $response = $this->get('/quienes-somos');

        $response->assertSee('¿Quiénes somos?');
        $response->assertSee('Inicio');
        $response->assertSee('Nuestra misión');
        $response->assertSee('Nuestra visión');
        $response->assertSee('Nuestros valores');
    }

    /**
     * La sección de valores renderiza las tarjetas placeholder.
     */
    public function test_quienes_somos_renders_valores(): void
    {
        $response = $this->get('/quienes-somos');

        foreach (['Calidad', 'Honestidad', 'Puntualidad', 'Cercanía'] as $titulo) {
            $response->assertSee($titulo);
        }
    }

    /**
     * El enlace "¿Quiénes somos?" de la navegación apunta a la ruta pública /quienes-somos.
     */
    public function test_nav_quienes_somos_links_to_public_route(): void
    {
        $this->get('/')->assertSee(route('publica.quienes-somos'), false);
    }
}
