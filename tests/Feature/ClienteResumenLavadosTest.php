<?php

namespace Tests\Feature;

use App\Models\Automotor;
use App\Models\Cliente;
use App\Models\Lavado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClienteResumenLavadosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-clientes', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-clientes');
    }

    private function cliente(string $nombre = 'Juan Perez'): Cliente
    {
        return Cliente::create(['nombre' => $nombre, 'dni' => null, 'telefono' => '987654321']);
    }

    private function lavadoConfirmado(Cliente $cliente, string $fecha, ?string $placa = null): Lavado
    {
        return Lavado::factory()->confirmado()->create([
            'cliente_id' => $cliente->id,
            'automotor_id' => $placa,
            'fecha' => $fecha,
        ]);
    }

    public function test_resumen_cuenta_solo_confirmados_del_cliente(): void
    {
        $cliente = $this->cliente();
        $auto = Automotor::create(['placa' => 'ABC-123', 'cliente_id' => $cliente->id]);

        $this->lavadoConfirmado($cliente, now()->subDays(10)->toDateString(), $auto->placa);
        $this->lavadoConfirmado($cliente, now()->subDays(2)->toDateString());
        $this->lavadoConfirmado($cliente, now()->subDays(1)->toDateString(), $auto->placa);

        // Ruido: pendiente del mismo cliente y confirmado de otro cliente.
        Lavado::factory()->pendiente()->create(['cliente_id' => $cliente->id]);
        $otro = $this->cliente('Otro Cliente');
        $this->lavadoConfirmado($otro, now()->toDateString());

        $respuesta = $this->actingAs($this->user)
            ->get(route('clientes.resumenLavados', $cliente))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('resumen.total_lavados', 3);

        $datos = $respuesta->json();
        $this->assertSame('ABC-123', $datos['resumen']['ultimo_lavado']['placa']);
        $this->assertSame(now()->subDays(1)->format('d/m/Y'), $datos['resumen']['ultimo_lavado']['fecha']);
        $this->assertSame(
            [['placa' => 'ABC-123', 'lavados' => 2]],
            $datos['resumen']['vehiculos']
        );
    }

    public function test_resumen_sin_lavados_devuelve_ceros_y_nulo(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($this->user)
            ->get(route('clientes.resumenLavados', $cliente))
            ->assertOk()
            ->assertJsonPath('resumen.total_lavados', 0)
            ->assertJsonPath('resumen.ultimo_lavado', null)
            ->assertJsonPath('resumen.vehiculos', []);
    }

    public function test_index_muestra_boton_lavados_y_modal(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($this->user)
            ->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('data-cliente-lavados="'.$cliente->id.'"', false)
            ->assertSee('modal-lavados-cliente');
    }

    public function test_acceso_requiere_sesion_y_permiso(): void
    {
        $cliente = $this->cliente();

        $this->get(route('clientes.resumenLavados', $cliente))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->get(route('clientes.resumenLavados', $cliente))->assertRedirect(route('dashboard'));
    }
}
