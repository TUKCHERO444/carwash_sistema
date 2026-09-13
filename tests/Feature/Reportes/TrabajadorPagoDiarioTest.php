<?php

namespace Tests\Feature\Reportes;

use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TrabajadorPagoDiarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-trabajadores', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('acceso-trabajadores');
    }

    private function datosTrabajador(array $extra = []): array
    {
        return array_merge([
            'dni' => '12345678',
            'nombre' => 'Juan',
            'apellido_paterno' => 'Perez',
            'apellido_materno' => 'Gomez',
            'estado' => true,
        ], $extra);
    }

    public function test_store_guarda_pago_diario_indicado(): void
    {
        $this->actingAs($this->admin)
            ->post(route('trabajadores.store'), $this->datosTrabajador(['pago_diario' => 75.5]))
            ->assertRedirect(route('trabajadores.index'));

        $trabajador = Trabajador::first();
        $this->assertEqualsWithDelta(75.5, (float) $trabajador->pago_diario, 0.01);
    }

    public function test_store_sin_pago_diario_guarda_null(): void
    {
        $this->actingAs($this->admin)
            ->post(route('trabajadores.store'), $this->datosTrabajador())
            ->assertRedirect(route('trabajadores.index'));

        $this->assertNull(Trabajador::first()->pago_diario);
    }

    public function test_store_pago_diario_negativo_rechazado(): void
    {
        $this->actingAs($this->admin)
            ->post(route('trabajadores.store'), $this->datosTrabajador(['pago_diario' => -1]))
            ->assertSessionHasErrors('pago_diario');

        $this->assertSame(0, Trabajador::count());
    }

    public function test_update_reemplaza_y_vacia_el_pago_diario(): void
    {
        $trabajador = Trabajador::factory()->create(['pago_diario' => 50]);

        $this->actingAs($this->admin)
            ->put(route('trabajadores.update', $trabajador), $this->datosTrabajador(['pago_diario' => 80]))
            ->assertRedirect(route('trabajadores.index'));

        $this->assertEqualsWithDelta(80, (float) $trabajador->fresh()->pago_diario, 0.01);

        $this->actingAs($this->admin)
            ->put(route('trabajadores.update', $trabajador), $this->datosTrabajador())
            ->assertRedirect(route('trabajadores.index'));

        $this->assertNull($trabajador->fresh()->pago_diario);
    }

    public function test_factory_default_pago_diario_es_50(): void
    {
        $trabajador = Trabajador::factory()->create();
        $this->assertEqualsWithDelta(50, (float) $trabajador->pago_diario, 0.01);
    }
}
