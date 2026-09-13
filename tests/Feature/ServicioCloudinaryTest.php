<?php

namespace Tests\Feature;

use App\Models\Servicio;
use App\Models\User;
use Cloudinary\Api\ApiResponse;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Gestión de imagen Cloudinary del servicio.
 * Feature: servicios-web
 */
class ServicioCloudinaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'acceso-servicios', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    /**
     * Feature: servicios-web, Property 3: store sube la imagen a Cloudinary y persiste la URL.
     */
    public function test_subir_imagen_al_crear_servicio(): void
    {
        $mockResponse = \Mockery::mock(ApiResponse::class);
        $mockResponse->shouldReceive('offsetGet')
            ->with('secure_url')
            ->andReturn('https://res.cloudinary.com/drm3cfpnm/image/upload/v1/servicio_lavado.jpg');

        Cloudinary::shouldReceive('uploadApi->upload')
            ->once()
            ->andReturn($mockResponse);

        $file = UploadedFile::fake()->create('servicio.jpg', 100);

        $this->actingAs($this->user)->post(route('servicios.store'), [
            'nombre' => 'Lavado premium',
            'precio' => 50.00,
            'imagen' => $file,
        ])->assertRedirect(route('servicios.index'));

        $this->assertDatabaseHas('servicios', [
            'nombre' => 'Lavado premium',
            'imagen' => 'https://res.cloudinary.com/drm3cfpnm/image/upload/v1/servicio_lavado.jpg',
        ]);
    }

    /**
     * Feature: servicios-web, Property 3: actualizar sin imagen conserva la original.
     */
    public function test_actualizar_sin_imagen_conserva_original(): void
    {
        $servicio = Servicio::factory()->create([
            'nombre' => 'Lavado básico',
            'imagen' => 'https://res.cloudinary.com/drm3cfpnm/image/upload/v1/original.jpg',
        ]);

        $this->actingAs($this->user)->put(route('servicios.update', $servicio), [
            'nombre' => 'Lavado básico',
            'precio' => 25.00,
        ])->assertRedirect(route('servicios.index'));

        $this->assertDatabaseHas('servicios', [
            'id' => $servicio->id,
            'imagen' => 'https://res.cloudinary.com/drm3cfpnm/image/upload/v1/original.jpg',
        ]);
    }

    /**
     * Feature: servicios-web, Property 3: actualizar con imagen nueva destruye la anterior.
     */
    public function test_actualizar_con_imagen_nueva_destruye_anterior(): void
    {
        $servicio = Servicio::factory()->create([
            'nombre' => 'Lavado básico',
            'imagen' => 'https://res.cloudinary.com/drm3cfpnm/image/upload/v1/vieja.jpg',
        ]);

        $destroyResponse = \Mockery::mock(ApiResponse::class);
        Cloudinary::shouldReceive('uploadApi->destroy')
            ->once()
            ->with('vieja')
            ->andReturn($destroyResponse);

        $uploadResponse = \Mockery::mock(ApiResponse::class);
        $uploadResponse->shouldReceive('offsetGet')
            ->with('secure_url')
            ->andReturn('https://res.cloudinary.com/drm3cfpnm/image/upload/v2/nueva.jpg');

        Cloudinary::shouldReceive('uploadApi->upload')
            ->once()
            ->andReturn($uploadResponse);

        $file = UploadedFile::fake()->create('nueva.jpg', 100);

        $this->actingAs($this->user)->put(route('servicios.update', $servicio), [
            'nombre' => 'Lavado básico',
            'precio' => 25.00,
            'imagen' => $file,
        ])->assertRedirect(route('servicios.index'));

        $this->assertDatabaseHas('servicios', [
            'id' => $servicio->id,
            'imagen' => 'https://res.cloudinary.com/drm3cfpnm/image/upload/v2/nueva.jpg',
        ]);
    }

    /**
     * Feature: servicios-web, Property 3: destroy destruye la imagen y elimina el servicio.
     */
    public function test_destroy_destruye_imagen_y_elimina_servicio(): void
    {
        $servicio = Servicio::factory()->create([
            'nombre' => 'Lavado básico',
            'imagen' => 'https://res.cloudinary.com/drm3cfpnm/image/upload/v1/eliminar.jpg',
        ]);

        $destroyResponse = \Mockery::mock(ApiResponse::class);
        Cloudinary::shouldReceive('uploadApi->destroy')
            ->once()
            ->with('eliminar')
            ->andReturn($destroyResponse);

        $this->actingAs($this->user)
            ->delete(route('servicios.destroy', $servicio))
            ->assertRedirect(route('servicios.index'));

        $this->assertDatabaseMissing('servicios', ['id' => $servicio->id]);
    }
}
