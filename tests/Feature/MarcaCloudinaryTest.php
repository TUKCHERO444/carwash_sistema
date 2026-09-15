<?php

namespace Tests\Feature;

use App\Models\Marca;
use App\Models\User;
use Cloudinary\Api\ApiResponse;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MarcaCloudinaryTest extends TestCase
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
    public function it_uploads_image_to_cloudinary_when_creating_a_marca()
    {
        // Mock Cloudinary
        $mockResponse = \Mockery::mock(ApiResponse::class);
        $mockResponse->shouldReceive('offsetGet')
            ->with('secure_url')
            ->andReturn('https://res.cloudinary.com/drm3cfpnm/image/upload/v1/test_brand.jpg');

        Cloudinary::shouldReceive('uploadApi->upload')
            ->once()
            ->andReturn($mockResponse);

        $file = UploadedFile::fake()->create('marca.jpg', 100);

        $response = $this->post(route('marcas.store'), [
            'nombre' => 'Marca de Prueba',
            'descripcion' => 'Descripcion de prueba',
            'foto' => $file,
        ]);

        $response->assertRedirect(route('marcas.index'));

        $this->assertDatabaseHas('marcas', [
            'nombre' => 'Marca de Prueba',
            'foto' => 'https://res.cloudinary.com/drm3cfpnm/image/upload/v1/test_brand.jpg',
        ]);

        $marca = Marca::first();
        $this->assertEquals('https://res.cloudinary.com/drm3cfpnm/image/upload/v1/test_brand.jpg', $marca->foto_url);
    }

    /** @test */
    public function it_creates_a_marca_without_foto()
    {
        $response = $this->post(route('marcas.store'), [
            'nombre' => 'Marca Sin Foto',
            'descripcion' => null,
        ]);

        $response->assertRedirect(route('marcas.index'));

        $this->assertDatabaseHas('marcas', [
            'nombre' => 'Marca Sin Foto',
            'foto' => null,
        ]);
    }

    /** @test */
    public function it_deletes_old_cloudinary_image_when_updating_with_new_one()
    {
        $oldUrl = 'https://res.cloudinary.com/drm3cfpnm/image/upload/v1/old_brand.jpg';
        $marca = Marca::create(['nombre' => 'Marca Original', 'foto' => $oldUrl]);

        // Mock Cloudinary: destroy old
        $destroyResponse = \Mockery::mock(ApiResponse::class);
        Cloudinary::shouldReceive('uploadApi->destroy')
            ->once()
            ->with('old_brand')
            ->andReturn($destroyResponse);

        // Mock Cloudinary: upload new
        $uploadResponse = \Mockery::mock(ApiResponse::class);
        $uploadResponse->shouldReceive('offsetGet')
            ->with('secure_url')
            ->andReturn('https://res.cloudinary.com/drm3cfpnm/image/upload/v2/new_brand.jpg');

        Cloudinary::shouldReceive('uploadApi->upload')
            ->once()
            ->andReturn($uploadResponse);

        $file = UploadedFile::fake()->create('new.jpg', 100);

        $response = $this->put(route('marcas.update', $marca), [
            'nombre' => 'Marca Actualizada',
            'descripcion' => null,
            'foto' => $file,
        ]);

        $this->assertDatabaseHas('marcas', [
            'id' => $marca->id,
            'nombre' => 'Marca Actualizada',
            'foto' => 'https://res.cloudinary.com/drm3cfpnm/image/upload/v2/new_brand.jpg',
        ]);
    }

    /** @test */
    public function it_keeps_previous_foto_when_updating_without_new_image()
    {
        $url = 'https://res.cloudinary.com/drm3cfpnm/image/upload/v1/keep_brand.jpg';
        $marca = Marca::create(['nombre' => 'Marca Original', 'foto' => $url]);

        $response = $this->put(route('marcas.update', $marca), [
            'nombre' => 'Marca Renombrada',
            'descripcion' => 'Nueva descripcion',
        ]);

        $this->assertDatabaseHas('marcas', [
            'id' => $marca->id,
            'nombre' => 'Marca Renombrada',
            'foto' => $url,
        ]);
    }

    /** @test */
    public function it_deletes_cloudinary_image_when_destroying_marca()
    {
        $url = 'https://res.cloudinary.com/drm3cfpnm/image/upload/v1/to_delete_brand.jpg';
        $marca = Marca::create(['nombre' => 'Marca Borrable', 'foto' => $url]);

        // Mock Cloudinary: destroy
        $destroyResponse = \Mockery::mock(ApiResponse::class);
        Cloudinary::shouldReceive('uploadApi->destroy')
            ->once()
            ->with('to_delete_brand')
            ->andReturn($destroyResponse);

        $response = $this->delete(route('marcas.destroy', $marca));

        $response->assertRedirect(route('marcas.index'));
        $this->assertDatabaseMissing('marcas', ['id' => $marca->id]);
    }
}
