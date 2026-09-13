<?php

namespace App\Providers;

use App\Models\Categoria;
use App\Services\ContenidoWebService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        if (env('APP_ENV') === 'production') {
            URL::forceScheme('https');
        }

        Gate::before(function ($user, $ability) {
            return $user->hasRole('Administrador') ? true : null;
        });

        View::composer('publica.partials.header', function ($view) {
            $view->with('categoriasMenu', $this->categoriasPublicas());
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
