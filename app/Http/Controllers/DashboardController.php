<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    /**
     * Muestra el dashboard de estadísticas e ingresos del local.
     *
     * La zona pública (/cuenta/*) nunca entra aquí: una cuenta que solo
     * tiene el rol Cliente es devuelta a su panel (las rutas con
     * permission:* sí responden 403, ahí no hay a dónde redirigir).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('Cliente') && $user->roles->pluck('name')->diff(['Cliente'])->isEmpty()) {
            return redirect()->route('cuenta.panel')
                ->with('info', 'Tu sesión es de cliente. Para entrar al panel operativo, cierra sesión e ingresa con una cuenta del local.');
        }

        $periodo = (int) $request->query('periodo', 30);

        // 7 | 30 | este mes
        if ($periodo === 7) {
            $dias = 7;
            $desde = CarbonImmutable::today()->subDays(6)->startOfDay();
            $hasta = CarbonImmutable::today()->endOfDay();
        } elseif ($periodo === 'mes') {
            $dias = CarbonImmutable::today()->day;
            $desde = CarbonImmutable::today()->startOfMonth();
            $hasta = CarbonImmutable::today()->endOfDay();
        } else {
            $periodo = 30;
            $dias = 30;
            $desde = CarbonImmutable::today()->subDays(29)->startOfDay();
            $hasta = CarbonImmutable::today()->endOfDay();
        }

        $resumen = $this->dashboardService->resumenGeneral();
        $serie = $this->dashboardService->ingresosPorDia($dias);
        $metodoPago = $this->dashboardService->metodoPago($desde, $hasta);
        $topProductos = $this->dashboardService->topProductos(5);
        $stockBajo = $this->dashboardService->productosStockBajo(5);

        return view('dashboard', compact(
            'resumen',
            'serie',
            'metodoPago',
            'topProductos',
            'stockBajo',
            'periodo'
        ));
    }
}
