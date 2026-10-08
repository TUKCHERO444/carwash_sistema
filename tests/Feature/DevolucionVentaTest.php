<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\DetalleVenta;
use App\Models\DevolucionVenta;
use App\Models\EgresoCaja;
use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\KardexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DevolucionVentaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-ventas', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-ventas');
        $this->actingAs($this->user);
    }

    private function producto(int $stock = 10, float $precioVenta = 35.00): Producto
    {
        return Producto::factory()->conExistencias($stock)->create([
            'precio_venta' => $precioVenta,
        ]);
    }

    /**
     * Crea una venta con sus detalles y descuenta el stock, como haría el store.
     *
     * @param  array<int, array{producto: Producto, cantidad: int, precio?: float}>  $lineas
     */
    private function vender(Caja $caja, array $lineas, string $metodoPago = 'efectivo', array $montos = []): Venta
    {
        $venta = Venta::create([
            'correlativo' => 'VTA-'.str_pad((Venta::max('id') ?? 0) + 1, 4, '0', STR_PAD_LEFT),
            'subtotal' => 0,
            'total' => 0,
            'metodo_pago' => $metodoPago,
            'monto_efectivo' => $montos['efectivo'] ?? null,
            'monto_yape' => $montos['yape'] ?? null,
            'monto_izipay' => $montos['izipay'] ?? null,
            'user_id' => $this->user->id,
            'caja_id' => $caja->id,
        ]);

        $total = 0.0;
        foreach ($lineas as $linea) {
            $precio = $linea['precio'] ?? (float) $linea['producto']->precio_venta;
            $subtotal = round($linea['cantidad'] * $precio, 2);
            $total += $subtotal;

            DetalleVenta::create([
                'venta_id' => $venta->id,
                'producto_id' => $linea['producto']->id,
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $precio,
                'subtotal' => $subtotal,
            ]);

            Producto::where('id', $linea['producto']->id)->decrement('stock', $linea['cantidad']);
        }

        $venta->update(['subtotal' => round($total, 2), 'total' => round($total, 2)]);

        return $venta->fresh();
    }

    private function payloadDevolucion(Venta $venta, array $cantidadesPorDetalle, string $motivo = 'Producto defectuoso', string $tipo = 'devolucion'): array
    {
        $lineas = [];
        foreach ($venta->detalles as $i => $detalle) {
            $lineas[] = [
                'detalle_venta_id' => $detalle->id,
                'cantidad' => $cantidadesPorDetalle[$detalle->id] ?? 0,
            ];
        }

        return ['motivo' => $motivo, 'tipo' => $tipo, 'lineas' => $lineas];
    }

    public function test_store_devuelve_parcial_reincorpora_stock_y_genera_kardex_y_egreso(): void
    {
        // Feature: devoluciones-ventas, Property 1, 3, 4: venta intacta, espejo Kardex, egreso.
        $caja = Caja::factory()->abierta()->create();
        $producto = $this->producto(10);
        $venta = $this->vender($caja, [['producto' => $producto, 'cantidad' => 3]]);
        $this->assertEquals(7, $producto->fresh()->stock);

        $response = $this->post(
            route('ventas.devoluciones.store', $venta),
            $this->payloadDevolucion($venta, [$venta->detalles->first()->id => 1])
        );

        $devolucion = DevolucionVenta::first();
        $this->assertNotNull($devolucion);
        $response->assertRedirect(route('devoluciones.show', $devolucion));
        $response->assertSessionHas('success');

        // Venta y detalles intactos.
        $this->assertDatabaseHas('ventas', ['id' => $venta->id, 'correlativo' => $venta->correlativo]);
        $this->assertDatabaseHas('detalle_ventas', ['venta_id' => $venta->id, 'cantidad' => 3]);

        // Stock +1, inventario intacto.
        $this->assertEquals(8, $producto->fresh()->stock);
        $this->assertEquals(10, $producto->fresh()->inventario);

        // Kardex entrada con fuente devolucion.
        $this->assertDatabaseHas('movimientos_kardex', [
            'producto_id' => $producto->id,
            'tipo' => 'entrada',
            'fuente' => 'devolucion',
            'origen_id' => $devolucion->correlativo,
            'cantidad' => 1,
            'stock_antes' => 7,
            'stock_despues' => 8,
        ]);

        // Egreso compensatorio en la caja activa.
        $this->assertDatabaseHas('egresos_caja', [
            'caja_id' => $caja->id,
            'monto' => 35.00,
            'tipo_pago' => 'efectivo',
        ]);

        $this->assertEquals('DEV-0001', $devolucion->correlativo);
        $this->assertEquals(35.00, (float) $devolucion->total_devuelto);
    }

    public function test_store_rechaza_cantidad_superior_a_la_disponible(): void
    {
        // Feature: devoluciones-ventas, Property 2: tope por línea.
        $caja = Caja::factory()->abierta()->create();
        $producto = $this->producto(10);
        $venta = $this->vender($caja, [['producto' => $producto, 'cantidad' => 2]]);

        // Primera devolución válida de 1.
        $this->post(
            route('ventas.devoluciones.store', $venta),
            $this->payloadDevolucion($venta, [$venta->detalles->first()->id => 1])
        )->assertRedirect();

        // Intentar devolver 2 cuando solo queda 1 disponible.
        $this->post(
            route('ventas.devoluciones.store', $venta),
            $this->payloadDevolucion($venta, [$venta->detalles->first()->id => 2])
        )->assertSessionHasErrors('lineas');

        // Sin efectos parciales: sigue habiendo 1 sola devolución.
        $this->assertEquals(1, DevolucionVenta::count());
        $this->assertEquals(9, $producto->fresh()->stock);
    }

    public function test_tope_por_linea_con_datos_aleatorios(): void
    {
        // Feature: devoluciones-ventas, Property 2: tope vendido − ya devuelto.
        for ($i = 0; $i < 30; $i++) {
            $vendida = random_int(1, 8);
            $primera = random_int(0, $vendida);

            $caja = Caja::factory()->abierta()->create();
            $producto = $this->producto(20);
            $venta = $this->vender($caja, [['producto' => $producto, 'cantidad' => $vendida]]);

            if ($primera > 0) {
                $this->post(
                    route('ventas.devoluciones.store', $venta),
                    $this->payloadDevolucion($venta, [$venta->detalles->first()->id => $primera])
                )->assertRedirect();
            }

            $disponible = $vendida - $primera;
            $intento = $disponible + random_int(1, 3);

            $this->post(
                route('ventas.devoluciones.store', $venta),
                $this->payloadDevolucion($venta, [$venta->detalles->first()->id => $intento])
            )->assertSessionHasErrors('lineas');

            $this->assertEquals($primera > 0 ? 1 : 0, DevolucionVenta::where('venta_id', $venta->id)->count());
            $this->assertEquals(20 - $vendida + $primera, $producto->fresh()->stock);
        }
    }

    public function test_store_sin_caja_activa_rechaza_con_error_caja(): void
    {
        // Feature: devoluciones-ventas, Property 9: sin caja no hay operación.
        $producto = $this->producto(10);
        $venta = Venta::create([
            'correlativo' => 'VTA-0001', 'subtotal' => 70, 'total' => 70,
            'metodo_pago' => 'efectivo', 'user_id' => $this->user->id, 'caja_id' => null,
        ]);
        DetalleVenta::create([
            'venta_id' => $venta->id, 'producto_id' => $producto->id,
            'cantidad' => 2, 'precio_unitario' => 35, 'subtotal' => 70,
        ]);

        $this->post(
            route('ventas.devoluciones.store', $venta),
            $this->payloadDevolucion($venta, [$venta->detalles->first()->id ?? 0 => 1])
        )->assertSessionHas('error_caja');

        $this->assertEquals(0, DevolucionVenta::count());
        $this->assertEquals(10, $producto->fresh()->stock);
    }

    public function test_store_mapea_tipo_pago_izipay_a_tarjeta_y_mixto_prorratea(): void
    {
        // Feature: devoluciones-ventas, Property 4: egreso según método de pago.
        $caja = Caja::factory()->abierta()->create();

        $p1 = $this->producto(10, 60.00);
        $v1 = $this->vender($caja, [['producto' => $p1, 'cantidad' => 1]], 'izipay');
        $this->post(route('ventas.devoluciones.store', $v1), $this->payloadDevolucion($v1, [$v1->detalles->first()->id => 1]));
        $this->assertDatabaseHas('egresos_caja', ['tipo_pago' => 'tarjeta', 'monto' => 60.00]);

        $p2 = $this->producto(10, 100.00);
        $v2 = $this->vender($caja, [['producto' => $p2, 'cantidad' => 1]], 'mixto', ['efectivo' => 70, 'yape' => 30]);
        // Devolución parcial simulada: el total es 100, se devuelve todo.
        $this->post(route('ventas.devoluciones.store', $v2), $this->payloadDevolucion($v2, [$v2->detalles->first()->id => 1]));

        $egresos = EgresoCaja::where('caja_id', $caja->id)->where('descripcion', 'like', '%'.$v2->correlativo.'%')->get();
        $this->assertCount(2, $egresos);
        $this->assertEquals(100.00, round($egresos->sum('monto'), 2));
        $this->assertEqualsCanonicalizing(['efectivo', 'yape'], $egresos->pluck('tipo_pago')->all());
    }

    public function test_store_cambio_crea_venta_sustituta_vinculada(): void
    {
        // Feature: devoluciones-ventas, Property 6: cambio = devolución + venta vinculada.
        $caja = Caja::factory()->abierta()->create();
        $devuelto = $this->producto(10, 35.00);
        $sustituto = $this->producto(5, 50.00);
        $venta = $this->vender($caja, [['producto' => $devuelto, 'cantidad' => 1]]);

        $payload = $this->payloadDevolucion($venta, [$venta->detalles->first()->id => 1], 'Defectuoso', 'cambio');
        $payload['producto_nuevo_id'] = $sustituto->id;
        $payload['cantidad_nueva'] = 1;

        $this->post(route('ventas.devoluciones.store', $venta), $payload)->assertRedirect();

        $devolucion = DevolucionVenta::first();
        $this->assertEquals('cambio', $devolucion->tipo);
        $this->assertNotNull($devolucion->venta_nueva_id);

        $nueva = Venta::find($devolucion->venta_nueva_id);
        $this->assertEquals(50.00, (float) $nueva->total);
        $this->assertEquals($caja->id, $nueva->caja_id);

        // Stock: devuelto +1, sustituto −1.
        $this->assertEquals(10, $devuelto->fresh()->stock);
        $this->assertEquals(4, $sustituto->fresh()->stock);

        // Kardex: entrada del devuelto y salida del sustituto con el mismo DEV.
        $this->assertDatabaseHas('movimientos_kardex', [
            'producto_id' => $devuelto->id, 'tipo' => 'entrada',
            'fuente' => 'devolucion', 'origen_id' => $devolucion->correlativo,
        ]);
        $this->assertDatabaseHas('movimientos_kardex', [
            'producto_id' => $sustituto->id, 'tipo' => 'salida',
            'fuente' => 'devolucion', 'origen_id' => $devolucion->correlativo,
        ]);
    }

    public function test_store_cambio_sin_stock_revierte_todo(): void
    {
        // Feature: devoluciones-ventas, Property 5: atomicidad total.
        $caja = Caja::factory()->abierta()->create();
        $devuelto = $this->producto(10, 35.00);
        $sustituto = $this->producto(0, 50.00);
        $venta = $this->vender($caja, [['producto' => $devuelto, 'cantidad' => 1]]);

        $payload = $this->payloadDevolucion($venta, [$venta->detalles->first()->id => 1], 'Defectuoso', 'cambio');
        $payload['producto_nuevo_id'] = $sustituto->id;
        $payload['cantidad_nueva'] = 1;

        $this->post(route('ventas.devoluciones.store', $venta), $payload)->assertSessionHasErrors('lineas');

        $this->assertEquals(0, DevolucionVenta::count());
        $this->assertEquals(1, Venta::count());
        $this->assertEquals(0, EgresoCaja::count());
        $this->assertEquals(0, MovimientoKardex::count());
        $this->assertEquals(9, $devuelto->fresh()->stock);
    }

    public function test_store_exige_motivo_y_lineas(): void
    {
        $caja = Caja::factory()->abierta()->create();
        $producto = $this->producto(10);
        $venta = $this->vender($caja, [['producto' => $producto, 'cantidad' => 1]]);

        $payload = $this->payloadDevolucion($venta, [$venta->detalles->first()->id => 1], '');
        $this->post(route('ventas.devoluciones.store', $venta), $payload)->assertSessionHasErrors('motivo');

        $payload = ['motivo' => 'x', 'tipo' => 'devolucion', 'lineas' => []];
        $this->post(route('ventas.devoluciones.store', $venta), $payload)->assertSessionHasErrors('lineas');

        $this->assertEquals(0, DevolucionVenta::count());
    }

    public function test_store_registra_auditoria(): void
    {
        // Feature: devoluciones-ventas, Property 8: auditoría completa.
        $caja = Caja::factory()->abierta()->create();
        $producto = $this->producto(10);
        $venta = $this->vender($caja, [['producto' => $producto, 'cantidad' => 1]]);

        $this->post(
            route('ventas.devoluciones.store', $venta),
            $this->payloadDevolucion($venta, [$venta->detalles->first()->id => 1])
        );

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'ventas',
            'accion' => 'registrar devolución',
            'usuario_id' => $this->user->id,
        ]);
    }

    public function test_anulacion_rechazada_si_hay_devoluciones_y_funciona_sin_ellas(): void
    {
        // Feature: devoluciones-ventas, Property 7: anulación intacta y diferenciada.
        $caja = Caja::factory()->abierta()->create();
        $producto = $this->producto(10);
        $venta = $this->vender($caja, [['producto' => $producto, 'cantidad' => 2]]);

        $this->post(
            route('ventas.devoluciones.store', $venta),
            $this->payloadDevolucion($venta, [$venta->detalles->first()->id => 1])
        )->assertRedirect();

        // La venta sigue existiendo tras la devolución.
        $this->assertDatabaseHas('ventas', ['id' => $venta->id]);

        // La anulación completa se rechaza con mensaje explícito (no borra).
        $this->delete(route('ventas.destroy', $venta))
            ->assertRedirect(route('ventas.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('ventas', ['id' => $venta->id]);
        $this->assertEquals(9, $producto->fresh()->stock);

        // Sin devoluciones, la anulación completa sigue borrando con restauración total.
        $ventaLimpia = $this->vender($caja, [['producto' => $producto, 'cantidad' => 2]]);
        $this->assertEquals(7, $producto->fresh()->stock);

        $this->delete(route('ventas.destroy', $ventaLimpia))
            ->assertRedirect(route('ventas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ventas', ['id' => $ventaLimpia->id]);
        $this->assertEquals(9, $producto->fresh()->stock);
    }

    public function test_create_muestra_disponibles_e_index_lista(): void
    {
        $caja = Caja::factory()->abierta()->create();
        $producto = $this->producto(10);
        $venta = $this->vender($caja, [['producto' => $producto, 'cantidad' => 2]]);

        $this->get(route('ventas.devoluciones.create', $venta))
            ->assertOk()
            ->assertSee($venta->correlativo);

        $this->post(
            route('ventas.devoluciones.store', $venta),
            $this->payloadDevolucion($venta, [$venta->detalles->first()->id => 1])
        );

        $this->get(route('devoluciones.index'))->assertOk()->assertSee('DEV-0001');
        $this->get(route('devoluciones.show', DevolucionVenta::first()))
            ->assertOk()
            ->assertSee('Producto defectuoso');
    }

    public function test_fuente_devolucion_esta_en_el_catalogo_kardex(): void
    {
        // Feature: devoluciones-ventas, Property 3: fuente propia en Kardex.
        $this->assertContains('devolucion', KardexService::fuentesValidas());
        $this->assertEquals('Devolución', KardexService::etiqueta('devolucion'));
    }
}
