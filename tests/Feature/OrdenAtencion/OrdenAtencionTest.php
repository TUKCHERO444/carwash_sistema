<?php

namespace Tests\Feature\OrdenAtencion;

use App\Models\Caja;
use App\Models\CambioAceite;
use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\Producto;
use App\Models\TipoVehiculo;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Orden de atención: inicio al registrar el ticket, fin y duración
 * en minutos al confirmar/pagar, y cola ordenada por registro.
 */
class OrdenAtencionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private TipoVehiculo $tipoVehiculo;

    private Trabajador $trabajador;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-ventas', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-ventas');
        $this->tipoVehiculo = TipoVehiculo::factory()->create(['precio' => 50]);
        $this->trabajador = Trabajador::factory()->create(['estado' => true]);
        $this->producto = Producto::factory()->create([
            'precio_compra' => 20.00,
            'precio_venta' => 35.00,
            'stock' => 10,
            'inventario' => 10,
            'activo' => true,
        ]);
    }

    public function test_store_lavado_registra_inicio_de_atencion(): void
    {
        $this->actingAs($this->user)->post(route('lavados.store'), [
            'tipo_vehiculo_id' => $this->tipoVehiculo->id,
            'placa' => 'ABC123',
            'fecha' => now()->toDateString(),
            'trabajadores_ids' => [$this->trabajador->id],
        ])->assertRedirect(route('lavados.index'));

        $lavado = Lavado::where('vehiculo_id', 'ABC123')->firstOrFail();
        $this->assertEquals('pendiente', $lavado->estado);
        $this->assertNotNull($lavado->atencion_inicio_at);
        $this->assertNull($lavado->atencion_fin_at);
        $this->assertNull($lavado->tiempo_atencion_minutos);
    }

    public function test_store_cambio_registra_inicio_de_atencion(): void
    {
        $this->actingAs($this->user)->post('/cambio-aceite', [
            'placa' => 'XYZ789',
            'trabajadores_ids' => [$this->trabajador->id],
            'fecha' => now()->toDateString(),
            'productos' => [
                ['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio' => 35.00, 'total' => 35.00],
            ],
        ])->assertRedirect(route('cambio-aceite.index'));

        $cambio = CambioAceite::where('vehiculo_id', 'XYZ789')->firstOrFail();
        $this->assertNotNull($cambio->atencion_inicio_at);
        $this->assertNull($cambio->atencion_fin_at);
        $this->assertNull($cambio->tiempo_atencion_minutos);
    }

    public function test_confirmar_lavado_guarda_fin_y_tiempo_en_minutos(): void
    {
        Caja::factory()->abierta()->create();
        $lavado = Lavado::factory()->create([
            'atencion_inicio_at' => now()->subMinutes(90),
            'created_at' => now()->subMinutes(90),
        ]);

        $this->actingAs($this->user)->post(route('lavados.procesarConfirmacion', $lavado), [
            'placa' => 'ABC123',
            'tipo_vehiculo_id' => $this->tipoVehiculo->id,
            'fecha' => now()->toDateString(),
            'trabajadores_ids' => [$this->trabajador->id],
            'precio' => 50,
            'total' => 50,
            'metodo_pago' => 'efectivo',
        ])->assertRedirect(route('lavados.index'));

        $lavado->refresh();
        $this->assertEquals('confirmado', $lavado->estado);
        $this->assertNotNull($lavado->atencion_fin_at);
        $this->assertNotNull($lavado->tiempo_atencion_minutos);
        $this->assertGreaterThanOrEqual(89, $lavado->tiempo_atencion_minutos);
        $this->assertEquals('1 h 30 min', $lavado->tiempo_atencion_label);
    }

    public function test_confirmar_cambio_guarda_fin_y_tiempo_en_minutos(): void
    {
        Caja::factory()->abierta()->create();
        $cliente = Cliente::factory()->create();
        $cambio = CambioAceite::create([
            'cliente_id' => $cliente->id,
            'user_id' => $this->user->id,
            'fecha' => now()->toDateString(),
            'precio' => 35.00,
            'total' => 35.00,
            'estado' => 'pendiente',
            'atencion_inicio_at' => now()->subMinutes(45),
            'created_at' => now()->subMinutes(45),
        ]);
        $cambio->productos()->attach($this->producto->id, [
            'cantidad' => 1, 'precio' => 35.00, 'total' => 35.00,
        ]);

        $this->actingAs($this->user)->post("/cambio-aceite/{$cambio->id}/confirmar", [
            'placa' => 'ABC123',
            'trabajadores_ids' => [$this->trabajador->id],
            'fecha' => now()->toDateString(),
            'precio' => 35.00,
            'total' => 35.00,
            'metodo_pago' => 'efectivo',
            'productos' => [
                ['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio' => 35.00, 'total' => 35.00],
            ],
        ])->assertRedirect(route('cambio-aceite.index'));

        $cambio->refresh();
        $this->assertEquals('confirmado', $cambio->estado);
        $this->assertNotNull($cambio->atencion_fin_at);
        $this->assertGreaterThanOrEqual(44, $cambio->tiempo_atencion_minutos);
        $this->assertEquals('45 min', $cambio->tiempo_atencion_label);
    }

    public function test_cola_muestra_ultimos_registrados_primero(): void
    {
        $cliente = Cliente::factory()->create();

        foreach (['AAA111' => 3, 'BBB222' => 2, 'CCC333' => 1] as $placa => $horas) {
            Vehiculo::create(['placa' => $placa, 'cliente_id' => $cliente->id]);
            $t = Lavado::factory()->create([
                'vehiculo_id' => $placa,
                'estado' => 'pendiente',
                'created_at' => now()->subHours($horas),
                'atencion_inicio_at' => now()->subHours($horas),
            ]);
            $t->trabajadores()->sync([$this->trabajador->id]);
        }

        $html = $this->withoutVite()->actingAs($this->user)
            ->get(route('lavados.index'))->assertStatus(200)->getContent();

        $posNuevo = strpos($html, 'CCC333');
        $posMedio = strpos($html, 'BBB222');
        $posViejo = strpos($html, 'AAA111');

        $this->assertNotFalse($posNuevo);
        $this->assertTrue($posNuevo < $posMedio && $posMedio < $posViejo);
    }

    public function test_confirmados_ordenados_por_fin_de_atencion(): void
    {
        $cliente = Cliente::factory()->create();

        foreach (['AAA111' => 5, 'BBB222' => 1] as $placa => $horas) {
            Vehiculo::create(['placa' => $placa, 'cliente_id' => $cliente->id]);
            Lavado::factory()->confirmado()->create([
                'vehiculo_id' => $placa,
                'atencion_inicio_at' => now()->subHours($horas + 1),
                'atencion_fin_at' => now()->subHours($horas),
            ]);
        }

        $html = $this->withoutVite()->actingAs($this->user)
            ->get(route('lavados.confirmados'))->assertStatus(200)->getContent();

        $this->assertTrue(strpos($html, 'BBB222') < strpos($html, 'AAA111'));
        $this->assertStringContainsString('45 min', $html);
    }
}
