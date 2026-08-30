<?php

namespace App\Http\Controllers;

use App\Models\Lavado;
use App\Models\Servicio;
use App\Models\Trabajador;
use App\Models\Vehiculo;
use App\Services\AuditService;
use App\Services\CajaService;
use App\Services\ClienteAutomotorService;
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
    ) {}

    public function index(): View
    {
        $lavados = Lavado::with(['cliente', 'vehiculo', 'trabajadores'])
            ->pendientes()
            ->orderBy('fecha', 'desc')
            ->paginate(10);

        return view('lavados.pendientes', compact('lavados'));
    }

    public function confirmados(): View
    {
        $lavados = Lavado::with(['cliente', 'vehiculo', 'trabajadores'])
            ->confirmados()
            ->orderBy('fecha', 'desc')
            ->paginate(10);

        return view('lavados.confirmados', compact('lavados'));
    }

    public function confirmar(Lavado $lavado): View|RedirectResponse
    {
        if ($lavado->estado === 'confirmado') {
            return redirect()->route('lavados.confirmados')
                ->with('info', 'Este lavado ya fue confirmado.');
        }

        $lavado->load(['cliente', 'vehiculo', 'trabajadores', 'servicios']);
        $vehiculos = Vehiculo::orderBy('nombre')->get();
        $trabajadores = Trabajador::where('estado', true)->orderBy('nombre')->get();

        $serviciosData = $lavado->servicios->map(fn ($s) => [
            'id' => $s->id,
            'nombre' => $s->nombre,
            'precio' => (float) $s->precio,
        ])->values()->all();

        $montosData = [
            'efectivo' => $lavado->monto_efectivo,
            'yape' => $lavado->monto_yape,
            'izipay' => $lavado->monto_izipay,
        ];

        return view('lavados.confirmar', compact('lavado', 'vehiculos', 'trabajadores', 'serviciosData', 'montosData'));
    }

    public function procesarConfirmacion(Request $request, Lavado $lavado): RedirectResponse
    {
        $caja = $this->cajaService->getCajaActiva();
        if (! $caja) {
            return back()->with('error_caja', true);
        }

        $request->validate(array_merge($this->clienteRules(), [
            'vehiculo_id' => ['required', 'integer', 'exists:vehiculos,id'],
            'fecha' => ['required', 'date'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'trabajadores_ids' => ['required', 'array', 'min:1'],
            'trabajadores_ids.*' => ['integer', 'exists:trabajadores,id'],
            'servicios' => ['nullable', 'array'],
            'servicios.*.servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'precio' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'gt:0'],
            'metodo_pago' => ['required', 'in:efectivo,yape,izipay,mixto'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_yape' => ['nullable', 'numeric', 'min:0'],
            'monto_izipay' => ['nullable', 'numeric', 'min:0'],
        ]), [
            'trabajadores_ids.required' => 'Debe asignar al menos un trabajador al lavado.',
            'trabajadores_ids.min' => 'Debe asignar al menos un trabajador al lavado.',
            'foto.image' => 'El archivo debe ser una imagen válida.',
            'foto.max' => 'La imagen no puede superar 5 MB.',
        ]);

        try {
            DB::transaction(function () use ($request, $caja, $lavado) {
                $cliente = $this->clienteAutomotorService->obtenerCliente($request);
                $automotor = $this->clienteAutomotorService->obtenerAutomotor($request->placa, $cliente->id, true);

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

                $lavado->update([
                    'cliente_id' => $cliente->id,
                    'automotor_id' => $automotor->placa,
                    'vehiculo_id' => $request->vehiculo_id,
                    'fecha' => $request->fecha,
                    'precio' => $request->precio,
                    'total' => $request->total,
                    'foto' => $foto,
                    'metodo_pago' => $request->metodo_pago,
                    'monto_efectivo' => $request->metodo_pago === 'mixto' ? $request->monto_efectivo : null,
                    'monto_yape' => $request->metodo_pago === 'mixto' ? $request->monto_yape : null,
                    'monto_izipay' => $request->metodo_pago === 'mixto' ? $request->monto_izipay : null,
                    'estado' => 'confirmado',
                    'caja_id' => $caja->id,
                ]);

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
        $vehiculos = Vehiculo::all();
        $trabajadores = Trabajador::where('estado', true)->get();

        return view('lavados.create', compact('vehiculos', 'trabajadores'));
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
            'vehiculo_id' => ['required', 'integer', 'exists:vehiculos,id'],
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
                $cliente = $this->clienteAutomotorService->obtenerCliente($request);
                $automotor = $this->clienteAutomotorService->obtenerAutomotor($request->placa, $cliente->id);

                $foto = null;
                if ($request->hasFile('foto')) {
                    $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
                    $foto = $result['secure_url'];
                }

                // Calcular precio server-side: precio vehículo + suma precios servicios
                $vehiculo = Vehiculo::findOrFail($request->vehiculo_id);
                $servicioIds = collect($request->servicios ?? [])->pluck('servicio_id')->filter()->all();
                $sumServicios = Servicio::whereIn('id', $servicioIds)->sum('precio');
                $precio = $vehiculo->precio + $sumServicios;

                $lavado = Lavado::create([
                    'cliente_id' => $cliente->id,
                    'automotor_id' => $automotor->placa,
                    'vehiculo_id' => $request->vehiculo_id,
                    'fecha' => $request->fecha,
                    'precio' => $precio,
                    'total' => $precio,
                    'foto' => $foto,
                    'user_id' => auth()->id(),
                    'estado' => 'pendiente',
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
        $lavado->load(['cliente', 'vehiculo', 'user', 'trabajadores', 'servicios']);

        return view('lavados.show', compact('lavado'));
    }

    public function edit(Lavado $lavado): View
    {
        $lavado->load(['cliente', 'trabajadores', 'servicios']);
        $vehiculos = Vehiculo::all();
        $trabajadores = Trabajador::where('estado', true)->get();

        $serviciosExistentes = $lavado->servicios->map(fn ($s) => [
            'id' => $s->id,
            'nombre' => $s->nombre,
            'precio' => (float) $s->precio,
        ])->values()->all();

        $lavadoMontos = [
            'efectivo' => $lavado->monto_efectivo,
            'yape' => $lavado->monto_yape,
            'izipay' => $lavado->monto_izipay,
        ];

        return view('lavados.edit', compact('lavado', 'vehiculos', 'trabajadores', 'serviciosExistentes', 'lavadoMontos'));
    }

    public function update(Request $request, Lavado $lavado): RedirectResponse
    {
        $request->validate(array_merge($this->clienteRules(), [
            'vehiculo_id' => ['required', 'integer', 'exists:vehiculos,id'],
            'fecha' => ['required', 'date'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'trabajadores_ids' => ['required', 'array', 'min:1'],
            'trabajadores_ids.*' => ['integer', 'exists:trabajadores,id'],
            'servicios' => ['nullable', 'array'],
            'servicios.*.servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'precio' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'gt:0'],
            'metodo_pago' => ['required', 'in:efectivo,yape,izipay,mixto'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_yape' => ['nullable', 'numeric', 'min:0'],
            'monto_izipay' => ['nullable', 'numeric', 'min:0'],
        ]), [
            'trabajadores_ids.required' => 'Debe asignar al menos un trabajador al lavado.',
            'trabajadores_ids.min' => 'Debe asignar al menos un trabajador al lavado.',
            'foto.image' => 'El archivo debe ser una imagen válida.',
            'foto.max' => 'La imagen no puede superar 5 MB.',
        ]);

        try {
            DB::transaction(function () use ($request, $lavado) {
                $cliente = $this->clienteAutomotorService->obtenerCliente($request);
                $automotor = $this->clienteAutomotorService->obtenerAutomotor($request->placa, $cliente->id);

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
                    'automotor_id' => $automotor->placa,
                    'vehiculo_id' => $request->vehiculo_id,
                    'fecha' => $request->fecha,
                    'precio' => $request->precio,
                    'total' => $request->total,
                    'foto' => $foto,
                    'metodo_pago' => $request->metodo_pago,
                    'monto_efectivo' => $request->metodo_pago === 'mixto' ? $request->monto_efectivo : null,
                    'monto_yape' => $request->metodo_pago === 'mixto' ? $request->monto_yape : null,
                    'monto_izipay' => $request->metodo_pago === 'mixto' ? $request->monto_izipay : null,
                ]);

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
        $lavado->load(['cliente', 'vehiculo', 'user', 'trabajadores', 'servicios']);

        return view('lavados.ticket', compact('lavado'));
    }
}
