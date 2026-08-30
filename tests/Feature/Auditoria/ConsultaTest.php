<?php

namespace Tests\Feature\Auditoria;

use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConsultaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'acceso-auditoria', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('acceso-auditoria');
        $this->actingAs($this->user);
    }

    public function test_index_muestra_listado(): void
    {
        $producto = Producto::create([
            'nombre' => 'Aceite 10W40',
            'precio_compra' => 20.00,
            'precio_venta' => 35.00,
            'stock' => 5,
            'inventario' => 5,
            'activo' => true,
        ]);

        $this->get(route('auditoria.acciones.index'))
            ->assertOk()
            ->assertViewHas('registros')
            ->assertSee('Productos')
            ->assertSee('crear');
    }

    public function test_index_filtra_por_modulo(): void
    {
        RegistroAuditoria::create([
            'modulo' => 'productos',
            'accion' => 'crear',
            'auditable_type' => Producto::class,
            'auditable_id' => 1,
            'usuario_id' => $this->user->id,
            'fecha_movimiento' => now(),
        ]);

        RegistroAuditoria::create([
            'modulo' => 'ventas',
            'accion' => 'crear',
            'auditable_type' => Producto::class,
            'auditable_id' => 2,
            'usuario_id' => $this->user->id,
            'fecha_movimiento' => now(),
        ]);

        $this->get(route('auditoria.acciones.index', ['modulo' => 'productos']))
            ->assertOk()
            ->assertViewHas('registros', fn ($registros) => $registros->count() === 1 && $registros->first()->modulo === 'productos');
    }

    public function test_index_filtra_por_accion(): void
    {
        RegistroAuditoria::create([
            'modulo' => 'productos',
            'accion' => 'crear',
            'auditable_type' => Producto::class,
            'auditable_id' => 1,
            'usuario_id' => $this->user->id,
            'fecha_movimiento' => now(),
        ]);

        RegistroAuditoria::create([
            'modulo' => 'productos',
            'accion' => 'eliminar',
            'auditable_type' => Producto::class,
            'auditable_id' => 1,
            'usuario_id' => $this->user->id,
            'fecha_movimiento' => now(),
        ]);

        $this->get(route('auditoria.acciones.index', ['accion' => 'eliminar']))
            ->assertOk()
            ->assertViewHas('registros', fn ($registros) => $registros->count() === 1 && $registros->first()->accion === 'eliminar');
    }

    public function test_index_filtra_por_usuario(): void
    {
        $otroUser = User::factory()->create();

        RegistroAuditoria::create([
            'modulo' => 'productos',
            'accion' => 'crear',
            'auditable_type' => Producto::class,
            'auditable_id' => 1,
            'usuario_id' => $this->user->id,
            'fecha_movimiento' => now(),
        ]);

        RegistroAuditoria::create([
            'modulo' => 'productos',
            'accion' => 'crear',
            'auditable_type' => Producto::class,
            'auditable_id' => 2,
            'usuario_id' => $otroUser->id,
            'fecha_movimiento' => now(),
        ]);

        // Filtra por usuario: ningún registro devuelto debe pertenecer al otro usuario.
        $this->get(route('auditoria.acciones.index', ['usuario_id' => $this->user->id]))
            ->assertOk()
            ->assertViewHas('registros', function ($registros) use ($otroUser) {
                return $registros->isNotEmpty()
                    && $registros->every(fn ($r) => $r->usuario_id === $this->user->id)
                    && ! $registros->contains(fn ($r) => $r->usuario_id === $otroUser->id);
            });
    }

    public function test_show_muestra_detalle_antes_despues(): void
    {
        $registro = RegistroAuditoria::create([
            'modulo' => 'productos',
            'accion' => 'actualizar',
            'auditable_type' => Producto::class,
            'auditable_id' => 1,
            'datos_antes' => ['stock' => 10],
            'datos_despues' => ['stock' => 7],
            'usuario_id' => $this->user->id,
            'fecha_movimiento' => now(),
        ]);

        $this->get(route('auditoria.acciones.show', $registro))
            ->assertOk()
            ->assertViewHas('registroAuditoria')
            ->assertSee('10')
            ->assertSee('7');
    }
}
