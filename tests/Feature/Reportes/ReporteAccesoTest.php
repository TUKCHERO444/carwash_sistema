<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReporteAccesoTest extends TestCase
{
    use RefreshDatabase;

    private function rutas(): array
    {
        return [
            'reportes.index',
            'reportes.ingresos',
            'reportes.ventas',
            'reportes.lavados',
            'reportes.cambioAceite',
            'reportes.inventario',
            'reportes.clientes',
            'reportes.caja',
            'reportes.personal',
            'reportes.kardex',
        ];
    }

    public function test_visitante_sin_autenticar_es_redirigido_a_login(): void
    {
        foreach ($this->rutas() as $ruta) {
            $this->get(route($ruta))->assertRedirect(route('login'));
        }
    }

    public function test_usuario_sin_permiso_es_redirigido_al_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ($this->rutas() as $ruta) {
            $this->get(route($ruta))->assertRedirect(route('dashboard'));
        }
    }

    public function test_usuario_con_permiso_accede_al_index_e_ingresos(): void
    {
        Permission::firstOrCreate(['name' => 'acceso-reportes', 'guard_name' => 'web']);

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('acceso-reportes');

        $this->actingAs($usuario)->get(route('reportes.index'))->assertOk();
        $this->actingAs($usuario)->get(route('reportes.ingresos'))->assertOk();
    }

    public function test_resumen_vive_en_auditoria_y_no_en_gestion_administrativa(): void
    {
        Permission::firstOrCreate(['name' => 'acceso-reportes', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'acceso-auditoria', 'guard_name' => 'web']);

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('acceso-reportes');

        $html = $this->actingAs($usuario)->get(route('reportes.index'))->getContent();

        // La sección Auditoría existe y contiene el resumen (desktop + móvil).
        $this->assertSame(2, substr_count($html, 'Resumen de reportes'));
        $this->assertStringContainsString('Auditoría', $html);

        // Gestión Administrativa no lo muestra (ni siquiera existe sin sus permisos).
        $this->assertStringNotContainsString('Gestión Administrativa', $html);

        // Sin acceso-auditoria no ve los enlaces del sidebar (la página
        // de reportes sí puede mencionar "Kardex" por su propio reporte).
        $this->assertStringNotContainsString('/auditoria/kardex', $html);
        $this->assertStringNotContainsString('/auditoria/acciones', $html);
    }

    public function test_sin_permiso_no_hay_seccion_auditoria_ni_resumen(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertDontSee('Resumen de reportes')
            ->assertDontSee('Auditoría');
    }
}
