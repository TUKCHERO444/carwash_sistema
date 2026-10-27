<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\EgresoCaja;
use App\Models\MetodoPago;
use App\Models\Trabajador;
use App\Services\AuditService;
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
     * Muestra la caja general al Administrador o el panel operativo al resto.
     *
     * El resumen completo solo se calcula y se carga con `ver-resumen-caja`.
     * Los demás roles reciben únicamente estado y totales, sin detalles de
     * ingresos, métodos de pago, balance ni arqueo.
     */
    public function index(): View
    {
        $caja = $this->cajaService->getCajaActiva();
        $categoriasGasto = EgresoCaja::categoriasManuales();
        $puedeRegistrarEgreso = auth()->user()->can('registrar-egresos-caja');
        $trabajadores = ($caja && $puedeRegistrarEgreso)
            ? Trabajador::orderBy('nombre')->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno'])
            : collect();

        if (auth()->user()->can('ver-resumen-caja')) {
            if ($caja) {
                $caja->load([
                    'ventas.pagos.metodoPago',
                    'cambioAceites.pagos.metodoPago',
                    'lavados' => fn ($q) => $q->where('estado', 'confirmado')->with('pagos.metodoPago'),
                    'egresos',
                ]);
            }

            $resumen = $caja ? $this->cajaService->calcularResumen($caja) : null;
            $efectivoEsperado = $caja ? $this->cajaService->calcularEfectivoEsperado($caja) : null;
            $nombresMetodos = MetodoPago::pluck('nombre', 'codigo')->all();

            return view('caja.panel', compact(
                'caja',
                'resumen',
                'efectivoEsperado',
                'categoriasGasto',
                'trabajadores',
                'nombresMetodos',
                'puedeRegistrarEgreso'
            ));
        }

        $resumen = $caja ? $this->cajaService->calcularTotales($caja) : null;

        return view('caja.panel-operativo', compact(
            'caja',
            'resumen',
            'categoriasGasto',
            'trabajadores',
            'puedeRegistrarEgreso'
        ));
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
            return back()->with('error', 'Ya existe una caja abierta.')->withInput();
        }

        try {
            app(AuditService::class)->anotarAccion('abrir caja');

            $this->cajaService->abrirCaja(
                (float) $request->monto_inicial,
                auth()->id()
            );

            return redirect()->route('caja.index')
                ->with('success', 'Caja abierta correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Cierra la caja activa con arqueo previo obligatorio.
     *
     * El esperado lo calcula el sistema; el responsable ingresa el contado y
     * la diferencia es automática. Con diferencia se exige observación.
     */
    public function cerrar(Request $request): RedirectResponse
    {
        $request->validate([
            'efectivo_contado' => ['required', 'numeric', 'min:0'],
            'observacion_arqueo' => ['nullable', 'string', 'max:500'],
        ]);

        $caja = $this->cajaService->getCajaActiva();

        if (! $caja) {
            return redirect()->route('caja.index')
                ->with('error', 'No hay caja activa.');
        }

        $esperado = $this->cajaService->calcularEfectivoEsperado($caja);
        $contado = round((float) $request->efectivo_contado, 2);
        $observacion = trim((string) $request->observacion_arqueo);

        if (round($contado - $esperado, 2) != 0.0 && $observacion === '') {
            return back()
                ->withErrors(['observacion_arqueo' => 'Debe registrar una observación cuando existe diferencia en el arqueo.'])
                ->withInput();
        }

        try {
            app(AuditService::class)->anotarAccion('cerrar caja');

            $caja = $this->cajaService->cerrarConArqueo(
                $caja,
                $contado,
                $observacion === '' ? null : $observacion,
                auth()->id()
            );

            $mensaje = 'Caja cerrada correctamente.';
            if ((float) $caja->diferencia > 0) {
                $mensaje .= ' Sobrante de S/ '.number_format($caja->diferencia, 2).'.';
            } elseif ((float) $caja->diferencia < 0) {
                $mensaje .= ' Faltante de S/ '.number_format(abs((float) $caja->diferencia), 2).'.';
            }

            return redirect()->route('caja.index')->with('success', $mensaje);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Registra un egreso manual clasificado en la caja activa.
     *
     * La categoría es obligatoria y solo admite el catálogo manual (se
     * excluye compra_abastecimiento, reservada al flujo de Compras, para no
     * duplicar operaciones de mercadería). No toca inventario ni Kardex.
     */
    public function registrarEgreso(Request $request): RedirectResponse
    {
        $request->validate([
            'monto' => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['required', 'string', 'max:500'],
            'tipo_pago' => ['required', 'in:efectivo,yape'],
            'categoria' => ['required', 'string', 'in:'.implode(',', array_keys(EgresoCaja::categoriasManuales()))],
            'trabajador_id' => ['nullable', 'integer', 'exists:trabajadores,id'],
        ]);

        $caja = $this->cajaService->getCajaActiva();

        if (! $caja) {
            return redirect()->route('caja.index')
                ->with('error', 'No hay caja activa.');
        }

        try {
            app(AuditService::class)->anotarAccion('registrar egreso');

            $this->cajaService->registrarEgreso($caja, [
                'monto' => $request->monto,
                'descripcion' => $request->descripcion,
                'tipo_pago' => $request->tipo_pago,
                'categoria' => $request->categoria,
                'trabajador_id' => $request->trabajador_id,
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('caja.index')
                ->with('success', 'Egreso registrado correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
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
        $caja->load('user', 'arqueoUser', 'egresos.user', 'ventas.pagos.metodoPago', 'cambioAceites.pagos.metodoPago');
        $caja->load(['lavados' => fn ($q) => $q->with('pagos.metodoPago')]);
        $resumen = $this->cajaService->calcularResumen($caja);
        $nombresMetodos = MetodoPago::pluck('nombre', 'codigo')->all();

        return view('caja.detalle', compact('caja', 'resumen', 'nombresMetodos'));
    }
}
