<?php

namespace App\Http\Controllers;

use App\Models\Lavado;
use App\Models\Servicio;
use App\Models\TipoVehiculo;
use App\Models\Trabajador;
use App\Services\AuditService;
use App\Services\CajaService;
use App\Services\ClienteVehiculoService;
use App\Services\PagoService;
use App\Services\TiempoAtencionService;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LavadoController extends Controller
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
     * Reglas validación del cliente que persisten en Cliente/Vehiculo.
     * Deben coincidir con el patrón estándar de ClienteController/VehiculoController.
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
        private ClienteVehiculoService $clienteVehiculoService,
        private PagoService $pagoService,
    ) {}

    public function index(): View
    {
        $lavados = Lavado::with(['cliente', 'vehiculo', 'tipoVehiculo', 'trabajadores', 'pagos.metodoPago'])
            ->pendientes()
            ->colaAtencion()
            ->paginate(10);

        return view('lavados.pendientes', compact('lavados'));
    }

    public function confirmados(): View
    {
        $lavados = Lavado::with(['cliente', 'vehiculo', 'tipoVehiculo', 'trabajadores', 'pagos.metodoPago'])
            ->confirmados()
            ->culminadosPorAtencion()
            ->paginate(10);

        return view('lavados.confirmados', compact('lavados'));
    }

    public function confirmar(Lavado $lavado): View|RedirectResponse
    {
        if ($lavado->estado === 'confirmado') {
            return redirect()->route('lavados.confirmados')
                ->with('info', 'Este lavado ya fue confirmado.');
        }

        $lavado->load(['cliente', 'vehiculo', 'tipoVehiculo', 'trabajadores', 'servicios']);
        $tiposVehiculo = TipoVehiculo::orderBy('nombre')->get();
        $trabajadores = Trabajador::where('estado', true)->orderBy('nombre')->get();
        $metodosPago = $this->pagoService->metodosActivos();
        $resumenPagos = $this->pagoService->resumen($lavado);

        $serviciosData = $lavado->servicios->map(fn ($s) => [
            'id' => $s->id,
            'nombre' => $s->nombre,
            'precio' => (float) $s->precio,
        ])->values()->all();

        $montosData = $resumenPagos['montos'];

        return view('lavados.confirmar', compact('lavado', 'tiposVehiculo', 'trabajadores', 'serviciosData', 'montosData', 'metodosPago', 'resumenPagos'));
    }

    public function procesarConfirmacion(Request $request, Lavado $lavado): RedirectResponse
    {
        $caja = $this->cajaService->getCajaActiva();
        if (! $caja) {
            return back()->with('error_caja', true);
        }

        $request->validate(array_merge($this->clienteRules(), $this->pagoService->reglas(), [
            'tipo_vehiculo_id' => ['required', 'integer', 'exists:tipos_vehiculo,id'],
            'fecha' => ['required', 'date'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'trabajadores_ids' => ['required', 'array', 'min:1'],
            'trabajadores_ids.*' => ['integer', 'exists:trabajadores,id'],
            'servicios' => ['nullable', 'array'],
            'servicios.*.servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'precio' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'gt:0'],
        ]), [
            'trabajadores_ids.required' => 'Debe asignar al menos un trabajador al lavado.',
            'trabajadores_ids.min' => 'Debe asignar al menos un trabajador al lavado.',
            'foto.image' => 'El archivo debe ser una imagen válida.',
            'foto.max' => 'La imagen no puede superar 5 MB.',
        ]);

        $lineasPago = $this->pagoService->validar($request, (float) $request->total);

        try {
            DB::transaction(function () use ($request, $caja, $lavado, $lineasPago) {
                $cliente = $this->clienteVehiculoService->obtenerCliente($request);
                $vehiculo = $this->clienteVehiculoService->obtenerVehiculo($request->placa, $cliente->id, true);

                $foto = $lavado->foto;
                if ($request->hasFile('foto')) {
                    // Eliminar imagen anterior
                    if ($lavado->foto) {
                        if (str_starts_with($lavado->foto, 'http')) {
                            $parts = explode('/', $lavado->foto);
                            $filename = end($parts);
                            $publicId = pathinfo($filename, PATHINFO_FILENAME);
                            try {
                                Cloudinary::uploadApi()->destroy($publicId);
                            } catch (\Exception $e) {
                            }
                        } elseif (Storage::disk('public')->exists($lavado->foto)) {
                            Storage::disk('public')->delete($lavado->foto);
                        }
                    }
                    $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
                    $foto = $result['secure_url'];
                }

                app(AuditService::class)->anotarAccion('confirmar');

                // Cierre de la orden de atención: el pago confirma el fin
                // y persiste la duración en minutos.
                $tiempos = app(TiempoAtencionService::class);
                $inicioAtencion = $lavado->atencion_inicio_at ?? $lavado->created_at ?? now();
                $cierre = $tiempos->datosFinalizacion($inicioAtencion);

                $lavado->update([
                    'cliente_id' => $cliente->id,
                    'vehiculo_id' => $vehiculo->placa,
                    'tipo_vehiculo_id' => $request->tipo_vehiculo_id,
                    'fecha' => $request->fecha,
                    'precio' => $request->precio,
                    'total' => $request->total,
                    'foto' => $foto,
                    'estado' => 'confirmado',
                    'caja_id' => $caja->id,
                    'atencion_inicio_at' => $inicioAtencion,
                    'atencion_fin_at' => $cierre['atencion_fin_at'],
                    'tiempo_atencion_minutos' => $cierre['tiempo_atencion_minutos'],
                ]);

                $this->pagoService->guardarLineas($lavado, $lineasPago, auth()->id(), $caja->id);

                $lavado->trabajadores()->sync($request->trabajadores_ids);
                $servicioIds = collect($request->servicios ?? [])->pluck('servicio_id')->filter()->all();
                $lavado->servicios()->sync($servicioIds);
            });

            return redirect()->route('lavados.index')
                ->with('success', 'Lavado confirmado correctamente.');
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', 'No se pudo confirmar el lavado. Intente nuevamente.');
        }
    }

    public function create(): View
    {
        $tiposVehiculo = TipoVehiculo::all();
        $trabajadores = Trabajador::where('estado', true)->get();

        return view('lavados.create', compact('tiposVehiculo', 'trabajadores'));
    }

    public function buscarServicios(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $servicios = Servicio::where('nombre', 'like', '%'.$q.'%')
            ->select('id', 'nombre', 'precio')
            ->limit(10)
            ->get();

        return response()->json($servicios);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(array_merge($this->clienteRules(), [
            'tipo_vehiculo_id' => ['required', 'integer', 'exists:tipos_vehiculo,id'],
            'fecha' => ['required', 'date'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'trabajadores_ids' => ['required', 'array', 'min:1'],
            'trabajadores_ids.*' => ['integer', 'exists:trabajadores,id'],
            'servicios' => ['nullable', 'array'],
            'servicios.*.servicio_id' => ['required', 'integer', 'exists:servicios,id'],
        ]), [
            'trabajadores_ids.required' => 'Debe asignar al menos un trabajador al lavado.',
            'trabajadores_ids.min' => 'Debe asignar al menos un trabajador al lavado.',
            'foto.image' => 'El archivo debe ser una imagen válida.',
            'foto.max' => 'La imagen no puede superar 5 MB.',
        ]);

        $lavado = null;
        try {
            DB::transaction(function () use ($request, &$lavado) {
                $cliente = $this->clienteVehiculoService->obtenerCliente($request);
                $vehiculo = $this->clienteVehiculoService->obtenerVehiculo($request->placa, $cliente->id);

                $foto = null;
                if ($request->hasFile('foto')) {
                    $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
                    $foto = $result['secure_url'];
                }

                // Calcular precio server-side: precio tipo vehículo + suma precios servicios
                $tipoVehiculo = TipoVehiculo::findOrFail($request->tipo_vehiculo_id);
                $servicioIds = collect($request->servicios ?? [])->pluck('servicio_id')->filter()->all();
                $sumServicios = Servicio::whereIn('id', $servicioIds)->sum('precio');
                $precio = $tipoVehiculo->precio + $sumServicios;

                $lavado = Lavado::create([
                    'cliente_id' => $cliente->id,
                    'vehiculo_id' => $vehiculo->placa,
                    'tipo_vehiculo_id' => $request->tipo_vehiculo_id,
                    'fecha' => $request->fecha,
                    'precio' => $precio,
                    'total' => $precio,
                    'foto' => $foto,
                    'user_id' => auth()->id(),
                    'estado' => 'pendiente',
                    'atencion_inicio_at' => now(),
                ]);

                $lavado->trabajadores()->sync($request->trabajadores_ids);
                $lavado->servicios()->sync($servicioIds);
            });

            return redirect()->route('lavados.index')
                ->with('success', 'Lavado registrado correctamente.');
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', 'No se pudo registrar el lavado. Intente nuevamente.');
        }
    }

    public function show(Lavado $lavado): View
    {
        $lavado->load(['cliente', 'vehiculo', 'tipoVehiculo', 'user', 'trabajadores', 'servicios', 'pagos.metodoPago']);

        return view('lavados.show', compact('lavado'));
    }

    public function edit(Lavado $lavado): View
    {
        $lavado->load(['cliente', 'trabajadores', 'servicios']);
        $tiposVehiculo = TipoVehiculo::all();
        $trabajadores = Trabajador::where('estado', true)->get();

        $serviciosExistentes = $lavado->servicios->map(fn ($s) => [
            'id' => $s->id,
            'nombre' => $s->nombre,
            'precio' => (float) $s->precio,
        ])->values()->all();

        $lavadoMontos = $this->pagoService->resumen($lavado)['montos'];
        $resumenPagos = $this->pagoService->resumen($lavado);
        $metodosPago = $this->pagoService->metodosActivos();

        return view('lavados.edit', compact('lavado', 'tiposVehiculo', 'trabajadores', 'serviciosExistentes', 'lavadoMontos', 'metodosPago', 'resumenPagos'));
    }

    public function update(Request $request, Lavado $lavado): RedirectResponse
    {
        $request->validate(array_merge($this->clienteRules(), $this->pagoService->reglas(), [
            'tipo_vehiculo_id' => ['required', 'integer', 'exists:tipos_vehiculo,id'],
            'fecha' => ['required', 'date'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'trabajadores_ids' => ['required', 'array', 'min:1'],
            'trabajadores_ids.*' => ['integer', 'exists:trabajadores,id'],
            'servicios' => ['nullable', 'array'],
            'servicios.*.servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'precio' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'gt:0'],
        ]), [
            'trabajadores_ids.required' => 'Debe asignar al menos un trabajador al lavado.',
            'trabajadores_ids.min' => 'Debe asignar al menos un trabajador al lavado.',
            'foto.image' => 'El archivo debe ser una imagen válida.',
            'foto.max' => 'La imagen no puede superar 5 MB.',
        ]);

        try {
            $lineasPago = $this->pagoService->validar($request, (float) $request->total);

            DB::transaction(function () use ($request, $lavado, $lineasPago) {
                $cliente = $this->clienteVehiculoService->obtenerCliente($request);
                $vehiculo = $this->clienteVehiculoService->obtenerVehiculo($request->placa, $cliente->id);

                $foto = $lavado->foto;
                if ($request->hasFile('foto')) {
                    // Eliminar imagen anterior
                    if ($lavado->foto) {
                        if (str_starts_with($lavado->foto, 'http')) {
                            $parts = explode('/', $lavado->foto);
                            $filename = end($parts);
                            $publicId = pathinfo($filename, PATHINFO_FILENAME);
                            try {
                                Cloudinary::uploadApi()->destroy($publicId);
                            } catch (\Exception $e) {
                            }
                        } elseif (Storage::disk('public')->exists($lavado->foto)) {
                            Storage::disk('public')->delete($lavado->foto);
                        }
                    }
                    $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
                    $foto = $result['secure_url'];
                }

                $lavado->update([
                    'cliente_id' => $cliente->id,
                    'vehiculo_id' => $vehiculo->placa,
                    'tipo_vehiculo_id' => $request->tipo_vehiculo_id,
                    'fecha' => $request->fecha,
                    'precio' => $request->precio,
                    'total' => $request->total,
                    'foto' => $foto,
                ]);

                $this->pagoService->guardarLineas($lavado, $lineasPago, auth()->id(), $lavado->caja_id);

                $lavado->trabajadores()->sync($request->trabajadores_ids);
                $servicioIds = collect($request->servicios ?? [])->pluck('servicio_id')->filter()->all();
                $lavado->servicios()->sync($servicioIds);
            });

            if ($lavado->estado === 'pendiente') {
                return redirect()->route('lavados.confirmar', $lavado)
                    ->with('success', 'Lavado actualizado correctamente.');
            }

            return redirect()->route('lavados.show', $lavado)
                ->with('success', 'Lavado actualizado correctamente.');
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', 'No se pudo actualizar el lavado. Intente nuevamente.');
        }
    }

    public function destroy(Lavado $lavado): RedirectResponse
    {
        try {
            if ($lavado->foto) {
                if (str_starts_with($lavado->foto, 'http')) {
                    $parts = explode('/', $lavado->foto);
                    $filename = end($parts);
                    $publicId = pathinfo($filename, PATHINFO_FILENAME);
                    try {
                        Cloudinary::uploadApi()->destroy($publicId);
                    } catch (\Exception $e) {
                    }
                } elseif (Storage::disk('public')->exists($lavado->foto)) {
                    Storage::disk('public')->delete($lavado->foto);
                }
            }
            $lavado->pagos()->delete();
            $lavado->delete();

            return redirect()->route('lavados.index')
                ->with('success', 'Lavado eliminado correctamente.');
        } catch (\Throwable $e) {
            return redirect()->route('lavados.index')
                ->with('error', 'No se pudo eliminar el lavado. Intente nuevamente.');
        }
    }

    public function ticket(Lavado $lavado): View
    {
        $lavado->load(['cliente', 'vehiculo', 'tipoVehiculo', 'user', 'trabajadores', 'servicios', 'pagos.metodoPago']);

        return view('lavados.ticket', compact('lavado'));
    }
}
