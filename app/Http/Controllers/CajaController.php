<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Services\CajaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CajaController extends Controller
{
    public function __construct(
        private readonly CajaService $cajaService
    ) {}

    /**
     * Muestra el panel de caja con la caja activa y su resumen.
     */
    public function index(): View
    {
        $caja = $this->cajaService->getCajaActiva();

        if ($caja) {
            $caja->load([
                'ventas',
                'cambioAceites',
                'ingresos' => fn ($q) => $q->where('estado', 'confirmado'),
                'egresos',
            ]);
        }

        $resumen = $caja ? $this->cajaService->calcularResumen($caja) : null;

        return view('caja.panel', compact('caja', 'resumen'));
    }

    /**
     * Abre una nueva caja con el monto inicial indicado.
     */
    public function abrir(Request $request): RedirectResponse
    {
        $request->validate([
            'monto_inicial' => ['required', 'numeric', 'gt:0'],
        ]);

        // Verificar si ya existe una caja abierta antes de intentar abrir
        if ($this->cajaService->getCajaActiva()) {
            return back()->with('error', 'Ya existe una caja abierta.');
        }

        try {
            $this->cajaService->abrirCaja(
                (float) $request->monto_inicial,
                auth()->id()
            );

            return redirect()->route('caja.index')
                ->with('success', 'Caja abierta correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cierra la caja activa.
     */
    public function cerrar(Request $request): RedirectResponse
    {
        $caja = $this->cajaService->getCajaActiva();

        if (! $caja) {
            return redirect()->route('caja.index')
                ->with('error', 'No hay caja activa.');
        }

        try {
            $this->cajaService->cerrarCaja($caja);

            return redirect()->route('caja.index')
                ->with('success', 'Caja cerrada correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Registra un egreso manual en la caja activa.
     */
    public function registrarEgreso(Request $request): RedirectResponse
    {
        $request->validate([
            'monto'       => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['required', 'string', 'max:500'],
            'tipo_pago'   => ['required', 'in:efectivo,yape'],
        ]);

        $caja = $this->cajaService->getCajaActiva();

        if (! $caja) {
            return redirect()->route('caja.index')
                ->with('error', 'No hay caja activa.');
        }

        try {
            $this->cajaService->registrarEgreso($caja, [
                'monto'       => $request->monto,
                'descripcion' => $request->descripcion,
                'tipo_pago'   => $request->tipo_pago,
                'user_id'     => auth()->id(),
            ]);

            return redirect()->route('caja.index')
                ->with('success', 'Egreso registrado correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Muestra el historial de cajas cerradas (solo Administrador).
     */
    public function historial(): View
    {
        $cajas = Caja::cerrada()
            ->with('user')
            ->orderByDesc('fecha_cierre')
            ->paginate(10);

        return view('caja.historial', compact('cajas'));
    }

    /**
     * Muestra el detalle de una caja cerrada (solo Administrador).
     * Usa Route Model Binding para recibir la instancia de Caja.
     */
    public function detalle(Caja $caja): View
    {
        $caja->load('user', 'egresos.user');
        $resumen = $this->cajaService->calcularResumen($caja);

        return view('caja.detalle', compact('caja', 'resumen'));
    }
}
