<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\EgresoCaja;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EgresoClasificadoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-caja', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-caja');
        $this->actingAs($this->user);
    }

    private function cajaAbierta(): Caja
    {
        return Caja::create([
            'user_id' => $this->user->id,
            'estado' => 'abierta',
            'monto_inicial' => 500,
            'fecha_apertura' => now(),
        ]);
    }

    public function test_registra_egreso_con_categoria_y_trabajador(): void
    {
        $caja = $this->cajaAbierta();
        $trabajador = Trabajador::factory()->create();

        $this->post(route('caja.egresos.store'), [
            'monto' => 80,
            'descripcion' => 'Pago semanal',
            'tipo_pago' => 'efectivo',
            'categoria' => 'pago_trabajador',
            'trabajador_id' => $trabajador->id,
        ])->assertRedirect(route('caja.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('egresos_caja', [
            'caja_id' => $caja->id,
            'monto' => 80,
            'categoria' => 'pago_trabajador',
            'trabajador_id' => $trabajador->id,
            'user_id' => $this->user->id,
        ]);

        // El balance refleja el egreso como siempre.
        $resumen = app(CajaService::class)->calcularResumen($caja);
        $this->assertEqualsWithDelta(80, $resumen['total_egresos'], 0.01);
        $this->assertEqualsWithDelta(420, $resumen['balance_final'], 0.01);
    }

    public function test_categoria_obligatoria_y_compra_reservada_al_flujo(): void
    {
        $this->cajaAbierta();

        // Sin categoría se rechaza.
        $this->post(route('caja.egresos.store'), [
            'monto' => 50, 'descripcion' => 'Insumos', 'tipo_pago' => 'efectivo',
        ])->assertSessionHasErrors('categoria');

        // compra_abastecimiento no admite egreso manual (flujo de Compras).
        $this->post(route('caja.egresos.store'), [
            'monto' => 50, 'descripcion' => 'Mercadería', 'tipo_pago' => 'efectivo',
            'categoria' => 'compra_abastecimiento',
        ])->assertSessionHasErrors('categoria');

        $this->assertSame(0, EgresoCaja::count());
    }

    public function test_compra_recibida_clasifica_su_egreso_sin_duplicar(): void
    {
        Permission::firstOrCreate(['name' => 'acceso-compras', 'guard_name' => 'web']);
        $this->user->givePermissionTo('acceso-compras');
        $caja = $this->cajaAbierta();

        $proveedor = Proveedor::factory()->create();
        $producto = Producto::factory()->conExistencias(10)->create();
        $compra = Compra::create([
            'proveedor_id' => $proveedor->id, 'fecha' => now()->toDateString(),
            'estado' => 'borrador', 'subtotal' => 100, 'total' => 100,
            'user_id' => $this->user->id,
        ]);
        DetalleCompra::create([
            'compra_id' => $compra->id, 'producto_id' => $producto->id,
            'cantidad' => 2, 'costo_unitario' => 50, 'subtotal' => 100,
        ]);

        $this->post(route('compras.recibir', $compra))->assertRedirect();

        // Un solo egreso, clasificado como abastecimiento, sin duplicados.
        $this->assertSame(1, EgresoCaja::where('caja_id', $caja->id)->count());
        $this->assertDatabaseHas('egresos_caja', [
            'caja_id' => $caja->id,
            'categoria' => 'compra_abastecimiento',
            'monto' => 100,
        ]);
    }

    public function test_panel_muestra_categoria_y_audita_el_egreso(): void
    {
        $this->cajaAbierta();

        $this->post(route('caja.egresos.store'), [
            'monto' => 50, 'descripcion' => 'Productos de limpieza',
            'tipo_pago' => 'efectivo', 'categoria' => 'insumos_operativos',
        ]);

        $this->get(route('caja.index'))
            ->assertOk()
            ->assertSee('Insumos operativos');

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'caja',
            'accion' => 'registrar egreso',
            'usuario_id' => $this->user->id,
        ]);
    }

    public function test_catalogo_centralizado_y_etiquetas(): void
    {
        // Feature: egreso-clasificado, Property: catálogo único y ampliable.
        $this->assertArrayHasKey('otros', EgresoCaja::CATEGORIAS);
        $this->assertArrayHasKey('pago_trabajador', EgresoCaja::CATEGORIAS);
        $this->assertArrayNotHasKey('compra_abastecimiento', EgresoCaja::categoriasManuales());
        $this->assertSame('Insumos operativos', EgresoCaja::etiquetaCategoria('insumos_operativos'));
        $this->assertSame('Sin clasificar', EgresoCaja::etiquetaCategoria(null));
        $this->assertSame('Sin clasificar', EgresoCaja::etiquetaCategoria('inexistente'));
    }
}
