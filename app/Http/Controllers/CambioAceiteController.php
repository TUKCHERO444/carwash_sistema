<?php

namespace App\Http\Controllers;

use App\Models\CambioAceite;
use App\Models\CambioProducto;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Services\AuditService;
use App\Services\CajaService;
use App\Services\ClienteAutomotorService;
use App\Services\KardexService;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CambioAceiteController extends Controller
{
    /**
     * Regla de placa: 6-7 caracteres alfanuméricos y opcional guion medio.
     */
    private const PLACA_RULE = 'regex:/^[A-Z0-9-]{6,7}$/i';

    /**
     * Solo letras (con acentos españoles y ñ) y espacios internos simples.
     * Rechaza números, símbolos y espacios dobles.
     */
    private const SOLO_LETRAS_RULE = 'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u';

    /**
     * DNI peruano: exactamente 8 dígitos.
     */
    private const DNI_RULE = 'regex:/^[0-9]{8}$/';

    /**
     * Teléfono móvil: exactamente 9 dígitos.
     */
    private const TELEFONO_RULE = 'regex:/^[0-9]{9}$/';

    /**
     * Reglas validación del cliente que persisten en Cliente/Automotor.
     * Deben coincidir con el patrón estándar de ClienteController/AutomotorController.
     */
    private function clienteRules(): array
    {
        return [
            'placa' => ['required', 'string', 'max:7', self::PLACA_RULE],
            'nombre' => ['nullable', 'string', 'max:100', self::SOLO_LETRAS_RULE],
            'telefono' => ['nullable', 'string', 'digits:9', self::TELEFONO_RULE],
            'dni' => ['nullable', 'string', 'digits:8', self::DNI_RULE],
        ];
    }

    public function __construct(
        private CajaService $cajaService,
        private ClienteAutomotorService $clienteAutomotorService,
        private KardexService $kardexService,
    ) {}

    /**
     * Tabla_Pendientes: lista CambioAceites con estado = 'pendiente'.
     * Vista: cambio-aceite.pendientes
     */
    public function index(): View
    {
        $cambioAceites = CambioAceite::with(['cliente', 'trabajadores'])
            ->pendientes()
            ->latest()
            ->paginate(10);

        return view('cambio-aceite.pendientes', compact('cambioAceites'));
    }

    public function create(): View
    {
        $trabajadores = Trabajador::where('estado', true)->get();

        return view('cambio-aceite.create', compact('trabajadores'));
    }

    /**
     * Crea CambioAceite con estado = 'pendiente'.
     * No valida caja ni campos de pago.
     * NO descuenta stock: el inventario se descuenta recién al confirmar el ticket
     * (ver procesarConfirmacion).
     * Redirige a cambio-aceite.index (Tabla_Pendientes).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(array_merge($this->clienteRules(), [
            'trabajadores_ids' => ['required', 'array', 'min:1'],
            'trabajadores_ids.*' => ['integer', 'exists:trabajadores,id'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'productos.*.cantidad' => ['required', 'integer', 'min:1'],
            'productos.*.precio' => ['required', 'numeric', 'gt:0'],
            'productos.*.total' => ['required', 'numeric', 'min:0'],
        ]), [
            'productos.required' => 'Debe agregar al menos un producto al cambio de aceite.',
            'productos.min' => 'Debe agregar al menos un producto al cambio de aceite.',
            'trabajadores_ids.required' => 'Debe asignar al menos un trabajador al cambio de aceite.',
            'trabajadores_ids.min' => 'Debe asignar al menos un trabajador al cambio de aceite.',
        ]);

        foreach ($request->productos as $item) {
            $producto = Producto::findOrFail($item['producto_id']);
            if ($item['cantidad'] > $producto->stock) {
                return back()->withErrors([
                    "productos.{$item['producto_id']}.cantidad" => "La cantidad de \"{$producto->nombre}\" ({$item['cantidad']}) excede el stock disponible ({$producto->stock}).",
                ])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($request) {
                $cliente = $this->clienteAutomotorService->obtenerCliente($request);
                $automotor = $this->clienteAutomotorService->obtenerAutomotor($request->placa, $cliente->id);

                $foto = null;
                if ($request->hasFile('foto')) {
                    $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
                    $foto = $result['secure_url'];
                }

                // Calcular precio en servidor (no confiar en el cliente)
                $precio = collect($request->productos)
                    ->sum(fn ($p) => $p['cantidad'] * $p['precio']);

                $cambioAceite = CambioAceite::create([
                    'cliente_id' => $cliente->id,
                    'automotor_id' => $automotor->placa,
                    'trabajador_id' => $request->trabajadores_ids[0],
                    'fecha' => $request->fecha,
                    'precio' => $precio,
                    'total' => $precio,
                    'descripcion' => $request->descripcion,
                    'foto' => $foto,
                    'user_id' => auth()->id(),
                    'estado' => 'pendiente',
                ]);

                $cambioAceite->trabajadores()->sync($request->trabajadores_ids);

                foreach ($request->productos as $item) {
                    CambioProducto::create([
                        'cambio_aceite_id' => $cambioAceite->id,
                        'producto_id' => $item['producto_id'],
                        'cantidad' => $item['cantidad'],
                        'precio' => $item['precio'],
                        'total' => $item['total'],
                    ]);
                }
            });

            return redirect()->route('cambio-aceite.index')
                ->with('success', 'Ticket de cambio de aceite registrado correctamente.');
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', 'No se pudo registrar el cambio de aceite. Intente nuevamente.');
        }
    }

    public function buscarProductos(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $productos = Producto::where('activo', true)
            ->where('nombre', 'like', '%'.$q.'%')
            ->where('stock', '>', 0)
            ->select('id', 'nombre', 'precio_venta', 'stock')
            ->limit(10)
            ->get();

        return response()->json($productos);
    }

    public function show(CambioAceite $cambioAceite): View
    {
        $cambioAceite->load(['cliente', 'trabajadores', 'user', 'productos']);

        return view('cambio-aceite.show', compact('cambioAceite'));
    }

    public function edit(CambioAceite $cambioAceite): View
    {
        $cambioAceite->load(['cliente', 'trabajadores', 'productos']);
        $trabajadores = Trabajador::where('estado', true)->get();

        $productosExistentes = $cambioAceite->productos->map(fn ($p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'precio' => (float) $p->pivot->precio,
            'cantidad' => (int) $p->pivot->cantidad,
            'total' => (float) $p->pivot->total,
        ])->values()->all();

        $trabajadoresAsignados = $cambioAceite->trabajadores->pluck('id')->toArray();

        $cambioAceiteMontos = [
            'efectivo' => $cambioAceite->monto_efectivo,
            'yape' => $cambioAceite->monto_yape,
            'izipay' => $cambioAceite->monto_izipay,
        ];

        return view('cambio-aceite.edit', compact('cambioAceite', 'trabajadores', 'productosExistentes', 'cambioAceiteMontos', 'trabajadoresAsignados'));
    }

    public function update(Request $request, CambioAceite $cambioAceite): RedirectResponse
    {
        $request->validate(array_merge($this->clienteRules(), [
            'trabajadores_ids' => ['required', 'array', 'min:1'],
            'trabajadores_ids.*' => ['integer', 'exists:trabajadores,id'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'precio' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'gt:0'],
            'metodo_pago' => ['required', 'in:efectivo,yape,izipay,mixto'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_yape' => ['nullable', 'numeric', 'min:0'],
            'monto_izipay' => ['nullable', 'numeric', 'min:0'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'productos.*.cantidad' => ['required', 'integer', 'min:1'],
            'productos.*.precio' => ['required', 'numeric', 'gt:0'],
            'productos.*.total' => ['required', 'numeric', 'min:0'],
        ]), [
            'productos.required' => 'Debe agregar al menos un producto al cambio de aceite.',
            'productos.min' => 'Debe agregar al menos un producto al cambio de aceite.',
            'trabajadores_ids.required' => 'Debe asignar al menos un trabajador al cambio de aceite.',
            'trabajadores_ids.min' => 'Debe asignar al menos un trabajador al cambio de aceite.',
        ]);

        $cambioAceite->load('productos');
        $oldStockMap = $cambioAceite->productos->pluck('pivot.cantidad', 'id')->toArray();

        foreach ($request->productos as $item) {
            $producto = Producto::findOrFail($item['producto_id']);
            $availableStock = $producto->stock + ($oldStockMap[$item['producto_id']] ?? 0);
            if ($item['cantidad'] > $availableStock) {
                return back()->withErrors([
                    "productos.{$item['producto_id']}.cantidad" => "La cantidad de \"{$producto->nombre}\" ({$item['cantidad']}) excede el stock disponible ({$availableStock}).",
                ])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($request, $cambioAceite) {
                $cliente = $this->clienteAutomotorService->obtenerCliente($request);
                $automotor = $this->clienteAutomotorService->obtenerAutomotor($request->placa, $cliente->id);

                $foto = $cambioAceite->foto;
                if ($request->hasFile('foto')) {
                    // Eliminar imagen anterior
                    if ($cambioAceite->foto) {
                        if (str_starts_with($cambioAceite->foto, 'http')) {
                            $parts = explode('/', $cambioAceite->foto);
                            $filename = end($parts);
                            $publicId = pathinfo($filename, PATHINFO_FILENAME);
                            try {
                                Cloudinary::uploadApi()->destroy($publicId);
                            } catch (\Exception $e) {
                            }
                        } elseif (Storage::disk('public')->exists($cambioAceite->foto)) {
                            Storage::disk('public')->delete($cambioAceite->foto);
                        }
                    }
                    $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
                    $foto = $result['secure_url'];
                }

                $cambioAceite->update([
                    'cliente_id' => $cliente->id,
                    'automotor_id' => $automotor->placa,
                    'trabajador_id' => $request->trabajadores_ids[0],
                    'fecha' => $request->fecha,
                    'precio' => $request->precio,
                    'total' => $request->total,
                    'descripcion' => $request->descripcion,
                    'foto' => $foto,
                    'metodo_pago' => $request->metodo_pago,
                    'monto_efectivo' => $request->metodo_pago === 'mixto' ? $request->monto_efectivo : null,
                    'monto_yape' => $request->metodo_pago === 'mixto' ? $request->monto_yape : null,
                    'monto_izipay' => $request->metodo_pago === 'mixto' ? $request->monto_izipay : null,
                ]);

                // Sincronizar trabajadores
                $cambioAceite->trabajadores()->sync($request->trabajadores_ids);

                // Cargar productos con pivot para restaurar stock
                $cambioAceite->load('productos');

                // Restaurar stock de los productos anteriores (movimiento de entrada compensatorio)
                foreach ($cambioAceite->productos as $productoAnterior) {
                    $producto = Producto::find($productoAnterior->id);
                    $stockAntes = $producto->stock;

                    Producto::where('id', $productoAnterior->id)
                        ->increment('stock', $productoAnterior->pivot->cantidad);

                    $this->kardexService->registrarEntrada(
                        $producto,
                        $productoAnterior->pivot->cantidad,
                        $stockAntes,
                        'cambio_aceite',
                        $automotor->placa
                    );
                }

                $syncData = [];
                foreach ($request->productos as $item) {
                    $syncData[$item['producto_id']] = [
                        'cantidad' => $item['cantidad'],
                        'precio' => $item['precio'],
                        'total' => $item['total'],
                    ];
                }
                $cambioAceite->productos()->sync($syncData);

                // Decrementar stock con los nuevos productos (nueva salida)
                foreach ($request->productos as $item) {
                    $producto = Producto::find($item['producto_id']);
                    $stockAntes = $producto->stock;

                    Producto::where('id', $item['producto_id'])
                        ->decrement('stock', $item['cantidad']);

                    $this->kardexService->registrarSalida(
                        $producto,
                        $item['cantidad'],
                        $stockAntes,
                        'cambio_aceite',
                        $automotor->placa
                    );
                }
            });

            return redirect()->route('cambio-aceite.show', $cambioAceite)
                ->with('success', 'Cambio de aceite actualizado correctamente.');
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', 'No se pudo actualizar el cambio de aceite. Intente nuevamente.');
        }
    }

    /**
     * Elimina el CambioAceite, restaura stock, elimina foto.
     * Éxito: redirige a cambio-aceite.index (Tabla_Pendientes).
     * Error: redirige a cambio-aceite.confirmar (Panel_Confirmacion).
     */
    public function destroy(CambioAceite $cambioAceite): RedirectResponse
    {
        try {
            if ($cambioAceite->foto) {
                if (str_starts_with($cambioAceite->foto, 'http')) {
                    $parts = explode('/', $cambioAceite->foto);
                    $filename = end($parts);
                    $publicId = pathinfo($filename, PATHINFO_FILENAME);
                    try {
                        Cloudinary::uploadApi()->destroy($publicId);
                    } catch (\Exception $e) {
                    }
                } elseif (Storage::disk('public')->exists($cambioAceite->foto)) {
                    Storage::disk('public')->delete($cambioAceite->foto);
                }
            }

            $placa = $cambioAceite->automotor_id;

            DB::transaction(function () use ($cambioAceite, $placa) {
                // El stock solo se descuenta al confirmar el ticket. Restaurar
                // únicamente si el ticket ya estaba confirmado, registrando el
                // movimiento compensatorio en el Kardex.
                if ($cambioAceite->estado === 'confirmado') {
                    // Cargar productos con pivot para restaurar stock
                    $cambioAceite->load('productos');

                    foreach ($cambioAceite->productos as $producto) {
                        $modelo = Producto::find($producto->id);
                        $stockAntes = $modelo->stock;

                        Producto::where('id', $producto->id)
                            ->increment('stock', $producto->pivot->cantidad);

                        $this->kardexService->registrarEntrada(
                            $modelo,
                            $producto->pivot->cantidad,
                            $stockAntes,
                            'cambio_aceite',
                            $placa ?? 'N/A'
                        );
                    }
                }

                $cambioAceite->delete();
            });

            return redirect()->route('cambio-aceite.index')
                ->with('success', 'Ticket eliminado y stock restaurado correctamente.');
        } catch (\Throwable $e) {
            return redirect()->route('cambio-aceite.confirmar', $cambioAceite)
                ->with('error', 'No se pudo eliminar el ticket. Intente nuevamente.');
        }
    }

    public function ticket(CambioAceite $cambioAceite): View
    {
        $cambioAceite->load(['cliente', 'trabajadores', 'user', 'productos']);

        return view('cambio-aceite.ticket', compact('cambioAceite'));
    }

    // ── Métodos nuevos ────────────────────────────────────────────────

    /**
     * Tabla_Confirmados: lista CambioAceites con estado = 'confirmado'.
     * Vista: cambio-aceite.confirmados
     */
    public function confirmados(): View
    {
        $cambioAceites = CambioAceite::with(['cliente', 'trabajador'])
            ->confirmados()
            ->latest()
            ->paginate(10);

        return view('cambio-aceite.confirmados', compact('cambioAceites'));
    }

    /**
     * Panel_Confirmacion: muestra el formulario completo de un pendiente.
     * Si ya está confirmado, redirige a cambio-aceite.confirmados.
     * Vista: cambio-aceite.confirmar
     */
    public function confirmar(CambioAceite $cambioAceite): View|RedirectResponse
    {
        if ($cambioAceite->estado === 'confirmado') {
            return redirect()->route('cambio-aceite.confirmados')
                ->with('info', 'Este cambio de aceite ya fue confirmado.');
        }

        $cambioAceite->load(['cliente', 'trabajadores', 'productos']);
        $trabajadores = Trabajador::where('estado', true)->get();

        $productosData = $cambioAceite->productos->map(fn ($p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'precio' => (float) $p->pivot->precio,
            'cantidad' => (int) $p->pivot->cantidad,
            'total' => (float) $p->pivot->total,
            'stock' => (int) $p->stock,
        ])->values()->all();

        $trabajadoresAsignados = $cambioAceite->trabajadores->pluck('id')->toArray();

        $montosData = [
            'efectivo' => $cambioAceite->monto_efectivo,
            'yape' => $cambioAceite->monto_yape,
            'izipay' => $cambioAceite->monto_izipay,
        ];

        return view('cambio-aceite.confirmar', compact(
            'cambioAceite',
            'trabajadores',
            'productosData',
            'montosData',
            'trabajadoresAsignados'
        ));
    }

    /**
     * Procesa la confirmación del pago.
     * Valida caja activa + campos de pago.
     * Actualiza estado a 'confirmado', sincroniza cambio_productos.
     * Redirige a cambio-aceite.index con mensaje de éxito.
     */
    public function procesarConfirmacion(Request $request, CambioAceite $cambioAceite): RedirectResponse
    {
        $caja = $this->cajaService->getCajaActiva();
        if (! $caja) {
            return back()->with('error_caja', true);
        }

        $request->validate(array_merge($this->clienteRules(), [
            'trabajadores_ids' => ['required', 'array', 'min:1'],
            'trabajadores_ids.*' => ['integer', 'exists:trabajadores,id'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'precio' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'gt:0'],
            'metodo_pago' => ['required', 'in:efectivo,yape,izipay,mixto'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_yape' => ['nullable', 'numeric', 'min:0'],
            'monto_izipay' => ['nullable', 'numeric', 'min:0'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'productos.*.cantidad' => ['required', 'integer', 'min:1'],
            'productos.*.precio' => ['required', 'numeric', 'gt:0'],
            'productos.*.total' => ['required', 'numeric', 'min:0'],
        ]), [
            'productos.required' => 'Debe agregar al menos un producto al cambio de aceite.',
            'productos.min' => 'Debe agregar al menos un producto al cambio de aceite.',
            'trabajadores_ids.required' => 'Debe asignar al menos un trabajador al cambio de aceite.',
            'trabajadores_ids.min' => 'Debe asignar al menos un trabajador al cambio de aceite.',
        ]);

        $cambioAceite->load('productos');
        $oldStockMap = $cambioAceite->productos->pluck('pivot.cantidad', 'id')->toArray();

        foreach ($request->productos as $item) {
            $producto = Producto::findOrFail($item['producto_id']);
            $availableStock = $producto->stock + ($oldStockMap[$item['producto_id']] ?? 0);
            if ($item['cantidad'] > $availableStock) {
                return back()->withErrors([
                    "productos.{$item['producto_id']}.cantidad" => "La cantidad de \"{$producto->nombre}\" ({$item['cantidad']}) excede el stock disponible ({$availableStock}).",
                ])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($request, $caja, $cambioAceite) {
                $cliente = $this->clienteAutomotorService->obtenerCliente($request);
                $automotor = $this->clienteAutomotorService->obtenerAutomotor($request->placa, $cliente->id, true);

                $foto = $cambioAceite->foto;
                if ($request->hasFile('foto')) {
                    // Eliminar imagen anterior
                    if ($cambioAceite->foto) {
                        if (str_starts_with($cambioAceite->foto, 'http')) {
                            $parts = explode('/', $cambioAceite->foto);
                            $filename = end($parts);
                            $publicId = pathinfo($filename, PATHINFO_FILENAME);
                            try {
                                Cloudinary::uploadApi()->destroy($publicId);
                            } catch (\Exception $e) {
                            }
                        } elseif (Storage::disk('public')->exists($cambioAceite->foto)) {
                            Storage::disk('public')->delete($cambioAceite->foto);
                        }
                    }
                    $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
                    $foto = $result['secure_url'];
                }

                app(AuditService::class)->anotarAccion('confirmar');

                $cambioAceite->update([
                    'cliente_id' => $cliente->id,
                    'automotor_id' => $automotor->placa,
                    'trabajador_id' => $request->trabajadores_ids[0],
                    'fecha' => $request->fecha,
                    'precio' => $request->precio,
                    'total' => $request->total,
                    'descripcion' => $request->descripcion,
                    'foto' => $foto,
                    'metodo_pago' => $request->metodo_pago,
                    'monto_efectivo' => $request->metodo_pago === 'mixto' ? $request->monto_efectivo : null,
                    'monto_yape' => $request->metodo_pago === 'mixto' ? $request->monto_yape : null,
                    'monto_izipay' => $request->metodo_pago === 'mixto' ? $request->monto_izipay : null,
                    'estado' => 'confirmado',
                    'caja_id' => $caja->id,
                ]);

                // Sincronizar trabajadores en pivote
                $cambioAceite->trabajadores()->sync($request->trabajadores_ids);

                // Sincronizar productos con valores finales y decrementar stock nuevo.
                // Este es el ÚNICO punto donde el cambio de aceite descuenta inventario:
                // se registra la salida en el Kardex por cada producto con la placa como origen.
                $syncData = [];
                foreach ($request->productos as $item) {
                    $syncData[$item['producto_id']] = [
                        'cantidad' => $item['cantidad'],
                        'precio' => $item['precio'],
                        'total' => $item['total'],
                    ];
                    $producto = Producto::find($item['producto_id']);
                    $stockAntes = $producto->stock;

                    Producto::where('id', $item['producto_id'])
                        ->decrement('stock', $item['cantidad']);

                    $this->kardexService->registrarSalida(
                        $producto,
                        $item['cantidad'],
                        $stockAntes,
                        'cambio_aceite',
                        $automotor->placa
                    );
                }
                $cambioAceite->productos()->sync($syncData);
            });

            return redirect()->route('cambio-aceite.index')
                ->with('success', 'Cambio de aceite confirmado correctamente.');
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', 'No se pudo confirmar el cambio de aceite. Intente nuevamente.');
        }
    }

    /**
     * Actualiza datos del ticket sin confirmar (estado permanece 'pendiente').
     * Restaura stock anterior, decrementa con nuevas cantidades.
     * Redirige a cambio-aceite.confirmar del mismo ticket.
     */
    public function actualizarTicket(Request $request, CambioAceite $cambioAceite): RedirectResponse
    {
        $request->validate(array_merge($this->clienteRules(), [
            'trabajadores_ids' => ['required', 'array', 'min:1'],
            'trabajadores_ids.*' => ['integer', 'exists:trabajadores,id'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'productos.*.cantidad' => ['required', 'integer', 'min:1'],
            'productos.*.precio' => ['required', 'numeric', 'gt:0'],
            'productos.*.total' => ['required', 'numeric', 'min:0'],
        ]), [
            'productos.required' => 'Debe agregar al menos un producto al cambio de aceite.',
            'productos.min' => 'Debe agregar al menos un producto al cambio de aceite.',
            'trabajadores_ids.required' => 'Debe asignar al menos un trabajador al cambio de aceite.',
            'trabajadores_ids.min' => 'Debe asignar al menos un trabajador al cambio de aceite.',
        ]);

        $cambioAceite->load('productos');
        $oldStockMap = $cambioAceite->productos->pluck('pivot.cantidad', 'id')->toArray();

        foreach ($request->productos as $item) {
            $producto = Producto::findOrFail($item['producto_id']);
            $availableStock = $producto->stock + ($oldStockMap[$item['producto_id']] ?? 0);
            if ($item['cantidad'] > $availableStock) {
                return back()->withErrors([
                    "productos.{$item['producto_id']}.cantidad" => "La cantidad de \"{$producto->nombre}\" ({$item['cantidad']}) excede el stock disponible ({$availableStock}).",
                ])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($request, $cambioAceite) {
                $cliente = $this->clienteAutomotorService->obtenerCliente($request);
                $automotor = $this->clienteAutomotorService->obtenerAutomotor($request->placa, $cliente->id);

                $foto = $cambioAceite->foto;
                if ($request->hasFile('foto')) {
                    // Eliminar imagen anterior
                    if ($cambioAceite->foto) {
                        if (str_starts_with($cambioAceite->foto, 'http')) {
                            $parts = explode('/', $cambioAceite->foto);
                            $filename = end($parts);
                            $publicId = pathinfo($filename, PATHINFO_FILENAME);
                            try {
                                Cloudinary::uploadApi()->destroy($publicId);
                            } catch (\Exception $e) {
                            }
                        } elseif (Storage::disk('public')->exists($cambioAceite->foto)) {
                            Storage::disk('public')->delete($cambioAceite->foto);
                        }
                    }
                    $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
                    $foto = $result['secure_url'];
                }

                // Recalcular precio en servidor
                $precio = collect($request->productos)
                    ->sum(fn ($p) => $p['cantidad'] * $p['precio']);

                app(AuditService::class)->anotarAccion('actualizar ticket');

                $cambioAceite->update([
                    'cliente_id' => $cliente->id,
                    'automotor_id' => $automotor->placa,
                    'trabajador_id' => $request->trabajadores_ids[0],
                    'fecha' => $request->fecha,
                    'precio' => $precio,
                    'total' => $precio,
                    'descripcion' => $request->descripcion,
                    'foto' => $foto,
                    // estado permanece 'pendiente'
                ]);

                // Sincronizar trabajadores en pivote
                $cambioAceite->trabajadores()->sync($request->trabajadores_ids);

                // Sincronizar productos sin tocar stock (el pendiente no descuenta)
                $syncData = [];
                foreach ($request->productos as $item) {
                    $syncData[$item['producto_id']] = [
                        'cantidad' => $item['cantidad'],
                        'precio' => $item['precio'],
                        'total' => $item['total'],
                    ];
                }
                $cambioAceite->productos()->sync($syncData);
            });

            return redirect()->route('cambio-aceite.confirmar', $cambioAceite)
                ->with('success', 'Ticket actualizado correctamente.');
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', 'No se pudo actualizar el ticket. Intente nuevamente.');
        }
    }
}
