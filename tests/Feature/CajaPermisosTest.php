<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\EgresoCaja;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\Pagos;
use Tests\TestCase;

class CajaPermisosTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioCon(array $permisos): User
    {
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permisos);

        return $user;
    }

    private function cajaAbierta(User $user, float $inicial = 100): Caja
    {
        return Caja::create([
            'user_id' => $user->id,
            'estado' => 'abierta',
            'monto_inicial' => $inicial,
            'fecha_apertura' => now(),
        ]);
    }

    private function venta(Caja $caja, User $user, float $total): Venta
    {
        $venta = Venta::create([
            'correlativo' => 'VTA-'.str_pad((Venta::max('id') ?? 0) + 1, 4, '0', STR_PAD_LEFT),
            'subtotal' => $total,
            'total' => $total,
            'user_id' => $user->id,
            'caja_id' => $caja->id,
        ]);
        Pagos::linea($venta, 'efectivo', $total);

        return $venta;
    }

    public function test_seeders_asignan_permisos_de_caja_por_rol(): void
    {
        $this->seed(AuthSeeder::class);

        $admin = Role::findByName('Administrador', 'web');
        $vendedor = Role::findByName('Vendedor', 'web');

        $this->assertTrue($admin->hasPermissionTo('gestionar-caja'));
        $this->assertTrue($admin->hasPermissionTo('ver-resumen-caja'));
        $this->assertTrue($admin->hasPermissionTo('registrar-egresos-caja'));
        $this->assertTrue($admin->hasPermissionTo('historial-caja'));

        $this->assertTrue($vendedor->hasPermissionTo('acceso-caja'));
        $this->assertTrue($vendedor->hasPermissionTo('registrar-egresos-caja'));
        $this->assertFalse($vendedor->hasPermissionTo('gestionar-caja'));
        $this->assertFalse($vendedor->hasPermissionTo('ver-resumen-caja'));
        $this->assertFalse($vendedor->hasPermissionTo('historial-caja'));
    }

    public function test_usuario_con_resumen_ve_caja_general_completa(): void
    {
        $user = $this->usuarioCon([
            'acceso-caja',
            'gestionar-caja',
            'ver-resumen-caja',
            'registrar-egresos-caja',
            'historial-caja',
        ]);
        $caja = $this->cajaAbierta($user);
        $this->venta($caja, $user, 100);

        $this->actingAs($user)
            ->get(route('caja.index'))
            ->assertOk()
            ->assertSee('Caja General')
            ->assertSee('Monto Inicial')
            ->assertSee('Balance Neto')
            ->assertSee('Ingresos por Método de Pago')
            ->assertSee('Ingresos por Origen')
            ->assertSee('Historial')
            ->assertSee('Registrar Egreso')
            ->assertSee('Cerrar Caja');
    }

    public function test_usuario_operativo_solo_ve_estado_y_totales(): void
    {
        $user = $this->usuarioCon(['acceso-caja', 'registrar-egresos-caja']);
        $caja = $this->cajaAbierta($user);
        $this->venta($caja, $user, 100);

        EgresoCaja::create([
            'caja_id' => $caja->id,
            'monto' => 30,
            'descripcion' => 'Pago reservado del detalle',
            'categoria' => 'insumos_operativos',
            'tipo_pago' => 'efectivo',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('caja.index'))
            ->assertOk()
            ->assertSee('Caja Chica')
            ->assertSee('Caja abierta')
            ->assertSee('Total Ingresos')
            ->assertSee('S/ 100.00')
            ->assertSee('Total Egresos')
            ->assertSee('S/ 30.00')
            ->assertSee('Registrar Egreso')
            ->assertDontSee('Caja General')
            ->assertDontSee('Monto Inicial')
            ->assertDontSee('Balance Neto')
            ->assertDontSee('Ingresos por Método de Pago')
            ->assertDontSee('Ingresos por Origen')
            ->assertDontSee('Pago reservado del detalle')
            ->assertDontSee('Historial')
            ->assertDontSee('Cerrar Caja')
            ->assertDontSee('Iniciar Caja');
    }

    public function test_usuario_operativo_sin_caja_no_ve_apertura(): void
    {
        $user = $this->usuarioCon(['acceso-caja']);

        $this->actingAs($user)
            ->get(route('caja.index'))
            ->assertOk()
            ->assertSee('Caja cerrada')
            ->assertSee('Un administrador debe aperturarla')
            ->assertDontSee('Iniciar Caja')
            ->assertDontSee('Total Ingresos')
            ->assertDontSee('Total Egresos');
    }

    public function test_usuario_operativo_no_puede_abrir_cerrar_ni_consultar_historial(): void
    {
        $user = $this->usuarioCon(['acceso-caja', 'registrar-egresos-caja']);
        $caja = $this->cajaAbierta($user);

        $this->actingAs($user)
            ->post(route('caja.abrir'), ['monto_inicial' => 50])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->post(route('caja.cerrar'), ['efectivo_contado' => 100])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->get(route('caja.historial'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->get(route('caja.detalle', $caja))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    public function test_usuario_sin_acceso_caja_es_redirigido(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('caja.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    public function test_usuario_operativo_puede_registrar_egreso(): void
    {
        $user = $this->usuarioCon(['acceso-caja', 'registrar-egresos-caja']);
        $caja = $this->cajaAbierta($user);

        $this->actingAs($user)
            ->post(route('caja.egresos.store'), [
                'monto' => 25,
                'descripcion' => 'Útiles de limpieza',
                'categoria' => 'insumos_operativos',
                'tipo_pago' => 'efectivo',
            ])
            ->assertRedirect(route('caja.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('egresos_caja', [
            'caja_id' => $caja->id,
            'monto' => 25,
            'user_id' => $user->id,
        ]);
    }

    public function test_resumen_sin_gestion_no_muestra_botones_administrativos(): void
    {
        $user = $this->usuarioCon(['acceso-caja', 'ver-resumen-caja']);

        $this->actingAs($user)
            ->get(route('caja.index'))
            ->assertOk()
            ->assertSee('Caja General')
            ->assertSee('Solicita a un administrador que aperture la caja')
            ->assertDontSee('Iniciar Caja');
    }
}
