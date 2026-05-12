<?php

namespace App\Http\Controllers;

use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\CajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VentaController extends Controller
{
    public function __construct(private CajaService $cajaService) {}

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
        return view('ventas.create');
    }

    /**
     * Search active productos by name (Ajax endpoint).
     * Must be registered BEFORE the resource route to avoid Route Model Binding conflicts.
     */
    public function buscarProductos(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $productos = Producto::where('activo', true)
            ->where('nombre', 'like', '%' . $q . '%')
            ->select('id', 'nombre', 'precio_venta', 'stock')
            ->limit(10)
            ->get();

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
        if (!$caja) {
            return back()->with('error_caja', true);
        }

        $request->validate([
            'observacion'                 => ['nullable', 'string', 'max:500'],
            'subtotal'                    => ['required', 'numeric', 'min:0'],
            'total'                       => ['required', 'numeric', 'gt:0'],
            'metodo_pago'                 => ['required', 'in:efectivo,yape,izipay,mixto'],
            'monto_efectivo'              => ['nullable', 'numeric', 'min:0'],
            'monto_yape'                  => ['nullable', 'numeric', 'min:0'],
            'monto_izipay'                => ['nullable', 'numeric', 'min:0'],
            'productos'                   => ['required', 'array', 'min:1'],
            'productos.*.producto_id'     => ['required', 'integer', 'exists:productos,id'],
            'productos.*.cantidad'        => ['required', 'integer', 'min:1'],
            'productos.*.precio_unitario' => ['required', 'numeric', 'gt:0'],
            'productos.*.subtotal'        => ['required', 'numeric', 'min:0'],
        ], [
            'productos.required' => 'Debe agregar al menos un producto a la venta.',
            'productos.min'      => 'Debe agregar al menos un producto a la venta.',
        ]);

        $venta = null;
        DB::transaction(function () use ($request, $caja, &$venta) {
            $nextId      = (Venta::max('id') ?? 0) + 1;
            $correlativo = 'VTA-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $venta = Venta::create([
                'correlativo'    => $correlativo,
                'observacion'    => $request->observacion,
                'subtotal'       => $request->subtotal,
                'total'          => $request->total,
                'metodo_pago'    => $request->metodo_pago,
                'monto_efectivo' => $request->metodo_pago === 'mixto' ? $request->monto_efectivo : null,
                'monto_yape'     => $request->metodo_pago === 'mixto' ? $request->monto_yape     : null,
                'monto_izipay'   => $request->metodo_pago === 'mixto' ? $request->monto_izipay   : null,
                'user_id'        => auth()->id(),
                'caja_id'        => $caja->id,
            ]);

            foreach ($request->productos as $item) {
                DetalleVenta::create([
                    'venta_id'        => $venta->id,
                    'producto_id'     => $item['producto_id'],
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal'        => $item['subtotal'],
                ]);

                Producto::where('id', $item['producto_id'])
                        ->decrement('stock', $item['cantidad']);
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
        $venta->load('user', 'detalles.producto');

        return view('ventas.show', compact('venta'));
    }

    /**
     * Display the printable ticket for the specified venta.
     */
    public function ticket(Venta $venta): View
    {
        $venta->load('user', 'detalles.producto');

        return view('ventas.ticket', compact('venta'));
    }

    /**
     * Remove the specified venta from the database.
     * Restores product stock for each detalle line within a DB transaction.
     */
    public function destroy(Venta $venta): RedirectResponse
    {
        try {
            DB::transaction(function () use ($venta) {
                foreach ($venta->detalles as $detalle) {
                    Producto::where('id', $detalle->producto_id)
                            ->increment('stock', $detalle->cantidad);
                }
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
