<?php

namespace Tests\Feature\Auditoria;

use App\Models\Caja;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ComprasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Proveedor $proveedor;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['acceso-compras', 'acceso-caja'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['acceso-compras', 'acceso-caja']);
        $this->actingAs($this->user);

        $this->proveedor = Proveedor::factory()->create(['estado' => '1']);
        $this->producto = Producto::factory()->conExistencias(0)->create([
            'precio_compra' => 0,
            'precio_venta' => 100,
            'activo' => true,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'tipo_documento' => 'Factura',
            'numero_documento' => 'F001-001',
            'observaciones' => 'Compra de prueba',
            'detalle' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'costo_unitario' => 50.00,
                ],
            ],
        ], $overrides);
    }

    private function auditoriaCompra(Compra $compra, string $accion): ?RegistroAuditoria
    {
        return RegistroAuditoria::query()
            ->where('auditable_type', Compra::class)
            ->where('auditable_id', $compra->id)
            ->where('accion', $accion)
            ->latest('id')
            ->first();
    }

    public function test_crear_borrador_registra_accion_propia_y_edicion_registra_actualizar_borrador(): void
    {
        $this->post(route('compras.store'), $this->payload())
            ->assertRedirect();

        $compra = Compra::firstOrFail();

        $creacion = $this->auditoriaCompra($compra, 'crear borrador');

        $this->assertNotNull($creacion);
        $this->assertNull($creacion->datos_antes);
        $this->assertSame('borrador', $creacion->datos_despues['estado']);
        $this->assertNull($this->auditoriaCompra($compra, 'crear'));
        $this->assertNull($this->auditoriaCompra($compra, 'actualizar'));

        $this->put(route('compras.update', $compra), $this->payload([
            'numero_documento' => 'F001-002',
            'detalle' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 12,
                    'costo_unitario' => 50.00,
                ],
            ],
        ]))->assertRedirect();

        $edicion = $this->auditoriaCompra($compra, 'actualizar borrador');

        $this->assertNotNull($edicion);
        $this->assertSame('F001-001', $edicion->datos_antes['numero_documento']);
        $this->assertSame('F001-002', $edicion->datos_despues['numero_documento']);
        $this->assertArrayNotHasKey('estado', $edicion->datos_antes);
        $this->assertNull($this->auditoriaCompra($compra, 'actualizar'));
    }

    public function test_editar_solo_lineas_del_borrador_registra_actualizar_borrador(): void
    {
        $this->post(route('compras.store'), $this->payload())->assertRedirect();

        $compra = Compra::firstOrFail();

        $this->put(route('compras.update', $compra), $this->payload([
            'detalle' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 20,
                    'costo_unitario' => 25.00,
                ],
            ],
        ]))->assertRedirect();

        $edicion = $this->auditoriaCompra($compra, 'actualizar borrador');

        $this->assertNotNull($edicion);
        $this->assertNull($edicion->datos_antes);
        $this->assertNull($edicion->datos_despues);
        $this->assertNull($this->auditoriaCompra($compra, 'actualizar'));
    }

    public function test_recibir_borrador_registra_recibir_compra_y_no_actualizar(): void
    {
        Caja::create([
            'user_id' => $this->user->id,
            'estado' => 'abierta',
            'monto_inicial' => 500,
            'fecha_apertura' => now(),
        ]);

        $this->post(route('compras.store'), $this->payload())->assertRedirect();

        $compra = Compra::firstOrFail();

        $this->post(route('compras.recibir', $compra))
            ->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('success');

        $recepcion = $this->auditoriaCompra($compra, 'recibir compra');

        $this->assertNotNull($recepcion);
        $this->assertSame('borrador', $recepcion->datos_antes['estado']);
        $this->assertSame('recibida', $recepcion->datos_despues['estado']);
        $this->assertNotNull($recepcion->datos_despues['correlativo']);
        $this->assertNull($this->auditoriaCompra($compra, 'actualizar'));
    }
}
