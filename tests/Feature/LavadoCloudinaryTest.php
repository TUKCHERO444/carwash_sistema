<?php

namespace Tests\Feature;

use App\Models\Lavado;
use App\Models\Servicio;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use Cloudinary\Api\ApiResponse;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LavadoCloudinaryTest extends TestCase
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
    public function it_uploads_image_to_cloudinary_when_creating_a_lavado()
    {
        $this->actingAs($this->user);

        $vehiculo = Vehiculo::factory()->create(['precio' => 50]);
        $trabajador = Trabajador::factory()->create();
        $servicio = Servicio::factory()->create(['precio' => 20]);

        $mockResponse = \Mockery::mock(ApiResponse::class);
        $mockResponse->shouldReceive('offsetGet')->with('secure_url')->andReturn('https://res.cloudinary.com/test/image/upload/v1/lavado.jpg');

        Cloudinary::shouldReceive('uploadApi->upload')
            ->once()
            ->andReturn($mockResponse);

        $file = UploadedFile::fake()->create('lavado.jpg', 100);

        $data = [
            'vehiculo_id' => $vehiculo->id,
            'placa' => 'ABC-123',
            'fecha' => now()->format('Y-m-d'),
            'foto' => $file,
            'trabajadores_ids' => [$trabajador->id],
            'servicios' => [
                ['servicio_id' => $servicio->id],
            ],
        ];

        $response = $this->post(route('lavados.store'), $data);

        $response->assertRedirect(route('lavados.index'));

        $this->assertDatabaseHas('lavados', [
            'foto' => 'https://res.cloudinary.com/test/image/upload/v1/lavado.jpg',
            'precio' => 70, // 50 + 20
        ]);
    }

    /** @test */
    public function it_deletes_old_cloudinary_image_when_updating_lavado_with_new_one()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->user);

        $oldUrl = 'https://res.cloudinary.com/test/image/upload/v1/old_lavado.jpg';
        $lavado = Lavado::factory()->confirmado()->create(['foto' => $oldUrl]);
        $vehiculo = Vehiculo::factory()->create();
        $trabajador = Trabajador::factory()->create();

        // Mock Delete
        $mockDestroyResponse = \Mockery::mock(ApiResponse::class);
        Cloudinary::shouldReceive('uploadApi->destroy')
            ->once()
            ->with('old_lavado')
            ->andReturn($mockDestroyResponse);

        // Mock Upload
        $mockResponse = \Mockery::mock(ApiResponse::class);
        $mockResponse->shouldReceive('offsetGet')->with('secure_url')->andReturn('https://res.cloudinary.com/test/image/upload/v2/new_lavado.jpg');

        Cloudinary::shouldReceive('uploadApi->upload')
            ->once()
            ->andReturn($mockResponse);

        $file = UploadedFile::fake()->create('new.jpg', 100);

        $data = [
            'vehiculo_id' => $vehiculo->id,
            'placa' => 'XYZ-789',
            'fecha' => now()->format('Y-m-d'),
            'foto' => $file,
            'trabajadores_ids' => [$trabajador->id],
            'precio' => 100,
            'total' => 90,
            'metodo_pago' => 'efectivo',
        ];

        $response = $this->put(route('lavados.update', $lavado), $data);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('lavados.show', $lavado));

        $this->assertDatabaseHas('lavados', [
            'id' => $lavado->id,
            'foto' => 'https://res.cloudinary.com/test/image/upload/v2/new_lavado.jpg',
        ]);
    }

    /** @test */
    public function it_deletes_cloudinary_image_when_destroying_lavado()
    {
        $this->actingAs($this->user);

        $url = 'https://res.cloudinary.com/test/image/upload/v1/to_delete.jpg';
        $lavado = Lavado::factory()->create(['foto' => $url]);

        $mockDestroyResponse = \Mockery::mock(ApiResponse::class);
        Cloudinary::shouldReceive('uploadApi->destroy')
            ->once()
            ->with('to_delete')
            ->andReturn($mockDestroyResponse);

        $response = $this->delete(route('lavados.destroy', $lavado));

        $response->assertRedirect(route('lavados.index'));
        $this->assertDatabaseMissing('lavados', ['id' => $lavado->id]);
    }
}
