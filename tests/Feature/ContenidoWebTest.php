<?php

namespace Tests\Feature;

use App\Models\ContenidoWeb;
use App\Models\Marca;
use App\Models\User;
use App\Services\ContenidoWebService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContenidoWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $sinPermiso;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'acceso-contenido-web', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
        $this->sinPermiso = User::factory()->create();
    }

    /**
     * GET /contenido-web responde 200 con la vista del panel (usuario con permiso).
     */
    public function test_panel_get_returns_200_with_permission(): void
    {
        $this->actingAs($this->admin)
            ->get(route('contenido-web.edit'))
            ->assertOk()
            ->assertSee('Contenido de la web')
            ->assertSee('Página de inicio')
            ->assertSee('Página de productos')
            ->assertSee('Página de marcas');
    }

    /**
     * GET /contenido-web sin autenticación redirige al login.
     */
    public function test_panel_get_guest_redirects_to_login(): void
    {
        $this->get(route('contenido-web.edit'))->assertRedirect(route('login'));
    }

    /**
     * GET y PUT /contenido-web sin permiso redirigen al dashboard sin tocar la BD.
     */
    public function test_panel_requires_permission_and_does_not_modify_db(): void
    {
        $this->actingAs($this->sinPermiso)
            ->get(route('contenido-web.edit'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->sinPermiso)
            ->put(route('contenido-web.update'), ['productos_titulo' => 'Cambio no permitido'])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('contenido_web', 0);
        $this->assertDatabaseCount('registros_auditoria', 0);
    }

    /**
     * PUT persiste toggles (como '1'/'0') y textos, y afecta las páginas públicas.
     */
    public function test_update_persists_and_impacts_public_pages(): void
    {
        $this->actingAs($this->admin)
            ->put(route('contenido-web.update'), [
                'inicio_mostrar_marcas' => '1',
                'inicio_mostrar_servicios' => '0',
                'inicio_mostrar_productos' => '0',
                'inicio_mostrar_proyecto' => '1',
                'productos_mostrar_mosaico' => '1',
                'productos_titulo' => 'Nuestros productos semana',
                'productos_intro' => 'Encuentra lo ideal para tu auto.',
                'servicios_titulo' => '¿Qué necesitas hoy?',
                'servicios_intro' => 'Especialistas a tu servicio.',
                'marcas_titulo' => 'Marcas aliadas',
                'marcas_intro' => 'Primeras marcas del mercado.',
            ])
            ->assertRedirect(route('contenido-web.edit'));

        $this->assertDatabaseHas('contenido_web', [
            'clave' => 'inicio_mostrar_productos',
            'valor' => '0',
            'tipo' => 'bool',
        ]);
        $this->assertDatabaseHas('contenido_web', [
            'clave' => 'productos_titulo',
            'valor' => 'Nuestros productos semana',
            'tipo' => 'string',
        ]);

        app()->forgetInstance(ContenidoWebService::class);

        $this->get('/')
            ->assertSee('Marcas aliadas')
            ->assertDontSee('Te recomendamos');

        $this->get('/nuestros-servicios')
            ->assertSee('Especialistas a tu servicio.')
            ->assertSee('¿Qué necesitas hoy?');

        $this->get('/nuestros-productos')
            ->assertSee('Nuestros productos semana')
            ->assertSee('Encuentra lo ideal para tu auto.');

        $this->get('/nuestras-marcas')
            ->assertSee('Marcas aliadas')
            ->assertSee('Primeras marcas del mercado.');
    }

    /**
     * El PUT es un upsert idempotente: nunca duplica filas por clave.
     */
    public function test_update_upserts_without_duplicates(): void
    {
        $data = [
            'productos_titulo' => 'Título de prueba',
            'productos_intro' => 'Introducción de prueba.',
            'servicios_titulo' => 'Servicios',
            'servicios_intro' => 'Servicios con especialistas.',
            'marcas_titulo' => 'Marcas',
            'marcas_intro' => 'Todas las marcas.',
        ];

        foreach (ContenidoWebService::DEFAULTS as $clave => $meta) {
            if ($meta['tipo'] === 'bool') {
                $data[$clave] = '1';
            }
        }

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($this->admin)
                ->put(route('contenido-web.update'), $data)
                ->assertRedirect(route('contenido-web.edit'));
        }

        $this->assertSame(
            count(ContenidoWebService::DEFAULTS),
            ContenidoWeb::count(),
            'Debe existir exactamente una fila por clave'
        );

        foreach (ContenidoWebService::DEFAULTS as $clave => $meta) {
            $this->assertSame(1, ContenidoWeb::where('clave', $clave)->count(), "Clave duplicada: {$clave}");
        }
    }

    /**
     * El PUT registra la acción de auditoría sobre el módulo contenido_web.
     */
    public function test_update_records_audit_action(): void
    {
        $this->actingAs($this->admin)
            ->put(route('contenido-web.update'), ['productos_titulo' => 'Nuevo título']);

        $this->assertDatabaseHas('registros_auditoria', [
            'modulo' => 'contenido_web',
            'accion' => 'actualizar contenidos web',
            'usuario_id' => $this->admin->id,
        ]);
    }

    /**
     * Textos mayores a 120 caracteres se rechazan sin persistir.
     */
    public function test_update_rejects_long_texts(): void
    {
        $this->actingAs($this->admin)
            ->put(route('contenido-web.update'), ['productos_titulo' => str_repeat('a', 121)])
            ->assertSessionHasErrors('productos_titulo');

        $this->assertDatabaseCount('contenido_web', 0);
    }

    /**
     * Órdenes de curaduría negativas se rechazan sin persistir.
     */
    public function test_update_rejects_negative_orden(): void
    {
        $marca = Marca::create(['nombre' => 'Alpha']);

        $this->actingAs($this->admin)
            ->put(route('contenido-web.update'), [
                'marcas_web' => [$marca->id],
                'marcas_orden' => [$marca->id => -1],
            ])
            ->assertSessionHasErrors("marcas_orden.{$marca->id}");

        $this->assertDatabaseCount('contenido_web', 0);
    }

    /**
     * La curaduría se persiste como JSON y filtra las marcas de /nuestras-marcas.
     */
    public function test_curaduria_stored_as_json_and_filters_public_page(): void
    {
        Marca::create(['nombre' => 'Alpha']);
        Marca::create(['nombre' => 'Beta']);
        $gamma = Marca::create(['nombre' => 'Gamma']);

        $this->actingAs($this->admin)
            ->put(route('contenido-web.update'), [
                'marcas_web' => [$gamma->id],
                'marcas_orden' => [$gamma->id => 10],
            ]);

        $this->assertDatabaseHas('contenido_web', [
            'clave' => 'marcas_web',
            'valor' => json_encode([['marca_id' => $gamma->id, 'orden' => 10]]),
            'tipo' => 'json',
        ]);

        app()->forgetInstance(ContenidoWebService::class);

        $this->get('/nuestras-marcas')
            ->assertSee('Gamma')
            ->assertDontSee('Alpha')
            ->assertDontSee('Beta');
    }
}
