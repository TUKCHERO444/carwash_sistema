<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\EgresoCaja;
use App\Models\User;
use App\Models\Venta;
use App\Services\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CajaArqueoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['acceso-caja', 'historial-caja'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['acceso-caja', 'historial-caja']);
        $this->actingAs($this->user);
    }

    private function cajaAbierta(float $inicial = 100): Caja
    {
        return Caja::create([
            'user_id' => $this->user->id,
            'estado' => 'abierta',
            'monto_inicial' => $inicial,
            'fecha_apertura' => now(),
        ]);
    }

    private function venta(Caja $caja, float $total, string $metodo = 'efectivo'): Venta
    {
        return Venta::create([
            'correlativo' => 'VTA-'.str_pad((Venta::max('id') ?? 0) + 1, 4, '0', STR_PAD_LEFT),
            'subtotal' => $total,
            'total' => $total,
            'metodo_pago' => $metodo,
            'user_id' => $this->user->id,
            'caja_id' => $caja->id,
        ]);
    }

    public function test_cerrar_exige_efectivo_contado(): void
    {
        $this->cajaAbierta();

        $this->post(route('caja.cerrar'), [])
            ->assertSessionHasErrors('efectivo_contado');

        $this->assertEquals('abierta', Caja::first()->estado);
    }

    public function test_esperado_solo_efectivo_fisico(): void
    {
        // Feature: arqueo-cierre-caja, Property 1: esperado = inicial + efectivo − egresos efectivo.
        $caja = $this->cajaAbierta(100);
        $this->venta($caja, 200, 'efectivo');
        $this->venta($caja, 500, 'yape');
        $this->venta($caja, 300, 'izipay');
        EgresoCaja::create([
            'caja_id' => $caja->id, 'monto' => 50, 'descripcion' => 'Retiro',
            'tipo_pago' => 'efectivo', 'user_id' => $this->user->id,
        ]);
        EgresoCaja::create([
            'caja_id' => $caja->id, 'monto' => 400, 'descripcion' => 'Pago Yape',
            'tipo_pago' => 'yape', 'user_id' => $this->user->id,
        ]);

        $esperado = app(CajaService::class)->calcularEfectivoEsperado($caja);

        // 100 + 200 − 50 = 250 (yape/izipay no son físico).
        $this->assertEqualsWithDelta(250, $esperado, 0.01);
    }

    public function test_cierre_coincide_sobrante_y_faltante(): void
    {
        // Feature: arqueo-cierre-caja, Property 2: diferencia = contado − esperado.
        foreach ([250 => 0.0, 260 => 10.0, 240 => -10.0] as $contado => $diferencia) {
            $caja = $this->cajaAbierta(100);
            $this->venta($caja, 200, 'efectivo');
            EgresoCaja::create([
                'caja_id' => $caja->id, 'monto' => 50, 'descripcion' => 'Retiro',
                'tipo_pago' => 'efectivo', 'user_id' => $this->user->id,
            ]);

            $this->post(route('caja.cerrar'), [
                'efectivo_contado' => $contado,
                'observacion_arqueo' => $diferencia == 0.0 ? null : 'Ajuste de prueba',
            ])->assertRedirect(route('caja.index'));

            $caja->refresh();
            $this->assertSame('cerrada', $caja->estado);
            $this->assertEqualsWithDelta(250, (float) $caja->efectivo_esperado, 0.01);
            $this->assertEqualsWithDelta($contado, (float) $caja->efectivo_contado, 0.01);
            $this->assertEqualsWithDelta($diferencia, (float) $caja->diferencia, 0.01);
            $this->assertSame($this->user->id, $caja->arqueo_user_id);
            $this->assertNotNull($caja->fecha_arqueo);
            $this->assertNotNull($caja->fecha_cierre);
        }

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'caja',
            'accion' => 'cerrar caja',
            'usuario_id' => $this->user->id,
        ]);
    }

    public function test_observacion_obligatoria_con_diferencia(): void
    {
        $this->cajaAbierta(100);

        $this->post(route('caja.cerrar'), ['efectivo_contado' => 50])
            ->assertSessionHasErrors('observacion_arqueo');

        $this->assertEquals('abierta', Caja::first()->estado);
        $this->assertNull(Caja::first()->diferencia);
    }

    public function test_caja_cerrada_no_permite_modificar_arqueo(): void
    {
        $caja = $this->cajaAbierta(100);

        $this->post(route('caja.cerrar'), ['efectivo_contado' => 100])
            ->assertRedirect(route('caja.index'));

        // Sin caja activa, un segundo cierre se rechaza sin tocar el arqueo.
        $this->post(route('caja.cerrar'), ['efectivo_contado' => 999])
            ->assertSessionHas('error');

        $caja->refresh();
        $this->assertEqualsWithDelta(0, (float) $caja->diferencia, 0.01);
        $this->assertEqualsWithDelta(100, (float) $caja->efectivo_contado, 0.01);
    }

    public function test_panel_muestra_esperado_y_detalle_muestra_arqueo(): void
    {
        $caja = $this->cajaAbierta(100);
        $this->venta($caja, 50, 'efectivo');

        $this->get(route('caja.index'))
            ->assertOk()
            ->assertSee('S/ 150.00');

        $this->post(route('caja.cerrar'), ['efectivo_contado' => 140, 'observacion_arqueo' => 'Faltante de prueba']);

        $this->get(route('caja.detalle', $caja->id))
            ->assertOk()
            ->assertSee('Arqueo de Caja')
            ->assertSee('Faltante')
            ->assertSee('Faltante de prueba');

        $this->get(route('caja.historial'))
            ->assertOk()
            ->assertSee('Diferencia');
    }

    public function test_detalle_de_caja_legada_sin_arqueo(): void
    {
        $caja = $this->cajaAbierta(100);
        app(CajaService::class)->cerrarCaja($caja);

        $this->get(route('caja.detalle', $caja->id))
            ->assertOk()
            ->assertSee('régimen anterior');
    }
}
