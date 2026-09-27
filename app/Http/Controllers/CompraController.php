<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\AuditService;
use App\Services\CajaService;
use App\Services\KardexService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    /**
     * Estados posibles de una compra y las transiciones permitidas desde cada uno.
     *
     * `recibida` y `anulada` son terminales en el Hito 1: una compra ya recibida no
     * puede revertirse, porque ya movió inventario, Kardex y caja. Agregar esos casos
     * más adelante exigiría una reversión transaccional, no una simple actualización.
     */
    private const TRANSICIONES = [
        'borrador' => ['recibida', 'anulada'],
        'recibida' => [],
        'anulada' => [],
    ];

    private const TIPOS_DOCUMENTO = ['Factura', 'Boleta', 'Ticket', 'Nota de venta', 'Guia de remision', 'Otros'];

    private const MENSAJES = [
        'proveedor_id.required' => 'Debe seleccionar un proveedor.',
        'proveedor_id.exists' => 'El proveedor seleccionado no existe.',
        'fecha.required' => 'La fecha de la compra es obligatoria.',
        'fecha.date' => 'La fecha de la compra no es válida.',
        'tipo_documento.in' => 'El tipo de documento no es válido.',
        'numero_documento.max' => 'El número de documento es demasiado largo.',
        'observaciones.max' => 'Las observaciones son demasiado largas.',
        'detalle.required' => 'Debe agregar al menos un producto a la compra.',
        'detalle.min' => 'Debe agregar al menos un producto a la compra.',
        'detalle.*.producto_id.required' => 'Cada línea debe tener un producto.',
        'detalle.*.producto_id.exists' => 'Uno de los productos seleccionados no existe.',
        'detalle.*.producto_id.distinct' => 'No puede repetir el mismo producto en dos líneas.',
        'detalle.*.cantidad.required' => 'Cada línea debe tener una cantidad.',
        'detalle.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
        'detalle.*.cantidad.max' => 'La cantidad es demasiado grande.',
        'detalle.*.costo_unitario.required' => 'Cada línea debe tener un costo unitario.',
        'detalle.*.costo_unitario.numeric' => 'El costo unitario debe ser numérico.',
        'detalle.*.costo_unitario.min' => 'El costo unitario no puede ser negativo.',
    ];

    /**
     * Reglas de validación compartidas entre store y update.
     */
    private function reglas(): array
    {
        return [
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'fecha' => ['required', 'date'],
            'tipo_documento' => ['nullable', 'string', 'max:20', 'in:'.implode(',', self::TIPOS_DOCUMENTO)],
            'numero_documento' => ['nullable', 'string', 'max:50'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'detalle' => ['required', 'array', 'min:1'],
            'detalle.*.producto_id' => ['required', 'integer', 'exists:productos,id', 'distinct'],
            'detalle.*.cantidad' => ['required', 'integer', 'min:1', 'max:999999'],
            'detalle.*.costo_unitario' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
        ];
    }

    /**
     * Listado de compras con filtros por estado y proveedor.
     */
    public function index(Request $request): View
    {
        $compras = Compra::query()
            ->with('proveedor')
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')->toString()))
            ->when($request->filled('proveedor_id'), fn ($q) => $q->where('proveedor_id', $request->integer('proveedor_id')))
            ->latest('fecha')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $proveedores = Proveedor::orderBy('razon_social')->pluck('razon_social', 'id');

        return view('compras.index', compact('compras', 'proveedores'));
    }

    public function create(): View
    {
        $proveedores = Proveedor::where('estado', '1')->orderBy('razon_social')->get();
        $productos = Producto::where('activo', 1)->orderBy('nombre')->get(['id', 'nombre']);

        return view('compras.create', compact('proveedores', 'productos'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->reglas(), self::MENSAJES);

        $compra = DB::transaction(function () use ($validated, $request) {
            [$lineas, $subtotal] = $this->prepararLineas($validated['detalle']);

            $compra = Compra::create([
                'correlativo' => null,
                'proveedor_id' => $validated['proveedor_id'],
                'fecha' => $validated['fecha'],
                'tipo_documento' => $validated['tipo_documento'] ?? null,
                'numero_documento' => $validated['numero_documento'] ?? null,
                'estado' => 'borrador',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'observaciones' => $validated['observaciones'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            $this->guardarLineas($compra, $lineas);

            return $compra;
        });

        return redirect()->route('compras.show', $compra)
            ->with('success', 'Compra registrada como borrador. Recíbela para ingresar las existencias.');
    }

    public function show(Compra $compra): View
    {
        $compra->load(['proveedor', 'user', 'detalles.producto']);

        return view('compras.show', compact('compra'));
    }

    public function edit(Compra $compra): View|RedirectResponse
    {
        if ($this->rechazarSiNoEsBorrador($compra)) {
            return redirect()->route('compras.show', $compra);
        }

        $compra->load('detalles.producto');
        $proveedores = Proveedor::where('estado', '1')->orderBy('razon_social')->get();
        $productos = Producto::where('activo', 1)->orderBy('nombre')->get(['id', 'nombre']);

        return view('compras.edit', compact('compra', 'proveedores', 'productos'));
    }

    public function update(Request $request, Compra $compra): RedirectResponse
    {
        if ($this->rechazarSiNoEsBorrador($compra)) {
            return redirect()->route('compras.show', $compra);
        }

        $validated = $request->validate($this->reglas(), self::MENSAJES);

        DB::transaction(function () use ($compra, $validated) {
            [$lineas, $subtotal] = $this->prepararLineas($validated['detalle']);

            $compra->update([
                'proveedor_id' => $validated['proveedor_id'],
                'fecha' => $validated['fecha'],
                'tipo_documento' => $validated['tipo_documento'] ?? null,
                'numero_documento' => $validated['numero_documento'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'observaciones' => $validated['observaciones'] ?? null,
            ]);

            $compra->detalles()->delete();
            $this->guardarLineas($compra, $lineas);
        });

        return redirect()->route('compras.show', $compra)
            ->with('success', 'Compra actualizada correctamente.');
    }

    /**
     * Elimina una compra. Solo los borradores son eliminables: una compra recibida ya
     * movió existencias, Kardex y caja, y borrarla dejaría esos registros huérfanos.
     */
    public function destroy(Compra $compra): RedirectResponse
    {
        if ($this->rechazarSiNoEsBorrador($compra)) {
            return redirect()->route('compras.show', $compra);
        }

        $compra->delete();

        return redirect()->route('compras.index')
            ->with('success', 'Borrador de compra eliminado.');
    }

    /**
     * Anula un borrador. La anulación se reserva al borrador porque una compra
     * recibida no tiene reversión disponible en esta fase.
     */
    public function anular(Compra $compra): RedirectResponse
    {
        if (! in_array('anulada', self::TRANSICIONES[$compra->estado] ?? [], true)) {
            return $this->errorTransicion($compra, 'anular');
        }

        app(AuditService::class)->anotarAccion('anular compra');
        $compra->update(['estado' => 'anulada']);

        return redirect()->route('compras.show', $compra)
            ->with('success', 'Compra anulada.');
    }

    /**
     * Anula una compra RECIBIDA generando salida compensatoria en Kardex
     * e ingreso compensatorio en caja (nota de crédito).
     *
     * Solo permitido en estado 'recibida'. Requiere caja abierta.
     */
    public function anularRecibida(Request $request, Compra $compra): RedirectResponse
    {
        if (! $compra->esRecibida()) {
            return $this->errorTransicion($compra, 'anular con reversión');
        }

        // Caja debe estar abierta ANTES de iniciar la transacción
        $caja = app(CajaService::class)->getCajaActiva();
        if (! $caja) {
            return redirect()->route('compras.show', $compra)
                ->with('error_caja', true);
        }

        try {
            $resultado = DB::transaction(function () use ($compra, $caja, $request) {
                // 1. Lock compra
                $compra = Compra::lockForUpdate()->findOrFail($compra->id);

                if (! $compra->esRecibida()) {
                    throw new \RuntimeException('La compra ya no está en estado recibida.');
                }

                // 2. Lock productos ordenados ASC para evitar deadlocks
                $lineas = $compra->detalles()
                    ->join('productos', 'detalle_compras.producto_id', '=', 'productos.id')
                    ->select('detalle_compras.*', 'productos.stock')
                    ->orderBy('detalle_compras.producto_id')
                    ->get();

                foreach ($lineas as $linea) {
                    $producto = Producto::lockForUpdate()->findOrFail($linea->producto_id);

                    $stockAntes = $producto->stock;
                    $cantidad = (int) $linea->cantidad;
                    $stockDespues = $stockAntes - $cantidad;

                    if ($stockDespues < 0) {
                        throw new \RuntimeException(
                            "Stock insuficiente para {$producto->nombre}. " .
                            "Disponible: {$stockAntes}, requerido: {$cantidad}"
                        );
                    }

                    // Actualizar stock e inventario
                    $producto->update([
                        'stock' => $stockDespues,
                        'inventario' => $stockDespues,
                    ]);

                    // Kardex salida compensatoria (fuente = ajuste_negativo)
                    app(KardexService::class)->registrarSalidaCompensatoria(
                        $producto,
                        $cantidad,
                        $stockAntes,
                        $compra->correlativo
                    );
                }

                // Ingreso compensatorio en caja (nota de crédito)
                app(CajaService::class)->registrarIngresoCompensatorio($caja, [
                    'monto' => (float) $compra->total,
                    'descripcion' => "Anulación compra {$compra->correlativo} ({$compra->proveedor->razon_social})",
                    'tipo_pago' => 'transferencia',
                    'user_id' => $request->user()->id,
                ]);

                // Marcar como anulada
                $compra->update([
                    'estado' => 'anulada',
                    'fecha_anulacion' => now(),
                ]);

                // Auditoría
                app(AuditService::class)->anotarAccion('anular compra recibida');

                return [
                    'compra' => $compra,
                ];
            });

            // Debug: log the created egreso
            $egresoCreado = \App\Models\EgresoCaja::where('descripcion', 'like', '%Anulación compra ' . $resultado['compra']->correlativo . '%')->first();
            \Log::info('Egreso creado:', ['id' => $egresoCreado?->id, 'descripcion' => $egresoCreado?->descripcion, 'monto' => $egresoCreado?->monto]);

            return redirect()->route('compras.show', $resultado['compra'])
                ->with('success', 'Compra anulada con salida compensatoria y nota de crédito registrada.');

        } catch (\RuntimeException $e) {
            \Log::error('anularRecibida RuntimeException: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('compras.show', $compra)
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            \Log::error('anularRecibida Throwable: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('compras.show', $compra)
                ->with('error', 'Error al anular la compra: '.$e->getMessage());
        }
    }

    /**
     * Recibe una compra en estado borrador: ingresa mercadería, actualiza stock,
     * registra Kardex, actualiza precio_compra y genera egreso de caja.
     *
     * Todo se ejecuta en una única transacción con bloqueos pesimistas para
     * garantizar atomicidad y evitar deadlocks (requisitos 29-40, 90).
     */
    public function recibir(Request $request, Compra $compra): RedirectResponse
    {
        if ($compra->estado !== 'borrador') {
            return $this->errorTransicion($compra, 'recibir');
        }

        // La caja debe existir y estar abierta ANTES de iniciar la transacción,
        // para no dejar rastro si falla (requisito 29).
        $caja = app(CajaService::class)->getCajaActiva();
        if (! $caja) {
            return redirect()->route('compras.show', $compra)
                ->with('error_caja', true);
        }

        // Verificar productos inactivos antes de bloquear (requisito 30).
        $productosInactivos = $compra->detalles()
            ->join('productos', 'detalle_compras.producto_id', '=', 'productos.id')
            ->where('productos.activo', 0)
            ->pluck('productos.nombre')
            ->all();

        if ($productosInactivos) {
            return redirect()->route('compras.show', $compra)
                ->with('error', 'No se puede recibir: los siguientes productos están inactivos: '.implode(', ', $productosInactivos));
        }

        try {
            $resultado = DB::transaction(function () use ($compra, $caja, $request) {
                // La compra ya viene del route model binding; verificamos estado y usamos esa instancia
                // para evitar lockForUpdate redundante que causa problemas en SQLite.
                if ($compra->estado !== 'borrador') {
                    throw new \RuntimeException('La compra ya no está en borrador.');
                }

                // 2. Generar correlativo si falta (requisito 31)
                $correlativo = $compra->correlativo ?? app(KardexService::class)->siguienteCorrelativoCompra();

                // 3. Procesar cada línea ordenada por producto_id para evitar deadlocks (requisito 32, 35-36)
                $lineas = $compra->detalles()
                    ->join('productos', 'detalle_compras.producto_id', '=', 'productos.id')
                    ->select('detalle_compras.*', 'productos.activo', 'productos.precio_compra as precio_compra_actual')
                    ->orderBy('detalle_compras.producto_id')
                    ->get();

                $productosConMargenPerdido = [];

                foreach ($lineas as $linea) {
                    // Bloquear producto (el lock crítico para evitar deadlocks)
                    $producto = Producto::lockForUpdate()->findOrFail($linea->producto_id);

                    // Verificar estado activo (doble chequeo dentro de la transacción)
                    if (! $producto->activo) {
                        throw new \RuntimeException("El producto {$producto->nombre} está inactivo.");
                    }

                    $stockAntes = $producto->stock;
                    $cantidad = (int) $linea->cantidad;
                    $stockDespues = $stockAntes + $cantidad;

                    // Actualizar stock e inventario (requisitos 33-34)
                    $producto->update([
                        'stock' => $stockDespues,
                        'inventario' => $stockDespues,
                    ]);

                    // Registrar entrada en Kardex (requisito 35)
                    app(KardexService::class)->registrarEntradaCompra(
                        $producto,
                        $cantidad,
                        $stockAntes,
                        $correlativo
                    );

                    // Actualizar precio_compra salvo que sea 0 (costo desconocido) — requisito 36
                    $nuevoCosto = (float) $linea->costo_unitario;
                    if ($nuevoCosto > 0) {
                        $producto->update(['precio_compra' => round($nuevoCosto, 2)]);

                        // Advertencia de margen (requisito 40 / design 6.4)
                        if ($producto->precio_venta > 0 && $nuevoCosto > $producto->precio_venta) {
                            $productosConMargenPerdido[] = $producto->nombre;
                        }
                    }
                }

                // 4. Registrar egreso en caja (requisito 37)
                $proveedorNombre = $compra->proveedor?->razon_social ?? 'Proveedor';
                $egreso = app(CajaService::class)->registrarEgreso($caja, [
                    'monto' => (float) $compra->total,
                    'descripcion' => "Pago compra {$correlativo} ({$proveedorNombre})",
                    'tipo_pago' => 'efectivo',
                    'user_id' => $request->user()->id,
                ]);

                // 5. Marcar compra como recibida (requisito 38)
                $compra->update([
                    'correlativo' => $correlativo,
                    'estado' => 'recibida',
                    'caja_id' => $caja->id,
                    'egreso_caja_id' => $egreso->id,
                    'fecha_recepcion' => now(),
                ]);
                // Auditoría
                app(AuditService::class)->anotarAccion('recibir compra');

                $compra->refresh();

                return [
                    'compra' => $compra,
                    'productosConMargenPerdido' => $productosConMargenPerdido,
                ];
            });

            $mensaje = "Compra recibida como {$resultado['compra']->correlativo}. Stock e inventario actualizados.";

            if ($resultado['productosConMargenPerdido']) {
                $mensaje .= ' ⚠ Productos con costo > precio de venta: '.implode(', ', $resultado['productosConMargenPerdido']);
            }

            return redirect()->route('compras.show', $resultado['compra'])
                ->with('success', $mensaje);

        } catch (\RuntimeException $e) {
            return redirect()->route('compras.show', $compra)
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // Cualquier error inesperado: revertido por la transacción
            return redirect()->route('compras.show', $compra)
                ->with('error', 'Error al recibir la compra: '.$e->getMessage());
        }
    }

    /**
     * Normaliza las líneas y calcula el total en el servidor.
     *
     * El formulario envía `subtotal` y `total` por comodidad, pero no se confían:
     * se recalculan aquí con `round(..., 2)` para que la suma de subtotales sea
     * exactamente el total almacenado, sin arrastrar coma flotante.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function prepararLineas(array $detalle): array
    {
        $lineas = collect($detalle)->map(function (array $linea) {
            $cantidad = (int) $linea['cantidad'];
            $costo = (float) $linea['costo_unitario'];

            return [
                'producto_id' => (int) $linea['producto_id'],
                'cantidad' => $cantidad,
                'costo_unitario' => round($costo, 2),
                'subtotal' => round($cantidad * $costo, 2),
            ];
        })->values()->all();

        $subtotal = round(collect($lineas)->sum('subtotal'), 2);

        return [$lineas, $subtotal];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    private function guardarLineas(Compra $compra, array $lineas): void
    {
        foreach ($lineas as $linea) {
            DetalleCompra::create($linea + ['compra_id' => $compra->id]);
        }
    }

    /**
     * Corta la edición, el borrado y la anulación cuando la compra ya no es borrador.
     *
     * @return bool True si la operación fue rechazada y ya se respondió al usuario.
     */
    private function rechazarSiNoEsBorrador(Compra $compra): bool
    {
        if ($compra->esBorrador()) {
            return false;
        }

        $this->errorTransicion($compra, 'modificar');

        return true;
    }

    /**
     * Mensaje explícito de transición rechazada, en lugar de un 403 genérico.
     */
    private function errorTransicion(Compra $compra, string $accion): RedirectResponse
    {
        if ($compra->estado === 'recibida') {
            $motivo = 'ya fue recibida, por lo que las existencias, el Kardex y el egreso de caja ya están registrados. La reversión no está disponible en esta fase.';
        } elseif ($compra->estado === 'anulada') {
            $motivo = 'está anulada y no admite cambios.';
        } else {
            $motivo = 'no está en un estado que permita la operación.';
        }

        return redirect()->route('compras.show', $compra)
            ->with('error', "No se puede {$accion} la compra {$compra->etiquetaEstado()}: {$motivo}");
    }
}
