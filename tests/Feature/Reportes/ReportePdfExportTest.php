<?php

namespace Tests\Feature\Reportes;

use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportePdfExportTest extends TestCase
{
    use RefreshDatabase;

    // Feature: exportación PDF de reportes, Property {1}
    private function usuarioConPermiso(): User
    {
        Permission::firstOrCreate(['name' => 'acceso-reportes', 'guard_name' => 'web']);

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('acceso-reportes');

        return $usuario;
    }

    /**
     * @return array<string, array<string, string>|null>
     */
    private function rutasPdf(): array
    {
        return [
            'reportes.ingresos' => null,
            'reportes.ventas' => null,
            'reportes.lavados' => null,
            'reportes.cambioAceite' => null,
            'reportes.inventario' => null,
            'reportes.clientes' => null,
            'reportes.caja' => null,
            'reportes.personal' => ['mes' => now()->format('Y-m')],
            'reportes.kardex' => null,
        ];
    }

    // Feature: exportación PDF de reportes, Property {2}
    public function test_exportar_pdf_por_cada_modulo_de_reporte(): void
    {
        foreach ($this->rutasPdf() as $ruta => $parametros) {
            $this->actingAs($this->usuarioConPermiso());

            $respuesta = $this->get(route($ruta, array_merge($parametros ?? [], ['export' => 'pdf'])));
            $contenido = $respuesta->getContent();

            $respuesta->assertOk();
            $respuesta->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $contenido);
            $this->assertGreaterThan(1000, strlen($contenido));
        }
    }

    // Feature: exportación PDF de reportes, Property {3}
    public function test_export_pdf_con_fecha_invalida_responde_422_json(): void
    {
        $this->actingAs($this->usuarioConPermiso());

        $this->get(route('reportes.ingresos', ['export' => 'pdf', 'desde' => '2026-13-99']))
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['errors']);
    }

    // Feature: CSV de reportes, Property {4}: el formato actual se mantiene y respeta el rango.
    public function test_export_csv_respeta_el_rango_sin_cambiar_formato(): void
    {
        $usuario = $this->usuarioConPermiso();
        $producto = Producto::factory()->create(['stock' => 10]);

        $enRango = now()->subDays(2);
        $fueraDeRango = now()->subMonths(2);

        MovimientoKardex::create([
            'producto_id' => $producto->id,
            'tipo' => 'entrada',
            'fuente' => 'inventario',
            'origen_id' => 0,
            'cantidad' => 2,
            'stock_antes' => 10,
            'stock_despues' => 12,
            'usuario_id' => $usuario->id,
            'fecha_movimiento' => $enRango,
        ]);

        MovimientoKardex::create([
            'producto_id' => $producto->id,
            'tipo' => 'salida',
            'fuente' => 'venta',
            'origen_id' => 0,
            'cantidad' => 1,
            'stock_antes' => 12,
            'stock_despues' => 11,
            'usuario_id' => $usuario->id,
            'fecha_movimiento' => $fueraDeRango,
        ]);

        $this->actingAs($usuario);

        $respuesta = $this->get(route('reportes.kardex', [
            'export' => 'csv',
            'desde' => now()->subDays(4)->toDateString(),
            'hasta' => now()->subDay()->toDateString(),
        ]));

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');

        $cuerpo = $respuesta->streamedContent();

        $this->assertStringContainsString($enRango->format('d/m/Y'), $cuerpo);
        $this->assertStringNotContainsString($fueraDeRango->format('d/m/Y'), $cuerpo);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $cuerpo);
    }
}
