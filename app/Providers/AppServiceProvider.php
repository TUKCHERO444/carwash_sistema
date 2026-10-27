<?php

namespace App\Providers;

use App\Models\Categoria;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Services\CajaService;
use App\Services\CarritoService;
use App\Services\ContenidoWebService;
use App\Support\ProductionSecurityGuard;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ContenidoWebService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProductionSecurityGuard::assertSafeConfiguration();

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        Gate::before(function ($user, $ability) {
            return $user->hasRole('Administrador') ? true : null;
        });

        View::composer('publica.partials.header', function ($view) {
            $view->with('categoriasMenu', $this->categoriasPublicas());
            $view->with('carritoConteo', app(CarritoService::class)->conteoParaHeader(auth()->user()));
        });

        View::composer('layouts.app', function ($view) {
            $usuario = auth()->user();
            $cajaAbierta = $usuario?->can('acceso-caja')
                && app(CajaService::class)->getCajaActiva() !== null;

            $view->with('cajaAbiertaGlobal', $cajaAbierta);
        });
    }

    /**
     * Categorías activas para el dropdown "Productos" de la web pública.
     * Solo se publican las categorías del panel que tienen al menos un
     * producto activo y con stock de calidad para la tienda.
     */
    private function categoriasPublicas()
    {
        return Categoria::whereHas('productos', fn ($query) => $query->where('activo', true))
            ->withCount('productos as productos_count')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'slug', 'descripcion']);
    }
}
