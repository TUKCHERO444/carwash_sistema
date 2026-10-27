<?php

namespace App\Http\Controllers;

use App\Models\DetalleDevolucionVenta;
use App\Models\DetalleVenta;
use App\Models\DevolucionVenta;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\AuditService;
use App\Services\CajaService;
use App\Services\KardexService;
use App\Services\PagoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class DevolucionVentaController extends Controller
{
    public function __construct(
        private CajaService $cajaService,
        private KardexService $kardexService,
        private PagoService $pagoService,
    ) {}

    /**
     * Listado paginado de devoluciones y cambios.
     */
    public function index(): View
    {
        $devoluciones = DevolucionVenta::with(['venta', 'ventaNueva', 'user'])
            ->latest()
            ->paginate(10);

        return view('devoluciones.index', compact('devoluciones'));
    }

    /**
     * Detalle de una devolución con trazabilidad completa.
     */
    public function show(DevolucionVenta $devolucion): View
    {
        $devolucion->load([
            'venta',
            'ventaNueva',
            'detalles.producto',
            'detalles.detalleVenta',
            'user',
            'caja',
        ]);

        return view('devoluciones.show', compact('devolucion'));
    }

    /**
     * Formulario para devolver líneas de una venta existente.
     * Muestra por línea: vendida, ya devuelta y disponible.
     */
    public function create(Venta $venta): View
    {
        $venta->load(['user', 'detalles.producto', 'devoluciones.detalles']);

        $lineas = $venta->detalles->map(function (DetalleVenta $detalle) use ($venta) {
            $devuelta = $venta->cantidadDevueltaPorDetalle($detalle->id);

            return [
                'detalle_venta_id' => $detalle->id,
                'producto_id' => $detalle->producto_id,
                'producto_nombre' => $detalle->producto->nombre,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'vendida' => (int) $detalle->cantidad,
                'devuelta' => $devuelta,
                'disponible' => (int) $detalle->cantidad - $devuelta,
            ];
        });

        $sustituto = null;
        $productoNuevoId = old('producto_nuevo_id');
        if ($productoNuevoId) {
            $producto = Producto::find($productoNuevoId);
            if ($producto) {
                $sustituto = [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'precio_venta' => (float) $producto->precio_venta,
                    'stock' => (int) $producto->stock,
                ];
            }
        }

        return view('ventas.devoluciones.create', compact('venta', 'lineas', 'sustituto'));
    }

    /**
     * Registra una devolución parcial o un cambio (devolución + nueva venta).
     *
     * La venta original nunca se modifica ni se elimina. Todo ocurre en una
     * única transacción: líneas de devolución, entradas Kardex, egreso(s) de
     * caja y, si es cambio, la venta sustituta con su salida Kardex.
     */
    public function store(Request $request, Venta $venta): RedirectResponse
    {
        $caja = $this->cajaService->getCajaActiva();
        if (! $caja) {
            return back()->with('error_caja', true)->withInput();
        }

        $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
            'tipo' => ['required', 'in:devolucion,cambio'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.detalle_venta_id' => ['required', 'integer', 'exists:detalle_ventas,id'],
            'lineas.*.cantidad' => ['required', 'integer', 'min:0'],
            'producto_nuevo_id' => ['required_if:tipo,cambio', 'integer', 'exists:productos,id'],
            'cantidad_nueva' => ['required_if:tipo,cambio', 'integer', 'min:1'],
        ], [
            'motivo.required' => 'Debe indicar el motivo de la devolución.',
            'lineas.required' => 'Debe seleccionar al menos un producto a devolver.',
            'lineas.min' => 'Debe seleccionar al menos un producto a devolver.',
            'producto_nuevo_id.required_if' => 'Debe seleccionar el producto sustituto para el cambio.',
            'cantidad_nueva.required_if' => 'Debe indicar la cantidad del producto sustituto.',
        ]);

        // Se ignoran las líneas con cantidad 0 (el JS solo envía las > 0,
        // pero el servidor no confía en el cliente).
        $lineasSolicitadas = collect($request->input('lineas', []))
            ->filter(fn ($l) => (int) ($l['cantidad'] ?? 0) > 0)
            ->values();

        if ($lineasSolicitadas->isEmpty()) {
            return back()
                ->withErrors(['lineas' => 'Debe seleccionar al menos un producto a devolver.'])
                ->withInput();
        }

        try {
            $devolucion = DB::transaction(function () use ($request, $venta, $caja, $lineasSolicitadas) {
                $ventaBloqueada = Venta::lockForUpdate()->findOrFail($venta->id);

                $detalles = DetalleVenta::where('venta_id', $ventaBloqueada->id)
                    ->lockForUpdate()
                    ->orderBy('producto_id')
                    ->get()
                    ->keyBy('id');

                $yaDevuelto = DetalleDevolucionVenta::whereIn('detalle_venta_id', $detalles->keys())
                    ->whereHas('devolucion', fn ($q) => $q->where('venta_id', $ventaBloqueada->id))
                    ->selectRaw('detalle_venta_id, SUM(cantidad) as total')
                    ->groupBy('detalle_venta_id')
                    ->pluck('total', 'detalle_venta_id');

                // 1. Validar pertenencia y topes por línea (vendida − ya devuelta).
                $lineasValidadas = [];
                foreach ($lineasSolicitadas as $linea) {
                    $detalle = $detalles->get((int) $linea['detalle_venta_id']);

                    if (! $detalle) {
                        throw new RuntimeException('Una de las líneas seleccionadas no pertenece a esta venta.');
                    }

                    $cantidad = (int) $linea['cantidad'];
                    $disponible = (int) $detalle->cantidad - (int) ($yaDevuelto[$detalle->id] ?? 0);

                    if ($cantidad > $disponible) {
                        throw new RuntimeException(
                            "La cantidad a devolver de \"{$detalle->producto->nombre}\" ({$cantidad}) "
                            ."excede la disponible ({$disponible})."
                        );
                    }

                    $lineasValidadas[] = ['detalle' => $detalle, 'cantidad' => $cantidad];
                }

                // 2. Total recalculado en el servidor con el precio original.
                $totalDevuelto = round(collect($lineasValidadas)->sum(
                    fn ($l) => $l['cantidad'] * (float) $l['detalle']->precio_unitario
                ), 2);

                if ($totalDevuelto <= 0) {
                    throw new RuntimeException('El importe a devolver debe ser mayor que cero.');
                }

                // 3. Cabecera DEV-XXXX. La etiqueta de auditoría se anota ANTES del
                // create para que el evento `created` del observer la consuma.
                $nextId = (DevolucionVenta::max('id') ?? 0) + 1;
                $correlativo = 'DEV-'.str_pad($nextId, 4, '0', STR_PAD_LEFT);

                app(AuditService::class)->anotarAccion(
                    $request->input('tipo') === 'cambio' ? 'registrar cambio' : 'registrar devolución'
                );

                $devolucion = DevolucionVenta::create([
                    'correlativo' => $correlativo,
                    'venta_id' => $ventaBloqueada->id,
                    'caja_id' => $caja->id,
                    'tipo' => $request->input('tipo'),
                    'motivo' => trim((string) $request->input('motivo')),
                    'total_devuelto' => $totalDevuelto,
                    'user_id' => auth()->id(),
                ]);

                // 4. Líneas: reincorporar stock + entrada Kardex (fuente devolucion).
                $productoIds = collect($lineasValidadas)->map(fn ($l) => $l['detalle']->producto_id)->sort()->values();
                foreach ($productoIds as $productoId) {
                    $producto = Producto::lockForUpdate()->findOrFail($productoId);
                    foreach ($lineasValidadas as $linea) {
                        if ($linea['detalle']->producto_id !== $producto->id) {
                            continue;
                        }

                        $cantidad = $linea['cantidad'];
                        $precio = (float) $linea['detalle']->precio_unitario;

                        DetalleDevolucionVenta::create([
                            'devolucion_id' => $devolucion->id,
                            'detalle_venta_id' => $linea['detalle']->id,
                            'producto_id' => $producto->id,
                            'cantidad' => $cantidad,
                            'precio_unitario' => $precio,
                            'subtotal' => round($cantidad * $precio, 2),
                        ]);

                        $stockAntes = $producto->stock;
                        Producto::where('id', $producto->id)->increment('stock', $cantidad);

                        $this->kardexService->registrarEntrada(
                            $producto,
                            $cantidad,
                            $stockAntes,
                            'devolucion',
                            $devolucion->correlativo
                        );
                    }
                }

                // 5. Egreso(s) compensatorio(s) según el método de pago original.
                $this->registrarEgresosCompensatorios($ventaBloqueada, $caja, $devolucion, $totalDevuelto);

                // 6. Cambio: venta sustituta en la misma transacción.
                if ($request->input('tipo') === 'cambio') {
                    $this->registrarVentaSustituta($request, $caja, $ventaBloqueada, $devolucion);
                }

                return $devolucion;
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['lineas' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo registrar la devolución. Intente nuevamente.')->withInput();
        }

        $mensaje = "Devolución {$devolucion->correlativo} registrada correctamente.";
        if ($devolucion->esCambio()) {
            $devolucion->load('ventaNueva');
            $mensaje = "Cambio registrado: devolución {$devolucion->correlativo} y nueva venta {$devolucion->ventaNueva->correlativo}.";
        }

        return redirect()->route('devoluciones.show', $devolucion)->with('success', $mensaje);
    }

    /**
     * Genera 1..N egresos compensatorios que suman exactamente el total
     * devuelto, mapeando cada línea de pago al tipo_pago de egresos_caja
     * (efectivo, yape, transferencia, tarjeta).
     */
    private function registrarEgresosCompensatorios(Venta $venta, $caja, DevolucionVenta $devolucion, float $total): void
    {
        $base = "Devolución {$devolucion->correlativo} de {$venta->correlativo} ({$devolucion->motivo})";

        $venta->loadMissing('pagos.metodoPago');
        $componentes = [];
        foreach ($venta->pagos as $pago) {
            $componentes[] = [$this->pagoService->tipoEgresoPara($pago->metodoPago?->codigo ?? ''), (float) $pago->monto];
        }
        if ($componentes === []) {
            $componentes = [['efectivo', $total]];
        } else {
            $componentes = $this->prorratearLineas($componentes, $total);
        }

        foreach ($componentes as $i => [$tipoPago, $monto]) {
            $descripcion = $base;
            if (count($componentes) > 1) {
                $descripcion .= " [mixto {$i}/".count($componentes).']';
            }

            $this->cajaService->registrarEgreso($caja, [
                'monto' => $monto,
                'descripcion' => mb_substr($descripcion, 0, 500),
                'tipo_pago' => $tipoPago,
                'user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Prorratea el total devuelto según las líneas de pago de la venta.
     * La suma de los egresos es exactamente el total (ajuste en el último).
     *
     * @param  list<array{0: string, 1: float}>  $componentes
     * @return list<array{0: string, 1: float}>
     */
    private function prorratearLineas(array $componentes, float $total): array
    {
        $totalPagos = round(array_sum(array_column($componentes, 1)), 2);

        $activos = array_values(array_filter($componentes, fn ($d) => $d[1] > 0));

        if ($totalPagos <= 0 || $activos === []) {
            return [['efectivo', round($total, 2)]];
        }

        $resultado = [];
        $acumulado = 0.0;
        foreach ($activos as $i => [$tipoPago, $monto]) {
            if ($i === count($activos) - 1) {
                $resultado[] = [$tipoPago, round($total - $acumulado, 2)];
            } else {
                $parte = round($total * ($monto / $totalPagos), 2);
                $resultado[] = [$tipoPago, $parte];
                $acumulado += $parte;
            }
        }

        return $resultado;
    }

    /**
     * Crea la venta sustituta del cambio: descuenta stock del producto nuevo
     * con salida Kardex (fuente devolucion) y la vincula a la devolución.
     */
    private function registrarVentaSustituta(Request $request, $caja, Venta $ventaOriginal, DevolucionVenta $devolucion): void
    {
        $producto = Producto::lockForUpdate()->findOrFail((int) $request->input('producto_nuevo_id'));

        if (! $producto->activo) {
            throw new RuntimeException("El producto \"{$producto->nombre}\" está inactivo.");
        }

        $cantidad = (int) $request->input('cantidad_nueva');

        if ($cantidad > $producto->stock) {
            throw new RuntimeException(
                "Stock insuficiente para \"{$producto->nombre}\". Disponible: {$producto->stock}, requerido: {$cantidad}."
            );
        }

        $precio = (float) $producto->precio_venta;
        $subtotal = round($cantidad * $precio, 2);

        $nextId = (Venta::max('id') ?? 0) + 1;
        $correlativo = 'VTA-'.str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $ventaNueva = Venta::create([
            'correlativo' => $correlativo,
            'observacion' => "Cambio por devolución {$devolucion->correlativo} de {$ventaOriginal->correlativo}",
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'user_id' => auth()->id(),
            'caja_id' => $caja->id,
        ]);

        $metodoId = MetodoPago::where('codigo', 'efectivo')->value('id')
            ?? MetodoPago::where('activo', true)->orderBy('id')->value('id');

        $this->pagoService->guardarLineas($ventaNueva, [[
            'metodo_pago_id' => $metodoId,
            'codigo' => 'efectivo',
            'monto' => $subtotal,
        ]], auth()->id(), $caja->id);

        DetalleVenta::create([
            'venta_id' => $ventaNueva->id,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'subtotal' => $subtotal,
        ]);

        $stockAntes = $producto->stock;
        Producto::where('id', $producto->id)->decrement('stock', $cantidad);

        $this->kardexService->registrarSalida(
            $producto,
            $cantidad,
            $stockAntes,
            'devolucion',
            $devolucion->correlativo
        );

        $devolucion->update(['venta_nueva_id' => $ventaNueva->id]);
    }
}
