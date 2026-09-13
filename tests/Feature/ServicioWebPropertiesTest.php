<?php

namespace Tests\Feature;

use App\Models\ContenidoWeb;
use App\Models\Servicio;
use App\Models\User;
use App\Services\ContenidoWebService;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Property-based tests del módulo servicios-web.
 *
 * Feature: servicios-web
 */
class ServicioWebPropertiesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'acceso-servicios', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    /**
     * Feature: servicios-web, Property 1: la página pública muestra exactamente
     * los servicios activos, ordenados por orden/nombre, con su precio.
     */
    public function test_property_1_public_page_contains_only_active_ordered_services(): void
    {
        $faker = FakerFactory::create();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $count = $faker->numberBetween(1, 8);
            $servicios = collect(range(0, $count - 1))->map(function ($j) use ($faker, $i) {
                return Servicio::factory()->create([
                    'nombre' => "Servicio {$i}-{$j}",
                    'precio' => $faker->randomFloat(2, 5, 500),
                    'orden' => $faker->numberBetween(0, 10),
                    'activo' => $faker->boolean(),
                ]);
            });

            $activos = $servicios->filter(fn ($s) => $s->activo)
                ->sortBy(['orden', 'nombre'])
                ->values();
            $inactivos = $servicios->filter(fn ($s) => ! $s->activo);

            $response = $this->get('/nuestros-servicios');
            $response->assertStatus(200);

            foreach ($inactivos as $inactivo) {
                $response->assertDontSee($inactivo->nombre);
            }

            $response->assertSeeInOrder(
                $activos->pluck('nombre')->map(fn ($nombre) => mb_strtoupper($nombre))->all()
            );

            foreach ($activos as $activo) {
                $response->assertSee('S/ '.number_format($activo->precio, 2), false);
            }

            $servicios->each->delete();
        }
    }

    /**
     * Feature: servicios-web, Property 2: el toggle complementa e involuciona.
     */
    public function test_property_2_toggle_complements_and_round_trips(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $servicio = Servicio::factory()->create(['activo' => $i % 2 === 0]);
            $original = $servicio->activo;

            $this->actingAs($this->admin)
                ->patchJson(route('servicios.toggleStatus', $servicio))
                ->assertOk()
                ->assertJson(['activo' => ! $original]);

            $servicio->refresh();

            $this->actingAs($this->admin)
                ->patchJson(route('servicios.toggleStatus', $servicio))
                ->assertOk();

            $servicio->refresh();

            $this->assertSame(
                $original,
                $servicio->activo,
                "Property 2 failed at iteration {$i}: round-trip no devolvió el estado original"
            );

            $servicio->delete();
        }
    }

    /**
     * Feature: servicios-web, Property 4: icono/orden inválidos se rechazan sin persistir.
     */
    public function test_property_4_invalid_fields_rejected_without_persist(): void
    {
        $faker = FakerFactory::create();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $iconoInvalido = $faker->word().'-'.$i;
            $ordenInvalido = $faker->numberBetween(-100, -1);

            $this->actingAs($this->admin)
                ->post(route('servicios.store'), [
                    'nombre' => 'Lavado valido',
                    'precio' => 30.00,
                    'icono' => $iconoInvalido,
                    'orden' => $ordenInvalido,
                ])
                ->assertSessionHasErrors(['icono', 'orden']);

            $this->assertDatabaseCount('servicios', 0);
        }
    }

    /**
     * Feature: servicios-web, Property 5: inicio respeta el toggle de sección y los 3 primeros activos.
     */
    public function test_property_5_inicio_respects_section_toggle_and_first_three(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $mostrar = $i % 2 === 0;

            for ($orden = 0; $orden < 5; $orden++) {
                Servicio::factory()->create([
                    'nombre' => "Servicio {$i}-{$orden}",
                    'orden' => $orden,
                    'activo' => true,
                ]);
            }

            if (! $mostrar) {
                ContenidoWeb::create([
                    'clave' => 'inicio_mostrar_servicios',
                    'valor' => '0',
                    'tipo' => 'bool',
                ]);
            }

            app()->forgetInstance(ContenidoWebService::class);

            $response = $this->get('/');

            if ($mostrar) {
                $response->assertSee('Cuidado completo para tu vehículo');
                $response->assertSeeInOrder(["Servicio {$i}-0", "Servicio {$i}-1", "Servicio {$i}-2"]);
                $response->assertDontSee("Servicio {$i}-3");
                $response->assertDontSee("Servicio {$i}-4");
            } else {
                $response->assertDontSee('Cuidado completo para tu vehículo');
            }

            Servicio::query()->delete();
            ContenidoWeb::query()->delete();
        }
    }
}
