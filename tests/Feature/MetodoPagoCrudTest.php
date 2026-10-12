<?php

namespace Tests\Feature;

use App\Models\MetodoPago;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MetodoPagoCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-metodos-pago', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-metodos-pago');
    }

    public function test_index_muestra_metodos(): void
    {
        MetodoPago::create(['codigo' => 'plin', 'nombre' => 'Plin', 'orden' => 4]);

        $this->actingAs($this->user)
            ->get(route('metodos-pago.index'))
            ->assertOk()
            ->assertSee('Plin');
    }

    public function test_store_crea_metodo_y_unico_efectivo(): void
    {
        $this->actingAs($this->user)
            ->post(route('metodos-pago.store'), [
                'codigo' => 'plin',
                'nombre' => 'Plin',
                'activo' => true,
                'es_efectivo' => false,
                'orden' => 4,
            ])
            ->assertRedirect(route('metodos-pago.index'));

        $this->assertDatabaseHas('metodos_pago', ['codigo' => 'plin', 'nombre' => 'Plin']);
    }

    public function test_store_rechaza_codigo_duplicado(): void
    {
        MetodoPago::create(['codigo' => 'plin', 'nombre' => 'Plin']);

        $this->actingAs($this->user)
            ->post(route('metodos-pago.store'), ['codigo' => 'plin', 'nombre' => 'Otro'])
            ->assertSessionHasErrors('codigo');
    }

    public function test_marcar_efectivo_desmarca_el_anterior(): void
    {
        $efectivo = MetodoPago::where('codigo', 'efectivo')->firstOrFail();

        $plin = MetodoPago::create(['codigo' => 'plin', 'nombre' => 'Plin']);

        $this->actingAs($this->user)
            ->put(route('metodos-pago.update', $plin), [
                'codigo' => 'plin',
                'nombre' => 'Plin',
                'es_efectivo' => true,
            ])
            ->assertRedirect(route('metodos-pago.index'));

        $this->assertTrue($plin->fresh()->es_efectivo);
        $this->assertFalse($efectivo->fresh()->es_efectivo);
    }

    public function test_destroy_bloqueado_con_pagos_asociados(): void
    {
        $yape = MetodoPago::where('codigo', 'yape')->firstOrFail();
        $venta = Venta::create(['correlativo' => 'VTA-0001', 'total' => 50, 'user_id' => $this->user->id]);
        $venta->pagos()->create(['metodo_pago_id' => $yape->id, 'monto' => 50]);

        $this->actingAs($this->user)
            ->delete(route('metodos-pago.destroy', $yape))
            ->assertRedirect(route('metodos-pago.index'));

        $this->assertDatabaseHas('metodos_pago', ['codigo' => 'yape']);
    }

    public function test_usuario_sin_permiso_no_accede(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('metodos-pago.index'))
            ->assertRedirect(route('dashboard'));
    }
}
