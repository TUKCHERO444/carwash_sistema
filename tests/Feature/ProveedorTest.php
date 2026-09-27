<?php

namespace Tests\Feature;

use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\ProveedorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProveedorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-proveedores', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-proveedores');

        $this->actingAs($this->user);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'ruc' => '20123456789',
            'razon_social' => 'Distribuidora Andina S.A.C.',
            'direccion' => 'Av. Republica de Panama 3721, Surco',
            'estado_tributario' => 'Activo',
            'condicion' => 'Contribuyente',
            'estado' => '1',
        ], $overrides);
    }

    // ─── RUC: 11 dígitos obligatorios (backend) ─────────────────────────────

    /** @test */
    public function store_acepta_ruc_de_exactamente_11_digitos(): void
    {
        $response = $this->post(route('proveedores.store'), $this->payload());

        $response->assertRedirect(route('proveedores.index'));
        $this->assertDatabaseHas('proveedores', [
            'ruc' => '20123456789',
            'razon_social' => 'Distribuidora Andina S.A.C.',
            'estado' => 1,
        ]);
    }

    /** @test */
    public function store_rechaza_ruc_con_menos_de_11_digitos(): void
    {
        $response = $this->post(route('proveedores.store'), $this->payload(['ruc' => '2012345678']));

        $response->assertSessionHasErrors('ruc');
        $this->assertDatabaseCount('proveedores', 0);
    }

    /** @test */
    public function store_rechaza_ruc_con_mas_de_11_digitos(): void
    {
        $response = $this->post(route('proveedores.store'), $this->payload(['ruc' => '201234567890']));

        $response->assertSessionHasErrors('ruc');
        $this->assertDatabaseCount('proveedores', 0);
    }

    /** @test */
    public function store_rechaza_ruc_con_letras(): void
    {
        $response = $this->post(route('proveedores.store'), $this->payload(['ruc' => '2012345678A']));

        $response->assertSessionHasErrors('ruc');
        $this->assertDatabaseCount('proveedores', 0);
    }

    /** @test */
    public function store_rechaza_ruc_vacio(): void
    {
        $response = $this->post(route('proveedores.store'), $this->payload(['ruc' => '']));

        $response->assertSessionHasErrors('ruc');
        $this->assertDatabaseCount('proveedores', 0);
    }

    /** @test */
    public function store_rechaza_ruc_duplicado(): void
    {
        Proveedor::factory()->create(['ruc' => '20123456789']);

        $response = $this->post(route('proveedores.store'), $this->payload(['ruc' => '20123456789']));

        $response->assertSessionHasErrors('ruc');
        $this->assertDatabaseCount('proveedores', 1);
    }

    // ─── Razón social ───────────────────────────────────────────────────────

    /** @test */
    public function store_rechaza_razon_social_vacia(): void
    {
        $response = $this->post(route('proveedores.store'), $this->payload(['razon_social' => '']));

        $response->assertSessionHasErrors('razon_social');
        $this->assertDatabaseCount('proveedores', 0);
    }

    /** @test */
    public function store_acepta_razon_social_con_puntos_y_guiones(): void
    {
        $razon = 'S.A.C. Distribuciones "El Motor" & CIA - Sur';

        $response = $this->post(route('proveedores.store'), $this->payload(['razon_social' => $razon]));

        $response->assertRedirect(route('proveedores.index'));
        $this->assertDatabaseHas('proveedores', ['razon_social' => $razon]);
    }

    // ─── Campos opcionales ──────────────────────────────────────────────────

    /** @test */
    public function store_acepta_campos_opcionales_vacios(): void
    {
        $response = $this->post(route('proveedores.store'), $this->payload([
            'direccion' => '',
            'estado_tributario' => '',
            'condicion' => '',
        ]));

        $response->assertRedirect(route('proveedores.index'));

        $proveedor = Proveedor::firstOrFail();
        $this->assertNull($proveedor->direccion);
        $this->assertNull($proveedor->estado_tributario);
        $this->assertNull($proveedor->condicion);
    }

    // ─── Estado: char(1) numérico ───────────────────────────────────────────

    /** @test */
    public function store_rechaza_estado_distinto_de_0_o_1(): void
    {
        $response = $this->post(route('proveedores.store'), $this->payload(['estado' => '2']));

        $response->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('proveedores', 0);
    }

    /** @test */
    public function store_rechaza_estado_alfabetico(): void
    {
        $response = $this->post(route('proveedores.store'), $this->payload(['estado' => 'A']));

        $response->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('proveedores', 0);
    }

    /** @test */
    public function store_rechaza_estado_vacio(): void
    {
        $response = $this->post(route('proveedores.store'), $this->payload(['estado' => '']));

        $response->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('proveedores', 0);
    }

    /** @test */
    public function store_persiste_estado_cero(): void
    {
        $response = $this->post(route('proveedores.store'), $this->payload(['estado' => '0']));

        $response->assertRedirect(route('proveedores.index'));
        $this->assertDatabaseHas('proveedores', ['ruc' => '20123456789', 'estado' => 0]);
    }

    // ─── Update ─────────────────────────────────────────────────────────────

    /** @test */
    public function update_persiste_los_cambios(): void
    {
        $proveedor = Proveedor::factory()->create(['ruc' => '20123456789', 'estado' => 1]);

        $response = $this->put(route('proveedores.update', $proveedor), $this->payload([
            'ruc' => '20987654321',
            'razon_social' => 'Razon Social Actualizada S.A.C.',
            'estado' => '0',
        ]));

        $response->assertRedirect(route('proveedores.index'));
        $this->assertDatabaseHas('proveedores', [
            'id' => $proveedor->id,
            'ruc' => '20987654321',
            'razon_social' => 'Razon Social Actualizada S.A.C.',
            'estado' => 0,
        ]);
    }

    /** @test */
    public function update_permite_mantener_el_mismo_ruc(): void
    {
        $proveedor = Proveedor::factory()->create(['ruc' => '20123456789']);

        $response = $this->put(route('proveedores.update', $proveedor), $this->payload(['ruc' => '20123456789']));

        $response->assertRedirect(route('proveedores.index'));
        $response->assertSessionHasNoErrors();
    }

    /** @test */
    public function update_rechaza_ruc_de_otro_proveedor(): void
    {
        Proveedor::factory()->create(['ruc' => '20123456789']);
        $otro = Proveedor::factory()->create(['ruc' => '20987654321']);

        $response = $this->put(route('proveedores.update', $otro), $this->payload(['ruc' => '20123456789']));

        $response->assertSessionHasErrors('ruc');
        $this->assertDatabaseHas('proveedores', ['id' => $otro->id, 'ruc' => '20987654321']);
    }

    /** @test */
    public function update_rechaza_ruc_invalido(): void
    {
        $proveedor = Proveedor::factory()->create(['ruc' => '20123456789']);

        $response = $this->put(route('proveedores.update', $proveedor), $this->payload(['ruc' => '123']));

        $response->assertSessionHasErrors('ruc');
    }

    // ─── Destroy ────────────────────────────────────────────────────────────

    /** @test */
    public function destroy_elimina_el_proveedor(): void
    {
        $proveedor = Proveedor::factory()->create();

        $response = $this->delete(route('proveedores.destroy', $proveedor));

        $response->assertRedirect(route('proveedores.index'));
        $this->assertDatabaseMissing('proveedores', ['id' => $proveedor->id]);
    }

    // ─── Vistas y rutas estáticas antes del resource ────────────────────────

    /** @test */
    public function index_muestra_los_proveedores(): void
    {
        Proveedor::factory()->create([
            'ruc' => '20123456789',
            'razon_social' => 'Distribuidora Visible S.A.C.',
        ]);

        $this->get(route('proveedores.index'))
            ->assertOk()
            ->assertSee('Distribuidora Visible S.A.C.')
            ->assertSee('20123456789', false)
            ->assertSee('Activo');
    }

    /** @test */
    public function index_pagina_de_diez_en_diez(): void
    {
        Proveedor::factory()->count(12)->create();

        $response = $this->get(route('proveedores.index'));

        $response->assertOk();
        $this->assertSame(10, $response->viewData('proveedores')->perPage());
        $this->assertCount(10, $response->viewData('proveedores')->items());
    }

    // ─── Permisos ───────────────────────────────────────────────────────────

    /** @test */
    public function usuario_sin_permimiento_no_accede(): void
    {
        $otro = User::factory()->create();

        // El proyecto redirige al dashboard con flash de error (ver bootstrap/app.php).
        $this->actingAs($otro)->get(route('proveedores.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->actingAs($otro)->get(route('proveedores.create'))->assertRedirect(route('dashboard'));
        $this->actingAs($otro)->post(route('proveedores.store'), $this->payload())->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('proveedores', 0);
    }

    /** @test */
    public function visitante_no_accede(): void
    {
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('proveedores.index'))->assertRedirect(route('login'));
    }

    /** @test */
    public function el_seeder_genera_proveedores_con_ruc_valido(): void
    {
        $this->seed(ProveedorSeeder::class);

        $this->assertSame(6, Proveedor::count());

        foreach (Proveedor::all() as $proveedor) {
            $this->assertMatchesRegularExpression('/^\d{11}$/', $proveedor->ruc);
            $this->assertNotEmpty($proveedor->razon_social);
            $this->assertContains($proveedor->estado, [0, 1]);
        }
    }

    // ─── Modelo ─────────────────────────────────────────────────────────────

    /** @test */
    public function el_modelo_normaliza_el_ruc_sin_no_digitos(): void
    {
        $proveedor = Proveedor::create([
            'ruc' => ' 205-123-45678 ',
            'razon_social' => 'Prueba Normalizada S.A.C.',
            'estado' => 1,
        ]);

        $this->assertSame('20512345678', $proveedor->fresh()->ruc);
    }
}
