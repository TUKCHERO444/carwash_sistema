<?php

namespace Tests\Feature\CambioAceite;

use App\Models\Caja;
use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\Producto;
use App\Models\TipoVehiculo;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\ClienteVehiculoService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Throwable;

/**
 * Regresión: los catch (\Throwable) de los controladores devolvían un error
 * genérico SIN reportar la excepción, por lo que los fallos reales (fotos,
 * transacciones, servicios) no dejaban rastro en laravel.log.
 * Ahora todo catch llama a report($e) — verificado con un espía del handler.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Trabajador $trabajador;

    private Cliente $cliente;

    private Producto $producto;

    private CambioAceite $pendiente;

    private object $espia;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-ventas', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-ventas');
        $this->trabajador = Trabajador::create(['nombre' => 'Mecánico Test', 'estado' => true]);
        $this->cliente = Cliente::create(['placa' => 'ABC123', 'nombre' => 'Juan']);
        $this->producto = Producto::factory()->create([
            'nombre' => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta' => 35.00,
            'stock' => 10,
            'inventario' => 10,
            'activo' => true,
        ]);

        $this->pendiente = CambioAceite::create([
            'cliente_id' => $this->cliente->id,
            'user_id' => $this->user->id,
            'fecha' => now()->toDateString(),
            'precio' => 70.00,
            'total' => 70.00,
            'estado' => 'pendiente',
        ]);

        // Espía del ExceptionHandler: captura lo que report() envía.
        $this->espia = new class implements ExceptionHandler
        {
            /** @var list<Throwable> */
            public array $reporteados = [];

            public function report(Throwable $e)
            {
                $this->reporteados[] = $e;
            }

            public function shouldReport(Throwable $e): bool
            {
                return true;
            }

            public function render($request, Throwable $e)
            {
                return response('error interno (test)', 500);
            }

            public function renderForConsole($output, Throwable $e)
            {
                //
            }
        };

        app()->instance(ExceptionHandler::class, $this->espia);
    }

    public function test_store_de_cambio_aceite_reporta_excepciones_inesperadas(): void
    {
        $this->mock(ClienteVehiculoService::class)
            ->shouldReceive('obtenerCliente')
            ->andThrow(new \RuntimeException('Fallo interno simulado (store cambio)'));

        $response = $this->actingAs($this->user)
            ->post('/cambio-aceite', [
                'placa' => 'XYZ789',
                'trabajadores_ids' => [$this->trabajador->id],
                'fecha' => now()->toDateString(),
                'productos' => [
                    ['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio' => 35.00, 'total' => 35.00],
                ],
            ]);

        $response->assertSessionHas('error');
        $this->assertCount(1, $this->espia->reporteados);
        $this->assertInstanceOf(\RuntimeException::class, $this->espia->reporteados[0]);
        $this->assertSame('Fallo interno simulado (store cambio)', $this->espia->reporteados[0]->getMessage());
        // Solo existe el ticket del setUp: la transacción hizo rollback completo.
        $this->assertDatabaseCount('cambio_aceites', 1);
    }

    public function test_procesar_confirmacion_reporta_excepciones_inesperadas(): void
    {
        Caja::factory()->abierta()->create();

        $this->mock(ClienteVehiculoService::class)
            ->shouldReceive('obtenerCliente')
            ->andThrow(new \RuntimeException('Fallo interno simulado (confirmar)'));

        $response = $this->actingAs($this->user)
            ->post("/cambio-aceite/{$this->pendiente->id}/confirmar", [
                'placa' => 'ABC123',
                'nombre' => 'Juan',
                'trabajadores_ids' => [$this->trabajador->id],
                'fecha' => now()->toDateString(),
                'precio' => 70.00,
                'total' => 70.00,
                'metodo_pago' => 'efectivo',
                'productos' => [
                    ['producto_id' => $this->producto->id, 'cantidad' => 2, 'precio' => 35.00, 'total' => 70.00],
                ],
            ]);

        $response->assertSessionHas('error');
        $this->assertCount(1, $this->espia->reporteados);
        $this->assertSame('Fallo interno simulado (confirmar)', $this->espia->reporteados[0]->getMessage());

        // La transacción hizo rollback: el ticket sigue pendiente y sin stock tocado.
        $this->pendiente->refresh();
        $this->assertEquals('pendiente', $this->pendiente->estado);
        $this->assertEquals(10, $this->producto->fresh()->stock);
    }

    public function test_update_de_lavado_reporta_excepciones_inesperadas(): void
    {
        $tipoVehiculo = TipoVehiculo::factory()->create(['precio' => 28000]);
        $lavado = Lavado::factory()->pendiente()->create([
            'tipo_vehiculo_id' => $tipoVehiculo->id,
        ]);

        $this->mock(ClienteVehiculoService::class)
            ->shouldReceive('obtenerCliente')
            ->andThrow(new \RuntimeException('Fallo interno simulado (update lavado)'));

        $response = $this->actingAs($this->user)
            ->put("/lavados/{$lavado->id}", [
                'placa' => 'ABC123',
                'tipo_vehiculo_id' => $tipoVehiculo->id,
                'fecha' => now()->toDateString(),
                'trabajadores_ids' => [$this->trabajador->id],
                'precio' => 100.00,
                'total' => 100.00,
                'metodo_pago' => 'efectivo',
            ]);

        $response->assertSessionHas('error');
        $this->assertCount(1, $this->espia->reporteados);
        $this->assertSame('Fallo interno simulado (update lavado)', $this->espia->reporteados[0]->getMessage());
    }
}
