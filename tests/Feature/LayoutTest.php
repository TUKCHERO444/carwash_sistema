<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 4.1 El layout renderiza sin errores: GET /dashboard devuelve HTTP 200.
     */
    public function test_dashboard_returns_http_200(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertStatus(200);
    }

    /**
     * 4.2 @yield('content') inyecta el contenido correcto:
     * el HTML contiene "Dashboard" y "Bienvenido".
     */
    public function test_content_section_is_injected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('Dashboard');
        $response->assertSee('Bienvenido');
    }

    /**
     * 4.3 Atributo lang presente: el HTML contiene <html lang=".
     */
    public function test_html_lang_attribute_is_present(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertSee('<html lang="', false);
    }

    /**
     * 4.4 Meta viewport presente: el HTML contiene name="viewport".
     */
    public function test_meta_viewport_is_present(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertSee('name="viewport"', false);
    }

    /**
     * 4.5 Atributos ARIA presentes: el HTML contiene los aria-label
     * de la navegación principal y la navegación móvil.
     */
    public function test_aria_labels_are_present(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('aria-label="Navegación principal"', false);
        $response->assertSee('aria-label="Navegación móvil"', false);
    }

    /**
     * 4.6 Sin referencias a CDN de Tailwind: el HTML no contiene
     * cdn.tailwindcss.com ni unpkg.com/tailwindcss.
     */
    public function test_no_tailwind_cdn_references(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertDontSee('cdn.tailwindcss.com', false);
        $response->assertDontSee('unpkg.com/tailwindcss', false);
    }

    /**
     * 4.7 Enlace activo diferenciado: para la ruta /dashboard,
     * el HTML contiene la clase bg-gray-100 (clase de enlace activo del sidebar).
     */
    public function test_active_link_has_bg_gray_100_class(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertSee('bg-gray-100');
    }
}
