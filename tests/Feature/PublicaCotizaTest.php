<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicaCotizaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La página pública "Cotiza con nosotros" responde HTTP 200 en GET /cotiza.
     */
    public function test_cotiza_returns_http_200(): void
    {
        $this->get('/cotiza')->assertStatus(200);
    }

    /**
     * La vista muestra el titular, el breadcrumb y la descripción inicial.
     */
    public function test_cotiza_renders_heading_and_intro(): void
    {
        $response = $this->get('/cotiza');

        $response->assertSee('Cotiza con nosotros');
        $response->assertSee('Inicio');
        $response->assertSee('En Carwash El Chinito entendemos');
    }

    /**
     * El formulario de contacto renderiza los campos y el botón de envío.
     */
    public function test_cotiza_renders_contact_form(): void
    {
        $response = $this->get('/cotiza');

        foreach (['Tu nombre', 'Tu email', 'Celular', 'Tu mensaje', 'Enviar mensaje'] as $texto) {
            $response->assertSee($texto);
        }
    }

    /**
     * El enlace "Contacto" de la navegación apunta a la ruta pública /cotiza.
     */
    public function test_nav_contacto_links_to_public_route(): void
    {
        $this->get('/')->assertSee(route('publica.cotiza'), false);
    }
}
