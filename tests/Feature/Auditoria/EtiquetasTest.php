<?php

namespace Tests\Feature\Auditoria;

use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EtiquetasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['acceso-ventas', 'acceso-inventario', 'acceso-caja'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['acceso-ventas', 'acceso-inventario', 'acceso-caja']);
        $this->actingAs($this->user);
    }

    private function producto(int $stock = 10): Producto
    {
        return Producto::create([
            'nombre' => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta' => 35.00,
            'stock' => $stock,
            'inventario' => $stock,
            'activo' => true,
        ]);
    }

    public function test_toggle_status_se_etiqueta_toggle_estado(): void
    {
        $producto = $this->producto();

        $this->patchJson(route('productos.toggleStatus', $producto))->assertOk();

        $this->assertDatabaseHas('registros_auditoria', [
            'auditable_id' => $producto->id,
            'accion' => 'toggle estado',
            'modulo' => 'productos',
        ]);
    }

    public function test_update_stock_se_etiqueta_ajustar_stock(): void
    {
        $producto = $this->producto(5);

        $this->patchJson(route('productos.updateStock', $producto), ['cantidad_adicional' => 3])->assertOk();

        $this->assertDatabaseHas('registros_auditoria', [
            'auditable_id' => $producto->id,
            'accion' => 'ajustar stock',
            'modulo' => 'productos',
        ]);
    }

    public function test_anular_venta_se_etiqueta_anular_venta(): void
    {
        $producto = $this->producto(10);

        $venta = Venta::create([
            'correlativo' => 'VTA-0001',
            'subtotal' => 70.00,
            'total' => 70.00,
            'metodo_pago' => 'efectivo',
            'user_id' => $this->user->id,
        ]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'cantidad' => 2,
            'precio_unitario' => 35.00,
            'subtotal' => 70.00,
        ]);

        $this->delete(route('ventas.destroy', $venta))->assertRedirect();

        $this->assertDatabaseHas('registros_auditoria', [
            'auditable_id' => $venta->id,
            'accion' => 'anular venta',
            'modulo' => 'ventas',
        ]);
    }

    public function test_abrir_caja_se_etiqueta_abrir_caja(): void
    {
        $this->post(route('caja.abrir'), ['monto_inicial' => 100])->assertRedirect();

        $this->assertDatabaseHas('registros_auditoria', [
            'accion' => 'abrir caja',
            'modulo' => 'caja',
        ]);
    }
}
