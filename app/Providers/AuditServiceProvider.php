<?php

namespace App\Providers;

use App\Models\Asistencia;
use App\Models\Automotor;
use App\Models\Caja;
use App\Models\CambioAceite;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ContenidoWeb;
use App\Models\EgresoCaja;
use App\Models\Lavado;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Venta;
use App\Observers\AuditModelObserver;
use App\Services\AuditService;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AuditServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AuditService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $modelos = [
            User::class,
            Role::class,
            Trabajador::class,
            Asistencia::class,
            Producto::class,
            Categoria::class,
            Marca::class,
            Servicio::class,
            Vehiculo::class,
            Cliente::class,
            Automotor::class,
            Venta::class,
            CambioAceite::class,
            Lavado::class,
            Caja::class,
            EgresoCaja::class,
            ContenidoWeb::class,
        ];

        foreach ($modelos as $modelo) {
            $modelo::observe(AuditModelObserver::class);
        }
    }
}
