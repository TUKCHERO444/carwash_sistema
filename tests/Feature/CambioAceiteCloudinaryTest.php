<?php

namespace Tests\Feature;

use App\Models\CambioAceite;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\User;
use Cloudinary\Api\ApiResponse;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CambioAceiteCloudinaryTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear permisos necesarios
        Permission::firstOrCreate(['name' => 'acceso-ventas', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['acceso-ventas']);
    }

    /** @test */
    public function it_uploads_image_to_cloudinary_when_creating_a_cambio_aceite()
    {
        $this->actingAs($this->user);

        $producto = Producto::factory()->create(['precio_venta' => 40, 'stock' => 10]);
        $trabajador = Trabajador::factory()->create();

        $mockResponse = \Mockery::mock(ApiResponse::class);
        $mockResponse->shouldReceive('offsetGet')->with('secure_url')->andReturn('https://res.cloudinary.com/test/image/upload/v1/cambio.jpg');

        Cloudinary::shouldReceive('uploadApi->upload')
            ->once()
            ->andReturn($mockResponse);

        $file = UploadedFile::fake()->create('cambio.jpg', 100);

        $data = [
            'placa' => 'ABC-123',
            'fecha' => now()->format('Y-m-d'),
            'foto' => $file,
            'trabajadores_ids' => [$trabajador->id],
            'productos' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 2,
                    'precio' => 40,
                    'total' => 80,
                ],
            ],
        ];

        $response = $this->post(route('cambio-aceite.store'), $data);

        $response->assertRedirect(route('cambio-aceite.index'));

        $this->assertDatabaseHas('cambio_aceites', [
            'foto' => 'https://res.cloudinary.com/test/image/upload/v1/cambio.jpg',
            'precio' => 80,
        ]);

        $this->assertEquals(8, $producto->fresh()->stock);
    }

    /** @test */
    public function it_deletes_old_cloudinary_image_when_updating_cambio_aceite_with_new_one()
    {
        $this->actingAs($this->user);

        $oldUrl = 'https://res.cloudinary.com/test/image/upload/v1/old_cambio.jpg';
        $cambio = CambioAceite::factory()->state(['estado' => 'confirmado'])->create(['foto' => $oldUrl]);
        $producto = Producto::factory()->create();
        $trabajador = Trabajador::factory()->create();

        // Mock Delete
        $mockDestroyResponse = \Mockery::mock(ApiResponse::class);
        Cloudinary::shouldReceive('uploadApi->destroy')
            ->once()
            ->with('old_cambio')
            ->andReturn($mockDestroyResponse);

        // Mock Upload
        $mockResponse = \Mockery::mock(ApiResponse::class);
        $mockResponse->shouldReceive('offsetGet')->with('secure_url')->andReturn('https://res.cloudinary.com/test/image/upload/v2/new_cambio.jpg');

        Cloudinary::shouldReceive('uploadApi->upload')
            ->once()
            ->andReturn($mockResponse);

        $file = UploadedFile::fake()->create('new.jpg', 100);

        $data = [
            'placa' => 'XYZ-789',
            'fecha' => now()->format('Y-m-d'),
            'foto' => $file,
            'trabajadores_ids' => [$trabajador->id],
            'precio' => 100,
            'total' => 90,
            'metodo_pago' => 'efectivo',
            'productos' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 1,
                    'precio' => 100,
                    'total' => 100,
                ],
            ],
        ];

        $response = $this->put(route('cambio-aceite.update', $cambio), $data);

        $response->assertRedirect(route('cambio-aceite.show', $cambio));

        $this->assertDatabaseHas('cambio_aceites', [
            'id' => $cambio->id,
            'foto' => 'https://res.cloudinary.com/test/image/upload/v2/new_cambio.jpg',
        ]);
    }

    /** @test */
    public function it_deletes_cloudinary_image_when_destroying_cambio_aceite()
    {
        $this->actingAs($this->user);

        $url = 'https://res.cloudinary.com/test/image/upload/v1/to_delete_aceite.jpg';
        $cambio = CambioAceite::factory()->create(['foto' => $url]);
        $producto = Producto::factory()->create();
        $cambio->productos()->attach($producto, ['cantidad' => 1, 'precio' => 10, 'total' => 10]);

        $mockDestroyResponse = \Mockery::mock(ApiResponse::class);
        Cloudinary::shouldReceive('uploadApi->destroy')
            ->once()
            ->with('to_delete_aceite')
            ->andReturn($mockDestroyResponse);

        $response = $this->delete(route('cambio-aceite.destroy', $cambio));

        $response->assertRedirect(route('cambio-aceite.index'));
        $this->assertDatabaseMissing('cambio_aceites', ['id' => $cambio->id]);
    }
}
