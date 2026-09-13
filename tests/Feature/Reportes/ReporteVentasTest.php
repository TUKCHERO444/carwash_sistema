<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use App\Models\Venta;
use App\Services\Reportes\ReporteVentasService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteVentasTest extends TestCase
{
    use RefreshDatabase;

    private ReporteVentasService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReporteVentasService::class);
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 8, 20, 12, 0, 0));

        Permission::firstOrCreate(['name' => 'acceso-reportes', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('acceso-reportes');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function venta(array $atributos = []): Venta
    {
        return Venta::create(array_merge([
            'correlativo' => 'V1-00000'.fake()->unique()->numberBetween(1, 999),
            'total' => 100,
            'metodo_pago' => 'efectivo',
            'user_id' => User::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $atributos));
    }

    public function test_filtros_por_usuario_metodo_y_correlativo(): void
    {
        $usuario = User::factory()->create();
        $ventaA = $this->venta(['user_id' => $usuario->id, 'total' => 50, 'metodo_pago' => 'yape', 'correlativo' => 'AAA-1']);
        $this->venta(['total' => 200, 'metodo_pago' => 'efectivo', 'correlativo' => 'BBB-2']);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $kpis = $this->service->kpis($desde, $hasta, ['user_id' => $usuario->id]);
        $this->assertSame(1, $kpis['operaciones']);
        $this->assertEqualsWithDelta(50, $kpis['total'], 0.01);

        $kpis = $this->service->kpis($desde, $hasta, ['metodo_pago' => 'efectivo']);
        $this->assertSame(1, $kpis['operaciones']);

        $detalle = $this->service->detalle($desde, $hasta, ['correlativo' => 'AAA']);
        $this->assertSame($ventaA->id, $detalle->items()[0]->id);
    }

    public function test_agregados_por_usuario_y_metodo(): void
    {
        $usuario = User::factory()->create();
        $this->venta(['user_id' => $usuario->id, 'total' => 30, 'metodo_pago' => 'yape']);
        $this->venta(['user_id' => $usuario->id, 'total' => 70, 'metodo_pago' => 'yape']);

        $desde = CarbonImmutable::today()->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $porUsuario = $this->service->porUsuario($desde, $hasta);
        $this->assertCount(1, $porUsuario);
        $this->assertSame($usuario->name, $porUsuario[0]['usuario']);
        $this->assertEqualsWithDelta(100, $porUsuario[0]['total'], 0.01);
        $this->assertSame(2, $porUsuario[0]['operaciones']);

        $porMetodo = $this->service->porMetodo($desde, $hasta);
        $this->assertSame('yape', $porMetodo[0]['metodo']);
    }

    public function test_ruta_descarga_csv_con_detalle_sin_paginar(): void
    {
        $this->venta(['total' => 50]);

        $respuesta = $this->actingAs($this->admin)
            ->get(route('reportes.ventas', ['export' => 'csv', 'desde' => now()->toDateString(), 'hasta' => now()->toDateString()]));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('correlativo;fecha;usuario;metodo_pago;total', $contenido);
        $this->assertStringContainsString('50', $contenido);
    }

    public function test_validacion_fecha_invalida_responde_con_errores_de_sesion(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reportes.ventas', ['desde' => '2026-13-01']))
            ->assertSessionHasErrors('desde');
    }
}
