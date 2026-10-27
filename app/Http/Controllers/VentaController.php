<?php

namespace App\Http\Controllers;

use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\AuditService;
use App\Services\CajaService;
use App\Services\KardexService;
use App\Services\PagoService;
use App\Services\TotalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VentaController extends Controller
{
    public function __construct(
        private CajaService $cajaService,
        private KardexService $kardexService,
        private PagoService $pagoService,
        private TotalesService $totalesService,
    ) {}

    /**
     * Display a paginated listing of ventas.
     */
    public function index(): View
    {
        $ventas = Venta::with('user')->latest()->paginate(10);

        return view('ventas.index', compact('ventas'));
    }

    /**
     * Show the form for creating a new venta.
     */
    public function create(): View
    {
        $metodosPago = $this->pagoService->metodosActivos();

        $idsOld = collect(old('productos', []))
            ->pluck('producto_id')
            ->filter()
            ->unique()
            ->values();

        $catalogoProductos = Producto::whereIn('id', $idsOld)
            ->get(['id', 'nombre', 'precio_venta', 'stock', 'inventario'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'precio_venta' => (float) $p->precio_venta,
                'stock' => (int) $p->stock,
                'stock_bajo' => $p->esta_en_alerta,
            ])
            ->values();

        return view('ventas.create', compact('metodosPago', 'catalogoProductos'));
    }

    /**
     * Search active productos by name (Ajax endpoint).
     * Must be registered BEFORE the resource route to avoid Route Model Binding conflicts.
     */
    public function buscarProductos(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $productos = Producto::where('activo', true)
            ->where('nombre', 'like', '%'.$q.'%')
            ->where('stock', '>', 0)
            ->select('id', 'nombre', 'precio_venta', 'stock', 'inventario')
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'precio_venta' => $p->precio_venta,
                'stock' => $p->stock,
                'stock_bajo' => $p->esta_en_alerta,
            ]);

        return response()->json($productos);
    }

    /**
     * Store a newly created venta in the database.
     * Persists the venta header, all detalle lines, and decrements product stock
     * within a single DB transaction to guarantee atomicity.
     */
    public function store(Request $request): RedirectResponse
    {
        $caja = $this->cajaService->getCajaActiva();
        if (! $caja) {
            return back()->with('error_caja', true)->withInput();
        }

        $request->validate(array_merge([
            'observacion' => ['nullable', 'string', 'max:500'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'gt:0'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'productos.*.cantidad' => ['required', 'integer', 'min:1'],
            'productos.*.precio_unitario' => ['required', 'numeric', 'gt:0'],
            'productos.*.subtotal' => ['required', 'numeric', 'min:0'],
        ], $this->pagoService->reglas()), [
            'productos.required' => 'Debe agregar al menos un producto a la venta.',
            'productos.min' => 'Debe agregar al menos un producto a la venta.',
        ]);

        $lineasPago = $this->pagoService->validar($request, (float) $request->total);

        // Coherencia aritmética (patrón Compras): los subtotales de línea se
        // recalculan en el servidor; subtotal y total de cabecera deben
        // cuadrar con el detalle (el total admite descuento/facturación).
        $lineas = $this->totalesService->normalizarLineas($request->productos, 'precio_unitario', 'subtotal');
        $suma = $this->totalesService->suma($lineas, 'subtotal');
        $this->totalesService->validarIgualdad((float) $request->subtotal, $suma, 'subtotal');
        $this->totalesService->validarCabecera((float) $request->total, $suma);

        foreach ($lineas as $item) {
            $producto = Producto::findOrFail($item['producto_id']);
            if ($item['cantidad'] > $producto->stock) {
                return back()->withErrors([
                    "productos.{$item['producto_id']}.cantidad" => "La cantidad de \"{$producto->nombre}\" ({$item['cantidad']}) excede el stock disponible ({$producto->stock}).",
                ])->withInput();
            }
        }

        $venta = null;
        $lineasPago = $lineasPago ?? [];
        $pagoService = $this->pagoService;
        DB::transaction(function () use ($request, $caja, &$venta, $lineasPago, $pagoService, $lineas, $suma) {
            $nextId = (Venta::max('id') ?? 0) + 1;
            $correlativo = 'VTA-'.str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $venta = Venta::create([
                'correlativo' => $correlativo,
                'observacion' => $request->observacion,
                'subtotal' => $suma,
                'total' => $request->total,
                'user_id' => auth()->id(),
                'caja_id' => $caja->id,
            ]);

            $pagoService->guardarLineas($venta, $lineasPago, auth()->id(), $caja->id);

            foreach ($lineas as $item) {
                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $item['subtotal'],
                ]);

                $producto = Producto::find($item['producto_id']);
                $stockAntes = $producto->stock;

                Producto::where('id', $item['producto_id'])
                    ->decrement('stock', $item['cantidad']);

                $this->kardexService->registrarSalida(
                    $producto,
                    $item['cantidad'],
                    $stockAntes,
                    'venta',
                    $venta->correlativo
                );
            }
        });

        return redirect()->route('ventas.show', $venta)
            ->with('success', 'Venta registrada correctamente.');
    }

    /**
     * Display the specified venta with all its details.
     */
    public function show(Venta $venta): View
    {
        $venta->load('user', 'detalles.producto', 'devoluciones.detalles', 'devoluciones.user', 'devoluciones.ventaNueva', 'pagos.metodoPago');

        return view('ventas.show', compact('venta'));
    }

    /**
     * Display the printable ticket for the specified venta.
     */
    public function ticket(Venta $venta): View
    {
        $venta->load('user', 'detalles.producto', 'pagos.metodoPago');

        return view('ventas.ticket', compact('venta'));
    }

    /**
     * Remove the specified venta from the database.
     * Restores product stock for each detalle line within a DB transaction.
     *
     * Una venta con devoluciones registradas no puede anularse: sus líneas ya
     * tienen movimientos Kardex de devolución que quedarían huérfanos. En ese
     * caso se rechaza con un mensaje explícito (la restricción FK restrict en
     * devoluciones_venta.venta_id lo impediría de todos modos).
     */
    public function destroy(Venta $venta): RedirectResponse
    {
        if ($venta->devoluciones()->exists()) {
            return redirect()->route('ventas.index')
                ->with('error', 'No se puede eliminar la venta '.$venta->correlativo.': tiene devoluciones registradas. La anulación completa solo aplica a ventas sin devoluciones.');
        }

        try {
            $correlativo = $venta->correlativo;

            DB::transaction(function () use ($venta, $correlativo) {
                $venta->pagos()->delete();

                foreach ($venta->detalles as $detalle) {
                    $producto = Producto::find($detalle->producto_id);
                    $stockAntes = $producto->stock;

                    Producto::where('id', $detalle->producto_id)
                        ->increment('stock', $detalle->cantidad);

                    // Movimiento compensatorio (entrada) por la anulación de la venta
                    $this->kardexService->registrarEntrada(
                        $producto,
                        $detalle->cantidad,
                        $stockAntes,
                        'venta',
                        $correlativo
                    );
                }

                app(AuditService::class)->anotarAccion('anular venta');

                $venta->delete();
            });

            return redirect()->route('ventas.index')
                ->with('success', 'Venta eliminada y stock restaurado correctamente.');
        } catch (\Throwable $e) {
            return redirect()->route('ventas.index')
                ->with('error', 'No se pudo eliminar la venta. Intente nuevamente.');
        }
    }
}
