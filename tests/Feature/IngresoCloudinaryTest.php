<?php

namespace Tests\Feature;

use App\Models\Ingreso;
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

class IngresoCloudinaryTest extends TestCase
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
    public function it_uploads_image_to_cloudinary_when_creating_an_ingreso()
    {
        $this->actingAs($this->user);

        $vehiculo = Vehiculo::factory()->create(['precio' => 50]);
        $trabajador = Trabajador::factory()->create();
        $servicio = Servicio::factory()->create(['precio' => 20]);

        $mockResponse = \Mockery::mock(ApiResponse::class);
        $mockResponse->shouldReceive('offsetGet')->with('secure_url')->andReturn('https://res.cloudinary.com/test/image/upload/v1/ingreso.jpg');

        Cloudinary::shouldReceive('uploadApi->upload')
            ->once()
            ->andReturn($mockResponse);

        $file = UploadedFile::fake()->create('ingreso.jpg', 100);

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

        $response = $this->post(route('ingresos.store'), $data);

        $response->assertRedirect(route('ingresos.index'));

        $this->assertDatabaseHas('ingresos', [
            'foto' => 'https://res.cloudinary.com/test/image/upload/v1/ingreso.jpg',
            'precio' => 70, // 50 + 20
        ]);
    }

    /** @test */
    public function it_deletes_old_cloudinary_image_when_updating_ingreso_with_new_one()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->user);

        $oldUrl = 'https://res.cloudinary.com/test/image/upload/v1/old_ingreso.jpg';
        $ingreso = Ingreso::factory()->confirmado()->create(['foto' => $oldUrl]);
        $vehiculo = Vehiculo::factory()->create();
        $trabajador = Trabajador::factory()->create();

        // Mock Delete
        $mockDestroyResponse = \Mockery::mock(ApiResponse::class);
        Cloudinary::shouldReceive('uploadApi->destroy')
            ->once()
            ->with('old_ingreso')
            ->andReturn($mockDestroyResponse);

        // Mock Upload
        $mockResponse = \Mockery::mock(ApiResponse::class);
        $mockResponse->shouldReceive('offsetGet')->with('secure_url')->andReturn('https://res.cloudinary.com/test/image/upload/v2/new_ingreso.jpg');

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

        $response = $this->put(route('ingresos.update', $ingreso), $data);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('ingresos.show', $ingreso));

        $this->assertDatabaseHas('ingresos', [
            'id' => $ingreso->id,
            'foto' => 'https://res.cloudinary.com/test/image/upload/v2/new_ingreso.jpg',
        ]);
    }

    /** @test */
    public function it_deletes_cloudinary_image_when_destroying_ingreso()
    {
        $this->actingAs($this->user);

        $url = 'https://res.cloudinary.com/test/image/upload/v1/to_delete.jpg';
        $ingreso = Ingreso::factory()->create(['foto' => $url]);

        $mockDestroyResponse = \Mockery::mock(ApiResponse::class);
        Cloudinary::shouldReceive('uploadApi->destroy')
            ->once()
            ->with('to_delete')
            ->andReturn($mockDestroyResponse);

        $response = $this->delete(route('ingresos.destroy', $ingreso));

        $response->assertRedirect(route('ingresos.index'));
        $this->assertDatabaseMissing('ingresos', ['id' => $ingreso->id]);
    }
}
