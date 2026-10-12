<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Coherencia aritmética entre líneas de detalle y totales de cabecera.
 *
 * Las líneas se recalculan en el servidor (patrón Compras) y la cabecera
 * debe cuadrar (el total admite descuento/facturación hasta ×1.18).
 */
class CoherenciaTotalesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Producto $producto;

    private Trabajador $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['acceso-ventas', 'acceso-compras', 'acceso-caja'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['acceso-ventas', 'acceso-compras', 'acceso-caja']);
        $this->trabajador = Trabajador::create(['nombre' => 'Mecánico Test', 'estado' => true]);
        $this->producto = Producto::factory()->create([
            'nombre' => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta' => 35.00,
            'stock' => 100,
            'inventario' => 100,
            'activo' => true,
        ]);

        Caja::factory()->abierta()->create();
    }

    private function lineaVenta(float $subtotalLinea = 70.00): array
    {
        return [[
            'producto_id' => $this->producto->id,
            'cantidad' => 2,
            'precio_unitario' => 35.00,
            'subtotal' => $subtotalLinea,
        ]];
    }

    private function lineaCambio(float $totalLinea = 70.00): array
    {
        return [[
            'producto_id' => $this->producto->id,
            'cantidad' => 2,
            'precio' => 35.00,
            'total' => $totalLinea,
        ]];
    }

    public function test_venta_normaliza_subtotal_de_linea_incoherente(): void
    {
        $this->actingAs($this->user)->post(route('ventas.store'), [
            'subtotal' => 70.00,
            'total' => 70.00,
            'metodo_pago' => 'efectivo',
            'productos' => $this->lineaVenta(9999.99),
        ])->assertRedirect();

        $this->assertDatabaseHas('detalle_ventas', [
            'producto_id' => $this->producto->id,
            'cantidad' => 2,
            'subtotal' => 70.00,
        ]);
        $this->assertDatabaseHas('ventas', ['subtotal' => 70.00, 'total' => 70.00]);
    }

    public function test_venta_rechaza_subtotal_de_cabecera_distinto_a_la_suma(): void
    {
        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'subtotal' => 50.00,
            'total' => 70.00,
            'metodo_pago' => 'efectivo',
            'productos' => $this->lineaVenta(),
        ]);

        $response->assertSessionHasErrors('subtotal');
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('detalle_ventas', 0);
    }

    public function test_venta_rechaza_total_sobre_el_tope_de_facturacion(): void
    {
        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'subtotal' => 70.00,
            'total' => 100.00,
            'metodo_pago' => 'efectivo',
            'productos' => $this->lineaVenta(),
        ]);

        $response->assertSessionHasErrors('total');
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_venta_acepta_total_con_descuento(): void
    {
        $this->actingAs($this->user)->post(route('ventas.store'), [
            'subtotal' => 70.00,
            'total' => 60.00,
            'metodo_pago' => 'efectivo',
            'productos' => $this->lineaVenta(),
        ])->assertRedirect();

        $this->assertDatabaseHas('ventas', ['subtotal' => 70.00, 'total' => 60.00]);
    }

    public function test_cambio_store_normaliza_total_de_linea_incoherente(): void
    {
        $this->actingAs($this->user)->post('/cambio-aceite', [
            'placa' => 'ABC123',
            'fecha' => now()->toDateString(),
            'trabajadores_ids' => [$this->trabajador->id],
            'productos' => $this->lineaCambio(9999.99),
        ])->assertRedirect(route('cambio-aceite.index'));

        $cambio = CambioAceite::firstOrFail();
        $this->assertEqualsWithDelta(70.00, (float) $cambio->total, 0.01);
        $this->assertDatabaseHas('cambio_productos', [
            'cambio_aceite_id' => $cambio->id,
            'cantidad' => 2,
            'total' => 70.00,
        ]);
    }

    private function cambioPendiente(): CambioAceite
    {
        $cambio = CambioAceite::create([
            'cliente_id' => Cliente::factory()->create()->id,
            'user_id' => $this->user->id,
            'fecha' => now()->toDateString(),
            'precio' => 70.00,
            'total' => 70.00,
            'estado' => 'pendiente',
        ]);
        $cambio->trabajadores()->attach($this->trabajador->id);
        $cambio->productos()->attach($this->producto->id, [
            'cantidad' => 2, 'precio' => 35.00, 'total' => 70.00,
        ]);

        return $cambio;
    }

    public function test_cambio_update_rechaza_precio_distinto_a_la_suma(): void
    {
        $cambio = $this->cambioPendiente();

        $response = $this->actingAs($this->user)->put(route('cambio-aceite.update', $cambio), [
            'placa' => 'ABC123',
            'fecha' => now()->toDateString(),
            'trabajadores_ids' => [$this->trabajador->id],
            'precio' => 50.00,
            'total' => 50.00,
            'metodo_pago' => 'efectivo',
            'productos' => $this->lineaCambio(),
        ]);

        $response->assertSessionHasErrors('precio');
        $this->assertEqualsWithDelta(70.00, (float) $cambio->fresh()->total, 0.01);
    }

    public function test_cambio_update_rechaza_total_sobre_el_tope(): void
    {
        $cambio = $this->cambioPendiente();

        $response = $this->actingAs($this->user)->put(route('cambio-aceite.update', $cambio), [
            'placa' => 'ABC123',
            'fecha' => now()->toDateString(),
            'trabajadores_ids' => [$this->trabajador->id],
            'precio' => 70.00,
            'total' => 100.00,
            'metodo_pago' => 'efectivo',
            'productos' => $this->lineaCambio(),
        ]);

        $response->assertSessionHasErrors('total');
    }

    public function test_cambio_update_acepta_descuento_y_normaliza_lineas(): void
    {
        $cambio = $this->cambioPendiente();

        $this->actingAs($this->user)->put(route('cambio-aceite.update', $cambio), [
            'placa' => 'ABC123',
            'fecha' => now()->toDateString(),
            'trabajadores_ids' => [$this->trabajador->id],
            'precio' => 70.00,
            'total' => 60.00,
            'metodo_pago' => 'efectivo',
            'productos' => $this->lineaCambio(9999.99),
        ])->assertRedirect();

        $this->assertDatabaseHas('cambio_aceites', ['id' => $cambio->id, 'precio' => 70.00, 'total' => 60.00]);
        $this->assertDatabaseHas('cambio_productos', [
            'cambio_aceite_id' => $cambio->id,
            'total' => 70.00,
        ]);
    }

    public function test_compra_normaliza_subtotal_incoherente(): void
    {
        $proveedor = Proveedor::factory()->create(['estado' => '1']);

        $this->actingAs($this->user)->post(route('compras.store'), [
            'proveedor_id' => $proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'detalle' => [[
                'producto_id' => $this->producto->id,
                'cantidad' => 2,
                'costo_unitario' => 35.00,
                'subtotal' => 9999.99,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('detalle_compras', [
            'producto_id' => $this->producto->id,
            'cantidad' => 2,
            'subtotal' => 70.00,
        ]);
        $this->assertDatabaseHas('compras', ['subtotal' => 70.00, 'total' => 70.00]);
    }

    public function test_pagos_mixtos_siguen_cuadrando_con_total_con_descuento(): void
    {
        $this->actingAs($this->user)->post(route('ventas.store'), [
            'subtotal' => 70.00,
            'total' => 60.00,
            'metodo_pago' => 'mixto',
            'monto_efectivo' => 20.00,
            'monto_yape' => 40.00,
            'productos' => $this->lineaVenta(),
        ])->assertRedirect();

        $venta = Venta::firstOrFail();
        $this->assertEqualsWithDelta(60.00, (float) $venta->pagos()->sum('monto'), 0.01);
    }
}
