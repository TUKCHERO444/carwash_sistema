<?php

namespace Tests\Feature\Auditoria;

use App\Models\DetalleVenta;
use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RegistroTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['acceso-ventas', 'acceso-inventario', 'acceso-auditoria', 'acceso-caja'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['acceso-ventas', 'acceso-inventario', 'acceso-auditoria', 'acceso-caja']);
    }

    public function test_crear_registra_auditoria_con_morphs_y_datos_despues(): void
    {
        $this->actingAs($this->user);

        $producto = Producto::create([
            'nombre' => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta' => 35.00,
            'stock' => 5,
            'inventario' => 5,
            'activo' => true,
        ]);

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'productos',
            'accion' => 'crear',
            'auditable_type' => Producto::class,
            'auditable_id' => $producto->id,
            'usuario_id' => $this->user->id,
        ]);

        $registro = RegistroAuditoria::where('auditable_id', $producto->id)->first();
        $this->assertSame(5, $registro->datos_despues['stock']);
        $this->assertSame('productos', $registro->modulo);
    }

    public function test_actualizar_registra_diff_antes_despues(): void
    {
        $this->actingAs($this->user);

        $producto = Producto::create([
            'nombre' => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta' => 35.00,
            'stock' => 10,
            'inventario' => 10,
            'activo' => true,
        ]);

        $producto->update(['stock' => 7]);

        $registro = RegistroAuditoria::where('auditable_id', $producto->id)
            ->where('accion', 'actualizar')
            ->latest('fecha_movimiento')
            ->first();

        $this->assertNotNull($registro);
        $this->assertSame(10, $registro->datos_antes['stock']);
        $this->assertSame(7, $registro->datos_despues['stock']);
    }

    public function test_eliminar_registra_auditoria(): void
    {
        $this->actingAs($this->user);

        $producto = Producto::create([
            'nombre' => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta' => 35.00,
            'stock' => 5,
            'inventario' => 5,
            'activo' => true,
        ]);

        $producto->delete();

        $this->assertDatabaseHas('registros_auditoria', [
            'auditable_id' => $producto->id,
            'accion' => 'eliminar',
        ]);
    }

    public function test_tablas_pivote_y_kardex_no_se_auditan(): void
    {
        $this->actingAs($this->user);

        $producto = Producto::create([
            'nombre' => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta' => 35.00,
            'stock' => 5,
            'inventario' => 5,
            'activo' => true,
        ]);

        $venta = Venta::create([
            'correlativo' => 'VTA-999',
            'subtotal' => 35.00,
            'total' => 35.00,
            'metodo_pago' => 'efectivo',
            'user_id' => $this->user->id,
        ]);

        $base = RegistroAuditoria::count();

        // MovimientoKardex (excluido) y una entidad pivote (excluida)
        MovimientoKardex::create([
            'producto_id' => $producto->id,
            'tipo' => 'entrada',
            'fuente' => 'inventario',
            'origen_id' => 'INV-0001',
            'cantidad' => 5,
            'stock_antes' => 0,
            'stock_despues' => 5,
            'usuario_id' => $this->user->id,
            'fecha_movimiento' => now(),
        ]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'precio_unitario' => 35.00,
            'subtotal' => 35.00,
        ]);

        $this->assertSame($base, RegistroAuditoria::count());
    }
}
