<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CajaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 0 — Medios de pago con los que se puede registrar un egreso de caja.
 *
 * El enum original solo admitía efectivo y yape, pensados para el cobro al cliente
 * final. Una compra a proveedor Lima se paga por transferencia bancaria casi
 * siempre, así que el enum se amplía antes de construir el módulo de compras.
 *
 * @see .kiro/specs/compras-ingreso-mercaderia/requirements.md (requisito 46)
 */
class EgresoCajaTipoPagoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CajaService $cajaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->cajaService = app(CajaService::class);
        $this->actingAs($this->user);
    }

    private function cajaAbierta()
    {
        return $this->cajaService->abrirCaja(100.00, $this->user->id);
    }

    private function registrarEgreso(string $tipoPago): void
    {
        $this->cajaService->registrarEgreso($this->cajaAbierta(), [
            'monto' => 150.00,
            'descripcion' => 'Pago a proveedor',
            'tipo_pago' => $tipoPago,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function admite_efectivo(): void
    {
        $this->registrarEgreso('efectivo');

        $this->assertDatabaseHas('egresos_caja', [
            'tipo_pago' => 'efectivo',
            'monto' => 150.00,
        ]);
    }

    /** @test */
    public function admite_yape(): void
    {
        $this->registrarEgreso('yape');

        $this->assertDatabaseHas('egresos_caja', ['tipo_pago' => 'yape']);
    }

    /** @test */
    public function admite_transferencia(): void
    {
        $this->registrarEgreso('transferencia');

        $this->assertDatabaseHas('egresos_caja', [
            'tipo_pago' => 'transferencia',
            'monto' => 150.00,
        ]);
    }

    /** @test */
    public function admite_tarjeta(): void
    {
        $this->registrarEgreso('tarjeta');

        $this->assertDatabaseHas('egresos_caja', ['tipo_pago' => 'tarjeta']);
    }

    /** @test */
    public function rechaza_un_tipo_de_pago_inexistente(): void
    {
        $this->expectException(QueryException::class);

        $this->registrarEgreso('bitcoin');
    }

    /** @test */
    public function el_egreso_queda_sujeto_a_la_caja_abierta(): void
    {
        $caja = $this->cajaAbierta();
        $this->cajaService->cerrarCaja($caja);

        $this->expectException(\RuntimeException::class);

        $this->cajaService->registrarEgreso($caja->fresh(), [
            'monto' => 150.00,
            'descripcion' => 'Pago a proveedor',
            'tipo_pago' => 'transferencia',
            'user_id' => $this->user->id,
        ]);
    }
}
