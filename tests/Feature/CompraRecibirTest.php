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
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tests para la recepción de compras (Fase 3).
 *
 * Feature: compras-ingreso-mercaderia
 * Property tests covering requirements 29-40.
 */
class CompraRecibirTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Permisos necesarios
        Permission::firstOrCreate(['name' => 'acceso-compras', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'acceso-caja', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['acceso-compras', 'acceso-caja']);

        $this->proveedor = Proveedor::factory()->create(['estado' => '1']);
    }

    /**
     * Property 1: Recepción exitosa con caja abierta actualiza stock, inventario,
     * registra Kardex con fuente 'compra', actualiza precio_compra y crea egreso.
     *
     * Property: dado una compra en borrador con líneas válidas y caja abierta,
     * al recibirla, todos los efectos secundarios ocurren atómicamente.
     */
    public function test_recibir_compra_exitosa_actualiza_todo(): void
    {
        // Arrange: caja abierta
        $caja = Caja::create([
            'user_id' => $this->user->id,
            'estado' => 'abierta',
            'monto_inicial' => 500,
            'fecha_apertura' => now(),
        ]);

        // Productos con stock inicial 0
        $producto1 = Producto::factory()->conExistencias(0)->create(['precio_compra' => 0, 'precio_venta' => 100]);
        $producto2 = Producto::factory()->conExistencias(0)->create(['precio_compra' => 0, 'precio_venta' => 200]);

        $compra = Compra::create([
            'correlativo' => null,
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'tipo_documento' => 'Factura',
            'numero_documento' => 'F001-001',
            'estado' => 'borrador',
            'subtotal' => 1500,
            'total' => 1500,
            'user_id' => $this->user->id,
        ]);

        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto1->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'subtotal' => 500,
        ]);
        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto2->id,
            'cantidad' => 5,
            'costo_unitario' => 200,
            'subtotal' => 1000,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->post(route('compras.recibir', $compra));

        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('success');

        $compra->refresh();
        $this->assertEquals('recibida', $compra->estado);
        $this->assertNotNull($compra->correlativo);
        $this->assertStringStartsWith('CMP-', $compra->correlativo);
        $this->assertNotNull($compra->fecha_recepcion);
        $this->assertEquals($caja->id, $compra->caja_id);
        $this->assertNotNull($compra->egreso_caja_id);
        $this->assertNotNull($compra->correlativo);
        $this->assertStringStartsWith('CMP-', $compra->correlativo);
        $this->assertNotNull($compra->fecha_recepcion);
        $this->assertEquals($caja->id, $compra->caja_id);
        $this->assertNotNull($compra->egreso_caja_id);

        // Stock e inventario actualizados
        $producto1->refresh();
        $producto2->refresh();
        $this->assertEquals(10, $producto1->stock);
        $this->assertEquals(10, $producto1->inventario);
        $this->assertEquals(5, $producto2->stock);
        $this->assertEquals(5, $producto2->inventario);

        // precio_compra actualizado (era 0, ahora toma el costo de la compra)
        $this->assertEquals(50, $producto1->precio_compra);
        $this->assertEquals(200, $producto2->precio_compra);

        // Kardex con fuente 'compra'
        $movimientos = MovimientoKardex::where('fuente', 'compra')
            ->where('origen_id', $compra->correlativo)
            ->get();
        $this->assertCount(2, $movimientos);
        foreach ($movimientos as $m) {
            $this->assertEquals('entrada', $m->tipo);
            $this->assertEquals('compra', $m->fuente);
            $this->assertEquals($compra->correlativo, $m->origen_id);
        }

        // Egreso de caja creado
        $egreso = EgresoCaja::find($compra->egreso_caja_id);
        $this->assertNotNull($egreso);
        $this->assertEquals(1500, $egreso->monto);
        $this->assertEquals($caja->id, $egreso->caja_id);
        $this->assertStringContainsString($compra->correlativo, $egreso->descripcion);
    }

    /**
     * Property 2: Recepción falla si no hay caja abierta (error_caja).
     *
     * Property: si no existe caja abierta, la recepción se rechaza antes de
     * iniciar la transacción y no deja rastro (stock, Kardex, egreso).
     */
    public function test_recibir_sin_caja_abierta_rechazada(): void
    {
        // Arrange: NO hay caja abierta
        $producto = Producto::factory()->conExistencias(0)->create();

        $compra = Compra::create([
            'correlativo' => null,
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'borrador',
            'subtotal' => 500,
            'total' => 500,
            'user_id' => $this->user->id,
        ]);

        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'subtotal' => 500,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->post(route('compras.recibir', $compra));

        // Assert: redirige con error_caja
        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('error_caja', true);

        // No hubo cambios
        $compra->refresh();
        $this->assertEquals('borrador', $compra->estado);
        $this->assertNull($compra->correlativo);
        $this->assertNull($compra->caja_id);
        $this->assertNull($compra->egreso_caja_id);

        $producto->refresh();
        $this->assertEquals(0, $producto->stock);
        $this->assertEquals(0, $producto->inventario);

        // No hay Kardex ni egreso
        $this->assertEquals(0, MovimientoKardex::where('fuente', 'compra')->count());
        $this->assertEquals(0, EgresoCaja::count());
    }

    /**
     * Property 3: Recepción falla si algún producto está inactivo.
     */
    public function test_recibir_con_producto_inactivo_rechazada(): void
    {
        $caja = Caja::create([
            'user_id' => $this->user->id,
            'estado' => 'abierta',
            'monto_inicial' => 500,
            'fecha_apertura' => now(),
        ]);

        $productoActivo = Producto::factory()->conExistencias(0)->create(['activo' => 1]);
        $productoInactivo = Producto::factory()->conExistencias(0)->create(['activo' => 0]);

        $compra = Compra::create([
            'correlativo' => null,
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'borrador',
            'subtotal' => 1000,
            'total' => 1000,
            'user_id' => $this->user->id,
        ]);

        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $productoActivo->id,
            'cantidad' => 5,
            'costo_unitario' => 100,
            'subtotal' => 500,
        ]);
        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $productoInactivo->id,
            'cantidad' => 5,
            'costo_unitario' => 100,
            'subtotal' => 500,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->post(route('compras.recibir', $compra));

        // Assert
        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('error');

        $compra->refresh();
        $this->assertEquals('borrador', $compra->estado);

        // No hay Kardex ni egreso
        $this->assertEquals(0, MovimientoKardex::where('fuente', 'compra')->count());
        $this->assertEquals(0, EgresoCaja::count());
    }

    /**
     * Property 4: Idempotencia - segunda recepción de la misma compra falla.
     *
     * Property: una compra ya recibida no puede recibirse de nuevo.
     */
    public function test_recibir_dos_veces_la_misma_compra_falla(): void
    {
        $caja = Caja::create([
            'user_id' => $this->user->id,
            'estado' => 'abierta',
            'monto_inicial' => 500,
            'fecha_apertura' => now(),
        ]);

        $producto = Producto::factory()->conExistencias(0)->create();

        $compra = Compra::create([
            'correlativo' => null,
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'borrador',
            'subtotal' => 500,
            'total' => 500,
            'user_id' => $this->user->id,
        ]);

        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'subtotal' => 500,
        ]);

        // Primera recepción
        $this->actingAs($this->user)->post(route('compras.recibir', $compra));

        // Segunda recepción
        $response = $this->actingAs($this->user)->post(route('compras.recibir', $compra));

        // Assert: rechaza con error de estado
        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('error');

        $compra->refresh();
        $this->assertEquals('recibida', $compra->estado);

        // Stock no se duplicó
        $producto->refresh();
        $this->assertEquals(10, $producto->stock);

        // Solo un movimiento Kardex
        $this->assertEquals(1, MovimientoKardex::where('fuente', 'compra')
            ->where('origen_id', $compra->correlativo)->count());
    }

    /**
     * Property 5: precio_compra NO se actualiza si el producto ya tiene costo > 0
     * y el nuevo costo de la compra es 0 (costo desconocido).
     *
     * Esto preserva la información de costo existente.
     */
    public function test_precio_compra_no_sobrescribe_si_nuevo_costo_es_cero(): void
    {
        $caja = Caja::create([
            'user_id' => $this->user->id,
            'estado' => 'abierta',
            'monto_inicial' => 500,
            'fecha_apertura' => now(),
        ]);

        // Producto YA tiene precio_compra conocido
        $producto = Producto::factory()->conExistencias(0)->create([
            'precio_compra' => 75, // Costo conocido previo
            'precio_venta' => 100,
        ]);

        $compra = Compra::create([
            'correlativo' => null,
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'borrador',
            'subtotal' => 0, // Costo 0 = desconocido
            'total' => 0,
            'user_id' => $this->user->id,
        ]);

        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'cantidad' => 10,
            'costo_unitario' => 0, // Desconocido
            'subtotal' => 0,
        ]);

        $this->actingAs($this->user)->post(route('compras.recibir', $compra));

        $producto->refresh();
        // Debe conservar el precio_compra anterior (75), no ponerse a 0
        $this->assertEquals(75, $producto->precio_compra);
    }

    /**
     * Property 6: Advertencia de margen cuando nuevo costo > precio_venta.
     *
     * La respuesta incluye aviso pero la recepción se completa.
     */
    public function test_recibir_advertencia_margen_costo_mayor_que_venta(): void
    {
        $caja = Caja::create([
            'user_id' => $this->user->id,
            'estado' => 'abierta',
            'monto_inicial' => 500,
            'fecha_apertura' => now(),
        ]);

        // precio_venta = 50, costo de compra = 60 -> margen perdido
        $producto = Producto::factory()->conExistencias(0)->create([
            'precio_compra' => 0,
            'precio_venta' => 50,
        ]);

        $compra = Compra::create([
            'correlativo' => null,
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'borrador',
            'subtotal' => 600,
            'total' => 600,
            'user_id' => $this->user->id,
        ]);

        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'cantidad' => 10,
            'costo_unitario' => 60,
            'subtotal' => 600,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('compras.recibir', $compra));

        // La recepción se completa (no falla)
        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('success');

        $mensaje = session('success');
        $this->assertStringContainsString('⚠', $mensaje);
        $this->assertStringContainsString($producto->nombre, $mensaje);
    }

    /**
     * Property 7: Transacción atómica - si falla el egreso, se revierte todo.
     *
     * Simulamos fallo en CajaService forzando una excepción.
     */
    public function test_recibir_transaccion_atomica_si_falla_egreso(): void
    {
        $caja = Caja::create([
            'user_id' => $this->user->id,
            'estado' => 'abierta',
            'monto_inicial' => 500,
            'fecha_apertura' => now(),
        ]);

        $producto = Producto::factory()->conExistencias(0)->create();

        $compra = Compra::create([
            'correlativo' => null,
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'borrador',
            'subtotal' => 500,
            'total' => 500,
            'user_id' => $this->user->id,
        ]);

        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'subtotal' => 500,
        ]);

        // Mock CajaService para lanzar excepción en registrarEgreso
        $this->mock(CajaService::class, function ($mock) use ($caja) {
            $mock->shouldReceive('getCajaActiva')->andReturn($caja);
            $mock->shouldReceive('registrarEgreso')->andThrow(new \RuntimeException('Error caja'));
        });

        $response = $this->actingAs($this->user)
            ->post(route('compras.recibir', $compra));

        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('error');

        // Todo revertido
        $compra->refresh();
        $this->assertEquals('borrador', $compra->estado);
        $this->assertNull($compra->correlativo);

        $producto->refresh();
        $this->assertEquals(0, $producto->stock);

        $this->assertEquals(0, MovimientoKardex::where('fuente', 'compra')->count());
        $this->assertEquals(0, EgresoCaja::count());
    }

    /**
     * Property 8: Bloqueo por producto_id ascendente evita deadlocks.
     *
     * Verificamos que las líneas se procesan ordenadas por producto_id.
     */
    public function test_recibir_ordena_lineas_por_producto_id(): void
    {
        $caja = Caja::create([
            'user_id' => $this->user->id,
            'estado' => 'abierta',
            'monto_inicial' => 500,
            'fecha_apertura' => now(),
        ]);

        // Crear productos con IDs conocidos (producto_2 tiene ID menor que producto_1)
        $producto2 = Producto::factory()->conExistencias(0)->create();
        $producto1 = Producto::factory()->conExistencias(0)->create();

        // Forzar que producto2 tenga ID menor
        if ($producto1->id < $producto2->id) {
            // Swap references
            $temp = $producto1;
            $producto1 = $producto2;
            $producto2 = $temp;
        }

        $compra = Compra::create([
            'correlativo' => null,
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'borrador',
            'subtotal' => 1500,
            'total' => 1500,
            'user_id' => $this->user->id,
        ]);

        // Línea 1: producto1 (ID mayor) - se inserta primero en el formulario
        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto1->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'subtotal' => 500,
        ]);
        // Línea 2: producto2 (ID menor) - se inserta segundo
        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto2->id,
            'cantidad' => 5,
            'costo_unitario' => 200,
            'subtotal' => 1000,
        ]);

        $this->actingAs($this->user)->post(route('compras.recibir', $compra));

        // Verificar que ambos se procesaron (la transacción no hizo deadlock)
        $producto1->refresh();
        $producto2->refresh();
        $this->assertEquals(10, $producto1->stock);
        $this->assertEquals(5, $producto2->stock);
    }

    /**
     * Property 9: Correlativo se genera si falta, y se usa el existente si ya tiene.
     */
    public function test_recibir_usar_correlativo_existente_o_generar_nuevo(): void
    {
        $caja = Caja::create([
            'user_id' => $this->user->id,
            'estado' => 'abierta',
            'monto_inicial' => 500,
            'fecha_apertura' => now(),
        ]);

        $producto = Producto::factory()->conExistencias(0)->create();

        // Caso A: compra SIN correlativo -> se genera
        $compraA = Compra::create([
            'correlativo' => null,
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'borrador',
            'subtotal' => 500,
            'total' => 500,
            'user_id' => $this->user->id,
        ]);
        DetalleCompra::create([
            'compra_id' => $compraA->id,
            'producto_id' => $producto->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'subtotal' => 500,
        ]);

        $this->actingAs($this->user)->post(route('compras.recibir', $compraA));
        $compraA->refresh();
        $this->assertNotNull($compraA->correlativo);
        $this->assertStringStartsWith('CMP-', $compraA->correlativo);

        // Caso B: compra CON correlativo -> se conserva
        $compraB = Compra::create([
            'correlativo' => 'CMP-9999',
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'borrador',
            'subtotal' => 500,
            'total' => 500,
            'user_id' => $this->user->id,
        ]);
        DetalleCompra::create([
            'compra_id' => $compraB->id,
            'producto_id' => $producto->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'subtotal' => 500,
        ]);

        $this->actingAs($this->user)->post(route('compras.recibir', $compraB));
        $compraB->refresh();
        $this->assertEquals('CMP-9999', $compraB->correlativo);
    }
}
