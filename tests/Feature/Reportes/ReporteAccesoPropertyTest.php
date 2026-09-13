<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReporteAccesoPropertyTest extends TestCase
{
    use RefreshDatabase;

    private const ITERACIONES = 100;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'acceso-reportes', 'guard_name' => 'web']);
    }

    /**
     * Feature: reportes, Property 6: Todas las rutas del módulo requieren
     * autenticación.
     *
     * For any route of the module (with valid query params), a request without
     * a session redirects to the login page.
     *
     * **Valida: Requisito 2.5**
     */
    public function test_property_6_all_routes_require_authentication(): void
    {
        $rutas = $this->rutasDelModulo();

        for ($i = 0; $i < self::ITERACIONES; $i++) {
            $rutas[$i % count($rutas)]()->assertRedirect(route('login'));
        }
    }

    /**
     * Feature: reportes, Property 7: Todas las rutas requieren el permiso
     * `acceso-reportes`, incluyendo al rol Vendedor.
     *
     * For any route of the module and any authenticated user without the
     * permission (including one holding only the Vendedor role), the request
     * redirects to the dashboard (convención del proyecto: el handler de
     * UnauthorizedException en bootstrap/app.php redirige las peticiones no JSON).
     *
     * **Valida: Requisitos 2.3, 2.6**
     */
    public function test_property_7_all_routes_require_acceso_reportes(): void
    {
        $sinPermiso = User::factory()->create();

        Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']);
        $vendedor = User::factory()->create();
        $vendedor->assignRole('Vendedor');

        $rutas = $this->rutasDelModulo($sinPermiso);
        $rutasVendedor = $this->rutasDelModulo($vendedor);

        for ($i = 0; $i < self::ITERACIONES; $i++) {
            $rutas[$i % count($rutas)]()->assertRedirect(route('dashboard'));
            $rutasVendedor[$i % count($rutasVendedor)]()->assertRedirect(route('dashboard'));
        }
    }

    /**
     * @return \Closure[]
     */
    private function rutasDelModulo(?User $usuario = null): array
    {
        $hoy = now()->toDateString();
        $mes = now()->format('Y-m');

        $construir = function (string $ruta, array $params = []) use ($usuario): \Closure {
            return fn () => $usuario
                ? $this->actingAs($usuario)->get(route($ruta, $params))
                : $this->get(route($ruta, $params));
        };

        return [
            $construir('reportes.index'),
            $construir('reportes.ingresos', ['desde' => $hoy, 'hasta' => $hoy]),
            $construir('reportes.ventas', ['desde' => $hoy, 'hasta' => $hoy]),
            $construir('reportes.lavados', ['desde' => $hoy, 'hasta' => $hoy]),
            $construir('reportes.cambioAceite', ['desde' => $hoy, 'hasta' => $hoy]),
            $construir('reportes.inventario', ['desde' => $hoy, 'hasta' => $hoy]),
            $construir('reportes.clientes', ['desde' => $hoy, 'hasta' => $hoy]),
            $construir('reportes.caja', ['desde' => $hoy, 'hasta' => $hoy]),
            $construir('reportes.personal', ['mes' => $mes]),
            $construir('reportes.kardex', ['desde' => $hoy, 'hasta' => $hoy]),
        ];
    }
}
