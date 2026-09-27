<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\EgresoCaja;
use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests para anular compra recibida (Fase 7).
 */
class CompraAnularRecibidaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'acceso-compras', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'acceso-caja', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['acceso-compras', 'acceso-caja']);

        $this->proveedor = Proveedor::factory()->create(['estado' => '1']);
    }

    private function crearCajaAbierta(): Caja
    {
        return app(CajaService::class)->abrirCaja(500, $this->user->id);
    }

    private function crearEgresoCaja(Caja $caja, array $data = []): \App\Models\EgresoCaja
    {
        return \App\Models\EgresoCaja::create(array_merge([
            'caja_id' => $caja->id,
            'monto' => 100,
            'descripcion' => 'Egreso de prueba',
            'tipo_pago' => 'efectivo',
            'user_id' => $this->user->id,
        ], $data));
    }

    /**
     * Anular compra recibida: stock - cant, Kardex salida ajuste_negativo, caja ingreso compensatorio.
     */
    public function test_anular_compra_recibida_exitosa(): void
    {
        $caja = $this->crearCajaAbierta();
        $egresoOriginal = $this->crearEgresoCaja($caja, ['monto' => 500, 'descripcion' => 'Pago compra original']);

        $producto = \App\Models\Producto::factory()->create(['stock' => 20, 'inventario' => 20, 'precio_compra' => 50, 'precio_venta' => 100]);

        $compra = Compra::create([
            'correlativo' => 'CMP-0001',
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'recibida',
            'subtotal' => 500,
            'total' => 500,
            'user_id' => $this->user->id,
            'caja_id' => $caja->id,
            'egreso_caja_id' => $egresoOriginal->id,
            'fecha_recepcion' => now(),
        ]);

        \App\Models\DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'subtotal' => 500,
        ]);

        try {
$response = $this->actingAs($this->user)
            ->post(route('compras.anular-recibida', $compra));

        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('success');

            $compra->refresh();
            $this->assertEquals('anulada', $compra->estado);
            $this->assertNotNull($compra->fecha_anulacion);

            $producto = \App\Models\Producto::find($producto->id);
            $this->assertEquals(10, $producto->stock); // 20 - 10

            // Kardex salida compensatoria
            $movimientos = \App\Models\MovimientoKardex::where('fuente', 'ajuste_negativo')
                ->where('origen_id', 'CMP-0001')
                ->get();
            $this->assertCount(1, $movimientos);
            $this->assertEquals('salida', $movimientos->first()->tipo);
            $this->assertEquals('ajuste_negativo', $movimientos->first()->fuente);

            // Ingreso compensatorio en caja (monto negativo = ingreso)
            $egreso = \App\Models\EgresoCaja::where('descripcion', 'like', '%Anulación compra CMP-0001%')->first();
            $this->assertNotNull($egreso);
            $this->assertEquals(500, abs($egreso->monto)); // monto negativo = ingreso
            $this->assertEquals($caja->id, $egreso->caja_id);
        } catch (\Throwable $e) {
            error_log('Exception caught in test: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Anular con stock insuficiente falla y hace rollback.
     */
    public function test_anular_con_stock_insuficiente_falla(): void
    {
        $caja = $this->crearCajaAbierta();
        $egresoOriginal = $this->crearEgresoCaja($caja, ['monto' => 1000, 'descripcion' => 'Egreso original']);

        $producto = \App\Models\Producto::factory()->create(['stock' => 5, 'inventario' => 5]);

        $compra = \App\Models\Compra::create([
            'correlativo' => 'CMP-0002',
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'recibida',
            'subtotal' => 1000,
            'total' => 1000,
            'user_id' => $this->user->id,
            'caja_id' => $caja->id,
            'egreso_caja_id' => $egresoOriginal->id,
            'fecha_recepcion' => now(),
        ]);

        \App\Models\DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'cantidad' => 10,
            'costo_unitario' => 100,
            'subtotal' => 1000,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('compras.anular-recibida', $compra));

        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('error');

        $compra = \App\Models\Compra::find($compra->id);
        $this->assertEquals('recibida', $compra->estado);

        $this->assertEquals(0, \App\Models\MovimientoKardex::where('fuente', 'ajuste_negativo')
            ->where('origen_id', 'CMP-0002')->count());
    }

    /**
     * Sin caja abierta -> error_caja.
     */
    public function test_anular_sin_caja_abierta_rechazada(): void
    {
        $producto = \App\Models\Producto::factory()->create(['stock' => 20]);

        $compra = \App\Models\Compra::create([
            'correlativo' => 'CMP-0003',
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'recibida',
            'subtotal' => 500,
            'total' => 500,
            'user_id' => $this->user->id,
        ]);

        \App\Models\DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'subtotal' => 500,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('compras.anular-recibida', $compra));

        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('error_caja', true);
    }

    /**
     * Compra en borrador usa anular simple (ya existente).
     */
    public function test_anular_borrador_sin_reversion(): void
    {
        $compra = \App\Models\Compra::factory()->borrador()->create(['proveedor_id' => $this->proveedor->id]);

        $response = $this->actingAs($this->user)
            ->post(route('compras.anular', $compra));

        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('success');

        $compra = \App\Models\Compra::find($compra->id);
        $this->assertEquals('anulada', $compra->estado);
        $this->assertNull($compra->fecha_anulacion);
    }
}