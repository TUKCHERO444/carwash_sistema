<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Ingreso;
use App\Models\Servicio;
use App\Models\Trabajador;
use App\Models\Vehiculo;
use App\Services\CajaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class IngresoController extends Controller
{
    public function __construct(private CajaService $cajaService) {}
    public function index(): View
    {
        $ingresos = Ingreso::with(['cliente', 'vehiculo', 'trabajadores'])
            ->pendientes()
            ->orderBy('fecha', 'desc')
            ->paginate(10);

        return view('ingresos.pendientes', compact('ingresos'));
    }

    public function confirmados(): View
    {
        $ingresos = Ingreso::with(['cliente', 'vehiculo', 'trabajadores'])
            ->confirmados()
            ->orderBy('fecha', 'desc')
            ->paginate(10);

        return view('ingresos.confirmados', compact('ingresos'));
    }

    public function confirmar(Ingreso $ingreso): View|RedirectResponse
    {
        if ($ingreso->estado === 'confirmado') {
            return redirect()->route('ingresos.confirmados')
                ->with('info', 'Este ingreso ya fue confirmado.');
        }

        $ingreso->load(['cliente', 'vehiculo', 'trabajadores', 'servicios']);
        $vehiculos    = Vehiculo::orderBy('nombre')->get();
        $trabajadores = Trabajador::where('estado', true)->orderBy('nombre')->get();

        $serviciosData = $ingreso->servicios->map(fn ($s) => [
            'id'     => $s->id,
            'nombre' => $s->nombre,
            'precio' => (float) $s->precio,
        ])->values()->all();

        $montosData = [
            'efectivo' => $ingreso->monto_efectivo,
            'yape'     => $ingreso->monto_yape,
            'izipay'   => $ingreso->monto_izipay,
        ];

        return view('ingresos.confirmar', compact('ingreso', 'vehiculos', 'trabajadores', 'serviciosData', 'montosData'));
    }

    public function procesarConfirmacion(Request $request, Ingreso $ingreso): RedirectResponse
    {
        $caja = $this->cajaService->getCajaActiva();
        if (!$caja) {
            return back()->with('error_caja', true);
        }

        $request->validate([
            'vehiculo_id'              => ['required', 'integer', 'exists:vehiculos,id'],
            'placa'                    => ['required', 'string', 'max:7'],
            'nombre'                   => ['nullable', 'string', 'max:100'],
            'telefono'                 => ['nullable', 'string', 'max:20'],
            'dni'      => ['nullable', 'string', 'max:8'],
            'fecha'                    => ['required', 'date'],
            'foto'                     => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'trabajadores_ids'         => ['required', 'array', 'min:1'],
            'trabajadores_ids.*'       => ['integer', 'exists:trabajadores,id'],
            'servicios'                => ['nullable', 'array'],
            'servicios.*.servicio_id'  => ['required', 'integer', 'exists:servicios,id'],
            'precio'                   => ['required', 'numeric', 'min:0'],
            'total'                    => ['required', 'numeric', 'gt:0'],
            'metodo_pago'              => ['required', 'in:efectivo,yape,izipay,mixto'],
            'monto_efectivo'           => ['nullable', 'numeric', 'min:0'],
            'monto_yape'               => ['nullable', 'numeric', 'min:0'],
            'monto_izipay'             => ['nullable', 'numeric', 'min:0'],
        ], [
            'trabajadores_ids.required' => 'Debe asignar al menos un trabajador al ingreso.',
            'trabajadores_ids.min'      => 'Debe asignar al menos un trabajador al ingreso.',
            'foto.image'               => 'El archivo debe ser una imagen válida.',
            'foto.max'                 => 'La imagen no puede superar 5 MB.',
        ]);

        try {
            DB::transaction(function () use ($request, $caja, $ingreso) {
                $cliente = Cliente::updateOrCreate(
                    ['placa' => $request->placa],
                    [
                        'nombre'   => $request->nombre,
                        'telefono' => $request->telefono,
                        'dni'      => $request->dni,
                    ]
                );

                $foto = $ingreso->foto;
                if ($request->hasFile('foto')) {
                    $nuevaFoto = Storage::disk('public')->put('ingresos', $request->file('foto'));
                    if ($ingreso->foto) {
                        Storage::disk('public')->delete($ingreso->foto);
                    }
                    $foto = $nuevaFoto;
                }

                $ingreso->update([
                    'cliente_id'     => $cliente->id,
                    'vehiculo_id'    => $request->vehiculo_id,
                    'fecha'          => $request->fecha,
                    'precio'         => $request->precio,
                    'total'          => $request->total,
                    'foto'           => $foto,
                    'metodo_pago'    => $request->metodo_pago,
                    'monto_efectivo' => $request->metodo_pago === 'mixto' ? $request->monto_efectivo : null,
                    'monto_yape'     => $request->metodo_pago === 'mixto' ? $request->monto_yape     : null,
                    'monto_izipay'   => $request->metodo_pago === 'mixto' ? $request->monto_izipay   : null,
                    'estado'         => 'confirmado',
                    'caja_id'        => $caja->id,
                ]);

                $ingreso->trabajadores()->sync($request->trabajadores_ids);
                $servicioIds = collect($request->servicios ?? [])->pluck('servicio_id')->filter()->all();
                $ingreso->servicios()->sync($servicioIds);
            });

            return redirect()->route('ingresos.index')
                ->with('success', 'Ingreso confirmado correctamente.');
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', 'No se pudo confirmar el ingreso. Intente nuevamente.');
        }
    }

    public function create(): View
    {
        $vehiculos    = Vehiculo::all();
        $trabajadores = Trabajador::where('estado', true)->get();

        return view('ingresos.create', compact('vehiculos', 'trabajadores'));
    }

    public function buscarServicios(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = $request->get('q', '');

        $servicios = Servicio::where('nombre', 'like', '%' . $q . '%')
            ->select('id', 'nombre', 'precio')
            ->limit(10)
            ->get();

        return response()->json($servicios);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'vehiculo_id'             => ['required', 'integer', 'exists:vehiculos,id'],
            'placa'                   => ['required', 'string', 'max:7'],
            'nombre'                  => ['nullable', 'string', 'max:100'],
            'telefono'                => ['nullable', 'string', 'max:20'],
            'dni'                     => ['nullable', 'string', 'max:8'],
            'fecha'                   => ['required', 'date'],
            'foto'                    => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'trabajadores_ids'        => ['required', 'array', 'min:1'],
            'trabajadores_ids.*'      => ['integer', 'exists:trabajadores,id'],
            'servicios'               => ['nullable', 'array'],
            'servicios.*.servicio_id' => ['required', 'integer', 'exists:servicios,id'],
        ], [
            'trabajadores_ids.required' => 'Debe asignar al menos un trabajador al ingreso.',
            'trabajadores_ids.min'      => 'Debe asignar al menos un trabajador al ingreso.',
            'foto.image'               => 'El archivo debe ser una imagen válida.',
            'foto.max'                 => 'La imagen no puede superar 5 MB.',
        ]);

        $ingreso = null;
        try {
            DB::transaction(function () use ($request, &$ingreso) {
                $cliente = Cliente::updateOrCreate(
                    ['placa' => $request->placa],
                    [
                        'nombre'   => $request->nombre,
                        'telefono' => $request->telefono,
                        'dni'      => $request->dni,
                    ]
                );

                $foto = null;
                if ($request->hasFile('foto')) {
                    $foto = Storage::disk('public')->put('ingresos', $request->file('foto'));
                }

                // Calculate precio server-side: vehiculo price + sum of selected servicios prices
                $vehiculo        = Vehiculo::findOrFail($request->vehiculo_id);
                $servicioIds     = collect($request->servicios ?? [])->pluck('servicio_id')->filter()->all();
                $sumServicios    = Servicio::whereIn('id', $servicioIds)->sum('precio');
                $precio          = $vehiculo->precio + $sumServicios;

                $ingreso = Ingreso::create([
                    'cliente_id'  => $cliente->id,
                    'vehiculo_id' => $request->vehiculo_id,
                    'fecha'       => $request->fecha,
                    'precio'      => $precio,
                    'total'       => $precio,
                    'foto'        => $foto,
                    'user_id'     => auth()->id(),
                    'estado'      => 'pendiente',
                ]);

                $ingreso->trabajadores()->sync($request->trabajadores_ids);
                $ingreso->servicios()->sync($servicioIds);
            });

            return redirect()->route('ingresos.index')
                ->with('success', 'Ingreso registrado correctamente.');
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', 'No se pudo registrar el ingreso. Intente nuevamente.');
        }
    }

    public function show(Ingreso $ingreso): View
    {
        $ingreso->load(['cliente', 'vehiculo', 'user', 'trabajadores', 'servicios']);

        return view('ingresos.show', compact('ingreso'));
    }

    public function edit(Ingreso $ingreso): View
    {
        $ingreso->load(['cliente', 'trabajadores', 'servicios']);
        $vehiculos    = Vehiculo::all();
        $trabajadores = Trabajador::where('estado', true)->get();

        $serviciosExistentes = $ingreso->servicios->map(fn ($s) => [
            'id'     => $s->id,
            'nombre' => $s->nombre,
            'precio' => (float) $s->precio,
        ])->values()->all();

        $ingresoMontos = [
            'efectivo' => $ingreso->monto_efectivo,
            'yape'     => $ingreso->monto_yape,
            'izipay'   => $ingreso->monto_izipay,
        ];

        return view('ingresos.edit', compact('ingreso', 'vehiculos', 'trabajadores', 'serviciosExistentes', 'ingresoMontos'));
    }

    public function update(Request $request, Ingreso $ingreso): RedirectResponse
    {
        $request->validate([
            'vehiculo_id'              => ['required', 'integer', 'exists:vehiculos,id'],
            'placa'                    => ['required', 'string', 'max:7'],
            'nombre'                   => ['nullable', 'string', 'max:100'],
            'telefono'                 => ['nullable', 'string', 'max:20'],
            'dni'                      => ['nullable', 'string', 'max:8'],
            'fecha'                    => ['required', 'date'],
            'foto'                     => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'trabajadores_ids'         => ['required', 'array', 'min:1'],
            'trabajadores_ids.*'       => ['integer', 'exists:trabajadores,id'],
            'servicios'                => ['nullable', 'array'],
            'servicios.*.servicio_id'  => ['required', 'integer', 'exists:servicios,id'],
            'precio'                   => ['required', 'numeric', 'min:0'],
            'total'                    => ['required', 'numeric', 'gt:0'],
            'metodo_pago'              => ['required', 'in:efectivo,yape,izipay,mixto'],
            'monto_efectivo'           => ['nullable', 'numeric', 'min:0'],
            'monto_yape'               => ['nullable', 'numeric', 'min:0'],
            'monto_izipay'             => ['nullable', 'numeric', 'min:0'],
        ], [
            'trabajadores_ids.required' => 'Debe asignar al menos un trabajador al ingreso.',
            'trabajadores_ids.min'      => 'Debe asignar al menos un trabajador al ingreso.',
            'foto.image'               => 'El archivo debe ser una imagen válida.',
            'foto.max'                 => 'La imagen no puede superar 5 MB.',
        ]);

        try {
            DB::transaction(function () use ($request, $ingreso) {
                // Update or create client by plate
                $cliente = Cliente::updateOrCreate(
                    ['placa' => $request->placa],
                    [
                        'nombre'   => $request->nombre,
                        'telefono' => $request->telefono,
                        'dni'      => $request->dni,
                    ]
                );

                $foto = $ingreso->foto;
                if ($request->hasFile('foto')) {
                    // Store new photo first, then delete old one
                    $nuevaFoto = Storage::disk('public')->put('ingresos', $request->file('foto'));
                    if ($ingreso->foto) {
                        Storage::disk('public')->delete($ingreso->foto);
                    }
                    $foto = $nuevaFoto;
                }

                $ingreso->update([
                    'cliente_id'     => $cliente->id,
                    'vehiculo_id'    => $request->vehiculo_id,
                    'fecha'          => $request->fecha,
                    'precio'         => $request->precio,
                    'total'          => $request->total,
                    'foto'           => $foto,
                    'metodo_pago'    => $request->metodo_pago,
                    'monto_efectivo' => $request->metodo_pago === 'mixto' ? $request->monto_efectivo : null,
                    'monto_yape'     => $request->metodo_pago === 'mixto' ? $request->monto_yape     : null,
                    'monto_izipay'   => $request->metodo_pago === 'mixto' ? $request->monto_izipay   : null,
                ]);

                $ingreso->trabajadores()->sync($request->trabajadores_ids);
                $servicioIds = collect($request->servicios ?? [])->pluck('servicio_id')->filter()->all();
                $ingreso->servicios()->sync($servicioIds);
            });

            if ($ingreso->estado === 'pendiente') {
                return redirect()->route('ingresos.confirmar', $ingreso)
                    ->with('success', 'Ingreso actualizado correctamente.');
            }
            return redirect()->route('ingresos.show', $ingreso)
                ->with('success', 'Ingreso actualizado correctamente.');
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', 'No se pudo actualizar el ingreso. Intente nuevamente.');
        }
    }

    public function destroy(Ingreso $ingreso): RedirectResponse
    {
        try {
            if ($ingreso->foto) {
                Storage::disk('public')->delete($ingreso->foto);
            }
            $ingreso->delete();

            return redirect()->route('ingresos.index')
                ->with('success', 'Ingreso eliminado correctamente.');
        } catch (\Throwable $e) {
            return redirect()->route('ingresos.index')
                ->with('error', 'No se pudo eliminar el ingreso. Intente nuevamente.');
        }
    }

    public function ticket(Ingreso $ingreso): View
    {
        $ingreso->load(['cliente', 'vehiculo', 'user', 'trabajadores', 'servicios']);

        return view('ingresos.ticket', compact('ingreso'));
    }
}
