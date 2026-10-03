<?php

namespace Tests\Feature\Cuenta;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Services\CarritoService;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Envío del carrito por WhatsApp (wa.me + PDF como enlace).
 */
class CarritoWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        Storage::fake('public');
        config()->set('services.whatsapp.pedidos', '942430960');
    }

    private function cliente(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status' => 'ACTIVE',
            'activo' => true,
        ]);
        $user->assignRole('Cliente');

        return $user;
    }

    private function producto(int $stock = 10, float $precio = 12.00): Producto
    {
        return Producto::factory()->create([
            'categoria_id' => Categoria::create(['nombre' => 'Cat '.uniqid()])->id,
            'stock' => $stock,
            'inventario' => $stock,
            'precio_venta' => $precio,
        ]);
    }

    public function test_numero_peruano_se_normaliza_con_51(): void
    {
        config()->set('services.whatsapp.pedidos', '942430960');

        $this->assertSame('51942430960', CarritoService::numeroWhatsApp());

        config()->set('services.whatsapp.pedidos', '51942430960');

        $this->assertSame('51942430960', CarritoService::numeroWhatsApp());

        config()->set('services.whatsapp.pedidos', null);

        $this->assertNull(CarritoService::numeroWhatsApp());
    }

    public function test_whatsapp_redirige_a_wa_me_con_resumen_y_pdf(): void
    {
        $user = $this->cliente();
        $producto = $this->producto();
        $this->actingAs($user);
        app(CarritoService::class)->agregar($user, $producto->id, 2);

        $respuesta = $this->actingAs($user)->get(route('cuenta.carrito.whatsapp'));

        $this->assertTrue(
            $respuesta->isRedirect() && str_starts_with((string) $respuesta->headers->get('Location'), 'https://wa.me/'),
            'Redirige a wa.me'
        );

        $url = $respuesta->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/51942430960?text=', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $texto = rawurldecode($query['text']);

        $this->assertStringContainsString($user->name, $texto);
        $this->assertStringContainsString($producto->nombre, $texto);
        $this->assertStringContainsString('S/ 24.00', $texto);
        $this->assertStringContainsString('.pdf', $texto);

        $archivos = Storage::disk('public')->files('carrito-pdfs');
        $this->assertCount(1, $archivos);
        $this->assertStringStartsWith("carrito-{$user->id}-", basename($archivos[0]));

        $this->assertDatabaseHas('registros_auditoria', ['modulo' => 'cuenta', 'accion' => 'carrito_whatsapp']);
    }

    public function test_whatsapp_regenera_un_solo_archivo_por_usuario(): void
    {
        $user = $this->cliente();
        $producto = $this->producto();
        $this->actingAs($user);
        app(CarritoService::class)->agregar($user, $producto->id, 1);

        $this->actingAs($user)->get(route('cuenta.carrito.whatsapp'));
        $this->actingAs($user)->get(route('cuenta.carrito.whatsapp'));

        $this->assertCount(1, Storage::disk('public')->files('carrito-pdfs'));
    }

    public function test_whatsapp_carrito_vacio_redirige_con_error(): void
    {
        $user = $this->cliente();

        $this->actingAs($user)->get(route('cuenta.carrito.whatsapp'))
            ->assertRedirect(route('cuenta.carrito.index'))
            ->assertSessionHasErrors('whatsapp');

        $this->assertCount(0, Storage::disk('public')->files('carrito-pdfs'));
    }

    public function test_whatsapp_guest_va_al_login(): void
    {
        $this->get(route('cuenta.carrito.whatsapp'))->assertRedirect(route('cuenta.login'));
    }

    public function test_boton_visible_solo_si_hay_numero(): void
    {
        $user = $this->cliente();
        $producto = $this->producto();
        $this->actingAs($user);
        app(CarritoService::class)->agregar($user, $producto->id, 1);

        $this->actingAs($user)->get(route('cuenta.carrito.index'))
            ->assertSee('Enviar por WhatsApp');

        config()->set('services.whatsapp.pedidos', null);

        $this->actingAs($user)->get(route('cuenta.carrito.index'))
            ->assertDontSee('Enviar por WhatsApp');
    }
}
