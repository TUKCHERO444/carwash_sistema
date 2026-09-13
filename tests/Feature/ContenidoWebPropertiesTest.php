<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\ContenidoWeb;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\User;
use App\Services\ContenidoWebService;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Property-based tests del módulo contenido-web.
 *
 * Feature: contenido-web
 */
class ContenidoWebPropertiesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'acceso-contenido-web', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    /**
     * Feature: contenido-web, Property 1: claves ausentes → defaults exactos.
     */
    public function test_property_1_defaults_without_bd(): void
    {
        $faker = FakerFactory::create();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            app()->forgetInstance(ContenidoWebService::class);
            $service = app(ContenidoWebService::class);

            $clave = array_rand(ContenidoWebService::DEFAULTS);
            $meta = ContenidoWebService::DEFAULTS[$clave];

            if ($meta['tipo'] === 'bool') {
                $this->assertSame(
                    (bool) $meta['default'],
                    $service->bool($clave),
                    "Property 1 falló en iteración {$i} para la clave {$clave}"
                );
            } elseif ($meta['tipo'] === 'string') {
                $this->assertSame(
                    (string) $meta['default'],
                    $service->text($clave),
                    "Property 1 falló en iteración {$i} para la clave {$clave}"
                );
            } else {
                $this->assertSame(
                    $meta['default'],
                    $service->json($clave),
                    "Property 1 falló en iteración {$i} para la clave {$clave}"
                );
            }
        }
    }

    /**
     * Feature: contenido-web, Property 2: sets de claves aleatorios → 1 fila por
     * clave y persistencia idempotente (bool como '1'/'0', curaduría como JSON).
     */
    public function test_property_2_upsert_idempotent(): void
    {
        $faker = FakerFactory::create();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $data = [];

            foreach (ContenidoWebService::DEFAULTS as $clave => $meta) {
                if ($meta['tipo'] === 'bool') {
                    $data[$clave] = $faker->boolean() ? '1' : '0';
                } elseif ($meta['tipo'] === 'string') {
                    $data[$clave] = $faker->words(4, true);
                }
            }

            $marcas = collect(range(0, $faker->numberBetween(0, 5)))->map(function ($j) use ($i) {
                return Marca::create(['nombre' => "Marca {$i}-{$j}"]);
            });

            $marcasWeb = [];
            $marcasOrden = [];
            $posicion = 0;

            foreach ($marcas as $marca) {
                if ($faker->boolean()) {
                    $marcasWeb[] = $marca->id;
                    $marcasOrden[$marca->id] = $posicion++;
                }
            }

            $data['marcas_web'] = $marcasWeb;
            $data['marcas_orden'] = $marcasOrden;

            $this->actingAs($this->admin)
                ->put(route('contenido-web.update'), $data)
                ->assertRedirect(route('contenido-web.edit'));

            $this->actingAs($this->admin)
                ->put(route('contenido-web.update'), $data)
                ->assertRedirect(route('contenido-web.edit'));

            $this->assertSame(
                count(ContenidoWebService::DEFAULTS),
                ContenidoWeb::count(),
                "Property 2 falló en iteración {$i}: número de filas distinto al esperado"
            );

            foreach (ContenidoWebService::DEFAULTS as $clave => $meta) {
                $this->assertSame(
                    1,
                    ContenidoWeb::where('clave', $clave)->count(),
                    "Property 2 falló en iteración {$i}: clave {$clave} duplicada"
                );

                if ($meta['tipo'] === 'bool') {
                    $this->assertDatabaseHas('contenido_web', [
                        'clave' => $clave,
                        'valor' => $data[$clave],
                        'tipo' => 'bool',
                    ]);
                }
            }

            if (empty($marcasWeb)) {
                $this->assertDatabaseHas('contenido_web', ['clave' => 'marcas_web', 'valor' => '[]']);
            } else {
                $esperado = json_encode(collect($marcasWeb)->map(function ($id) use ($marcasOrden) {
                    return ['marca_id' => $id, 'orden' => $marcasOrden[$id]];
                })->all());
                $this->assertDatabaseHas('contenido_web', ['clave' => 'marcas_web', 'valor' => $esperado]);
            }

            ContenidoWeb::query()->delete();
            Marca::query()->delete();
        }
    }

    /**
     * Feature: contenido-web, Property 4: curadurías aleatorias → solo marcas
     * curadas en orden; curaduría vacía → fallback a todas por nombre.
     */
    public function test_property_4_curaduria_order_filter_fallback(): void
    {
        $faker = FakerFactory::create();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $marcas = collect(range(0, 2))->map(function ($j) use ($i) {
                return Marca::create(['nombre' => "Marca {$i}-{$j}"]);
            });

            $curadas = $marcas->filter(fn () => $faker->boolean())->values();

            $marcasWeb = [];
            $marcasOrden = [];
            $posicion = 0;

            foreach ($curadas as $marca) {
                $marcasWeb[] = $marca->id;
                $marcasOrden[$marca->id] = $posicion++;
            }

            $this->actingAs($this->admin)
                ->put(route('contenido-web.update'), [
                    'marcas_web' => $marcasWeb,
                    'marcas_orden' => $marcasOrden,
                ]);

            app()->forgetInstance(ContenidoWebService::class);
            $service = app(ContenidoWebService::class);

            $obtenidas = $service->marcasWeb()->pluck('id')->all();

            if ($curadas->isEmpty()) {
                $esperadas = $marcas->sortBy('nombre')->pluck('id')->all();
                $this->assertSame(
                    $esperadas,
                    $obtenidas,
                    "Property 4 falló en iteración {$i}: fallback no devolvió todas por nombre"
                );
            } else {
                $this->assertSame(
                    $curadas->pluck('id')->all(),
                    $obtenidas,
                    "Property 4 falló en iteración {$i}: curaduría no respetó orden/filtro"
                );

                foreach ($marcas->whereNotIn('id', $curadas->pluck('id'))->pluck('id') as $id) {
                    $this->assertNotContains(
                        (int) $id,
                        $obtenidas,
                        "Property 4 falló en iteración {$i}: marca no curada incluida"
                    );
                }
            }

            ContenidoWeb::query()->delete();
            Marca::query()->delete();
        }
    }

    /**
     * Feature: contenido-web, Property 5: mosaico solo con categorías que tienen
     * ≥1 producto activo, y cada tile enlaza a su colección por slug.
     */
    public function test_property_5_mosaic_exact_categories_and_links(): void
    {
        $faker = FakerFactory::create();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $categorias = collect(range(0, $faker->numberBetween(1, 6)))->map(function ($j) use ($i) {
                return Categoria::create(['nombre' => "Categoria {$i}-{$j}"]);
            });

            foreach ($categorias as $categoria) {
                $nActivos = $faker->numberBetween(0, 3);
                $nInactivos = $faker->numberBetween(0, 2);

                for ($k = 0; $k < $nActivos; $k++) {
                    Producto::factory()->create(['categoria_id' => $categoria->id, 'activo' => true]);
                }
                for ($k = 0; $k < $nInactivos; $k++) {
                    Producto::factory()->create(['categoria_id' => $categoria->id, 'activo' => false]);
                }
            }

            app()->forgetInstance(ContenidoWebService::class);

            $response = $this->get('/nuestros-productos');
            $response->assertStatus(200);

            $conActivos = $categorias->filter(function (Categoria $categoria) {
                return $categoria->productos()->where('activo', true)->exists();
            });
            $sinActivos = $categorias->filter(function (Categoria $categoria) {
                return ! $categoria->productos()->where('activo', true)->exists();
            });

            foreach ($sinActivos as $categoria) {
                $response->assertDontSee(
                    mb_strtoupper($categoria->nombre),
                    "Property 5 falló en iteración {$i}: categoría sin activos visible"
                );
            }

            foreach ($conActivos as $categoria) {
                $response->assertSee(mb_strtoupper($categoria->nombre));
                $response->assertSee(
                    route('publica.productos.categoria', ['categoria' => $categoria->slug]),
                    false
                );
            }

            Categoria::query()->delete();
            Producto::query()->delete();
        }
    }
}
