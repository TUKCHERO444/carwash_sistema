<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Spatie\Permission\Models\Permission;

class ProductoCloudinaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear permisos necesarios
        Permission::create(['name' => 'acceso-inventario']);
        
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-inventario');
        
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_uploads_image_to_cloudinary_when_creating_a_producto()
    {
        // Mock Cloudinary
        $mockResponse = \Mockery::mock(\Cloudinary\Api\ApiResponse::class);
        $mockResponse->shouldReceive('offsetGet')
            ->with('secure_url')
            ->andReturn('https://res.cloudinary.com/drm3cfpnm/image/upload/v1/test_product.jpg');

        Cloudinary::shouldReceive('uploadApi->upload')
            ->once()
            ->andReturn($mockResponse);

        $file = UploadedFile::fake()->create('producto.jpg', 100);

        $response = $this->post(route('productos.store'), [
            'nombre' => 'Producto de Prueba',
            'precio_compra' => 10,
            'precio_venta' => 20,
            'stock' => 100,
            'inventario' => 100,
            'activo' => 1,
            'foto' => $file,
            'categoria_id' => null,
        ]);

        $response->assertRedirect(route('productos.index'));
        
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Producto de Prueba',
            'foto' => 'https://res.cloudinary.com/drm3cfpnm/image/upload/v1/test_product.jpg'
        ]);

        $producto = Producto::first();
        $this->assertEquals('https://res.cloudinary.com/drm3cfpnm/image/upload/v1/test_product.jpg', $producto->foto_url);
    }

    /** @test */
    public function it_deletes_old_cloudinary_image_when_updating_with_new_one()
    {
        $oldUrl = 'https://res.cloudinary.com/drm3cfpnm/image/upload/v1/old_image.jpg';
        $producto = Producto::factory()->create(['foto' => $oldUrl]);

        // Mock Cloudinary: destroy old
        $destroyResponse = \Mockery::mock(\Cloudinary\Api\ApiResponse::class);
        Cloudinary::shouldReceive('uploadApi->destroy')
            ->once()
            ->with('old_image')
            ->andReturn($destroyResponse);

        // Mock Cloudinary: upload new
        $uploadResponse = \Mockery::mock(\Cloudinary\Api\ApiResponse::class);
        $uploadResponse->shouldReceive('offsetGet')
            ->with('secure_url')
            ->andReturn('https://res.cloudinary.com/drm3cfpnm/image/upload/v2/new_image.jpg');

        Cloudinary::shouldReceive('uploadApi->upload')
            ->once()
            ->andReturn($uploadResponse);

        $file = UploadedFile::fake()->create('new.jpg', 100);

        $response = $this->put(route('productos.update', $producto), [
            'nombre' => 'Producto Actualizado',
            'precio_compra' => 10,
            'precio_venta' => 20,
            'stock' => 100,
            'inventario' => 100,
            'activo' => 1,
            'foto' => $file,
            'categoria_id' => null,
        ]);

        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'foto' => 'https://res.cloudinary.com/drm3cfpnm/image/upload/v2/new_image.jpg'
        ]);
    }

    /** @test */
    public function it_deletes_cloudinary_image_when_destroying_producto()
    {
        $url = 'https://res.cloudinary.com/drm3cfpnm/image/upload/v1/to_delete.jpg';
        $producto = Producto::factory()->create(['foto' => $url]);

        // Mock Cloudinary: destroy
        $destroyResponse = \Mockery::mock(\Cloudinary\Api\ApiResponse::class);
        Cloudinary::shouldReceive('uploadApi->destroy')
            ->once()
            ->with('to_delete')
            ->andReturn($destroyResponse);

        $response = $this->delete(route('productos.destroy', $producto));

        $response->assertRedirect(route('productos.index'));
        $this->assertDatabaseMissing('productos', ['id' => $producto->id]);
    }
}
