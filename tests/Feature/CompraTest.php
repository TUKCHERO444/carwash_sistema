<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tests CRUD del módulo de compras (Fase 2).
 *
 * Feature: compras-ingreso-mercaderia
 * Cubre requisitos 20-28, 52-58.
 */
class CompraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-compras', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'acceso-caja', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['acceso-compras', 'acceso-caja']);

        $this->proveedor = Proveedor::factory()->create(['estado' => '1']);

        $this->producto1 = Producto::factory()->conExistencias(0)->create(['precio_compra' => 0, 'precio_venta' => 100]);
        $this->producto2 = Producto::factory()->conExistencias(0)->create(['precio_compra' => 0, 'precio_venta' => 200]);
    }

    // ========== INDEX ==========

    /** @test */
    public function index_muestra_compras_paginadas(): void
    {
        Compra::factory()->count(15)->create();

        $response = $this->actingAs($this->user)->get(route('compras.index'));

        $response->assertOk()
            ->assertViewIs('compras.index')
            ->assertViewHas('compras');
    }

    /** @test */
    public function index_filtra_por_estado(): void
    {
        Compra::factory()->borrador()->count(3)->create();
        Compra::factory()->recibida()->count(2)->create();
        Compra::factory()->anulada()->count(1)->create();

        $response = $this->actingAs($this->user)
            ->get(route('compras.index', ['estado' => 'borrador']));

        $response->assertOk();
        $compras = $response->viewData('compras');
        $this->assertTrue($compras->every(fn ($c) => $c->estado === 'borrador'));
    }

    /** @test */
    public function index_filtra_por_proveedor(): void
    {
        $proveedor2 = Proveedor::factory()->create(['estado' => '1']);
        Compra::factory()->count(3)->create(['proveedor_id' => $this->proveedor->id]);
        Compra::factory()->count(2)->create(['proveedor_id' => $proveedor2->id]);

        $response = $this->actingAs($this->user)
            ->get(route('compras.index', ['proveedor_id' => $this->proveedor->id]));

        $response->assertOk();
        $compras = $response->viewData('compras');
        $this->assertTrue($compras->every(fn ($c) => $c->proveedor_id === $this->proveedor->id));
    }

    // ========== CREATE ==========

    /** @test */
    public function store_crea_compra_como_borrador(): void
    {
        $caja = Caja::create([
            'user_id' => $this->user->id,
            'estado' => 'abierta',
            'monto_inicial' => 500,
            'fecha_apertura' => now(),
        ]);

        $payload = [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'tipo_documento' => 'Factura',
            'numero_documento' => 'F001-001',
            'observaciones' => 'Compra de prueba',
            'detalle' => [
                [
                    'producto_id' => $this->producto1->id,
                    'cantidad' => 10,
                    'costo_unitario' => 50.00,
                ],
                [
                    'producto_id' => $this->producto2->id,
                    'cantidad' => 5,
                    'costo_unitario' => 200.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('compras.store'), $payload);

        $response->assertRedirect(route('compras.show', Compra::first()))
            ->assertSessionHas('success');

        $compra = Compra::first();
        $this->assertEquals('borrador', $compra->estado);
        $this->assertNull($compra->correlativo);
        $this->assertEquals(1500.00, $compra->total);
        $this->assertCount(2, $compra->detalles);
    }

    /** @test */
    public function store_rechaza_sin_lineas(): void
    {
        $payload = [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'detalle' => [],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('compras.store'), $payload);

        $response->assertSessionHasErrors('detalle');
    }

    /** @test */
    public function store_rechaza_producto_duplicado(): void
    {
        $payload = [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'detalle' => [
                ['producto_id' => $this->producto1->id, 'cantidad' => 10, 'costo_unitario' => 50],
                ['producto_id' => $this->producto1->id, 'cantidad' => 5, 'costo_unitario' => 50],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('compras.store'), $payload);

        $response->assertSessionHasErrors('detalle.*.producto_id');
    }

    /** @test */
    public function store_calcula_total_en_servidor(): void
    {
        // El formulario envía subtotal y total, pero el controlador los recalcula
        $payload = [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->format('Y-m-d'),
            'detalle' => [
                ['producto_id' => $this->producto1->id, 'cantidad' => 10, 'costo_unitario' => 50.00, 'subtotal' => 9999],
                ['producto_id' => $this->producto2->id, 'cantidad' => 5, 'costo_unitario' => 200.00, 'subtotal' => 8888],
            ],
            'subtotal' => 9999,
            'total' => 8888,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('compras.store'), $payload);

        $compra = Compra::first();
        $this->assertEquals(1500.00, $compra->total); // 10*50 + 5*200 = 1500
    }

    // ========== EDIT / UPDATE ==========

    /** @test */
    public function edit_solo_borrador(): void
    {
        $compraRecibida = Compra::factory()->recibida()->create(['proveedor_id' => $this->proveedor->id]);

        $response = $this->actingAs($this->user)
            ->get(route('compras.edit', $compraRecibida));

        $response->assertRedirect(route('compras.show', $compraRecibida))
            ->assertSessionHas('error');
    }

    /** @test */
    public function update_actualiza_borrador(): void
    {
        $compra = Compra::factory()->borrador()->create([
            'proveedor_id' => $this->proveedor->id,
            'subtotal' => 500,
            'total' => 500,
        ]);
        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $this->producto1->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'subtotal' => 500,
        ]);

        $proveedor2 = Proveedor::factory()->create(['estado' => '1']);

        $payload = [
            'proveedor_id' => $proveedor2->id,
            'fecha' => now()->format('Y-m-d'),
            'detalle' => [
                ['producto_id' => $this->producto1->id, 'cantidad' => 20, 'costo_unitario' => 60],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->put(route('compras.update', $compra), $payload);

        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('success');

        $compra->refresh();
        $this->assertEquals($proveedor2->id, $compra->proveedor_id);
        $this->assertEquals(1200.00, $compra->total); // 20 * 60
    }

    /** @test */
    public function update_rechaza_si_recibida(): void
    {
        $compra = Compra::factory()->recibida()->create(['proveedor_id' => $this->proveedor->id]);

        $response = $this->actingAs($this->user)
            ->put(route('compras.update', $compra), [
                'proveedor_id' => $this->proveedor->id,
                'fecha' => now()->format('Y-m-d'),
                'detalle' => [['producto_id' => $this->producto1->id, 'cantidad' => 1, 'costo_unitario' => 10]],
            ]);

        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('error');
    }

    // ========== DESTROY ==========

    /** @test */
    public function destroy_elimina_borrador(): void
    {
        $compra = Compra::factory()->borrador()->create(['proveedor_id' => $this->proveedor->id]);
        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $this->producto1->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'subtotal' => 500,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('compras.destroy', $compra));

        $response->assertRedirect(route('compras.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('compras', ['id' => $compra->id]);
        $this->assertDatabaseMissing('detalle_compras', ['compra_id' => $compra->id]);
    }

    /** @test */
    public function destroy_rechaza_si_recibida(): void
    {
        $compra = Compra::factory()->recibida()->create(['proveedor_id' => $this->proveedor->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('compras.destroy', $compra));

        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('error');
    }

    // ========== ANULAR ==========

    /** @test */
    public function anular_borrador_cambia_estado(): void
    {
        $compra = Compra::factory()->borrador()->create(['proveedor_id' => $this->proveedor->id]);

        $response = $this->actingAs($this->user)
            ->post(route('compras.anular', $compra));

        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('success');

        $compra->refresh();
        $this->assertEquals('anulada', $compra->estado);
    }

    /** @test */
    public function anular_rechaza_si_recibida(): void
    {
        $compra = Compra::factory()->recibida()->create(['proveedor_id' => $this->proveedor->id]);

        $response = $this->actingAs($this->user)
            ->post(route('compras.anular', $compra));

        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('error');
    }

    /** @test */
    public function anular_rechaza_si_anulada(): void
    {
        $compra = Compra::factory()->anulada()->create(['proveedor_id' => $this->proveedor->id]);

        $response = $this->actingAs($this->user)
            ->post(route('compras.anular', $compra));

        $response->assertRedirect(route('compras.show', $compra))
            ->assertSessionHas('error');
    }

    // ========== SHOW ==========

    /** @test */
    public function show_muestra_compra_con_detalles(): void
    {
        $compra = Compra::factory()->borrador()->create(['proveedor_id' => $this->proveedor->id]);
        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $this->producto1->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'subtotal' => 500,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('compras.show', $compra));

        $response->assertOk()
            ->assertViewIs('compras.show')
            ->assertViewHas('compra');
    }

    // ========== PERMISOS ==========

    /** @test */
    public function usuario_sin_permiso_no_accede(): void
    {
        $userSinPermiso = User::factory()->create();

        $response = $this->actingAs($userSinPermiso)
            ->get(route('compras.index'));

        $response->assertStatus(302); // Redirige si no tiene permiso
    }

    /** @test */
    public function visitante_no_accede(): void
    {
        $response = $this->get(route('compras.index'));
        $response->assertRedirect(route('login'));
    }
}
