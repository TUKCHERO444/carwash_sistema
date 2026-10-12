<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Vehiculo;
use App\Services\VehiculoApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VehiculoController extends Controller
{
    /**
     * Regla de placa: 6-7 caracteres alfanuméricos y opcional guion medio.
     */
    private const PLACA_RULE = 'regex:/^[A-Z0-9-]{6,7}$/i';

    public function __construct(
        private VehiculoApiService $vehiculoApiService,
    ) {}

    /**
     * Solo letras (con acentos españoles y ñ) y espacios internos simples.
     * Rechaza números, símbolos y espacios dobles.
     */
    private const SOLO_LETRAS_RULE = 'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u';

    /**
     * Letras, números y espacios internos simples. Rechaza símbolos.
     */
    private const ALFANUMERICO_RULE = 'regex:/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ]+)*$/u';

    /**
     * Alfanumérico para campos que pueden contener guiones/códigos
     * (serie, motor) además de letras, números y espacios.
     */
    private const CODIGO_RULE = 'regex:/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ -]+$/u';

    /**
     * Display a listing of all vehículos ordered by placa.
     */
    public function index(): View
    {
        $vehiculos = Vehiculo::with('cliente')->orderBy('placa')->paginate(10);

        return view('vehiculos.index', compact('vehiculos'));
    }

    /**
     * Show the form for creating a new vehículo.
     */
    public function create(): View
    {
        $clientes = Cliente::orderBy('nombre')->get();

        return view('vehiculos.create', compact('clientes'));
    }

    /**
     * Store a newly created vehículo in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'placa' => ['required', 'string', 'max:7', 'unique:vehiculos,placa', self::ALFANUMERICO_RULE],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'marca' => ['nullable', 'string', 'max:100', self::ALFANUMERICO_RULE],
            'modelo' => ['nullable', 'string', 'max:100', self::ALFANUMERICO_RULE],
            'serie' => ['nullable', 'string', 'max:100', self::CODIGO_RULE],
            'color' => ['nullable', 'string', 'max:50', self::SOLO_LETRAS_RULE],
            'motor' => ['nullable', 'string', 'max:100', self::CODIGO_RULE],
            'vin' => ['nullable', 'string', 'max:100', 'unique:vehiculos,vin', self::CODIGO_RULE],
        ]);

        Vehiculo::create([
            'placa' => Vehiculo::normalizarPlaca($request->placa),
            'cliente_id' => $request->cliente_id,
            'marca' => $request->marca,
            'modelo' => $request->modelo,
            'serie' => $request->serie,
            'color' => $request->color,
            'motor' => $request->motor,
            'vin' => $request->vin,
        ]);

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo creado correctamente.');
    }

    /**
     * Show the form for editing an existing vehículo.
     */
    public function edit(Vehiculo $vehiculo): View
    {
        $clientes = Cliente::orderBy('nombre')->get();

        return view('vehiculos.edit', compact('vehiculo', 'clientes'));
    }

    /**
     * Update the specified vehículo in the database.
     */
    public function update(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        $request->validate([
            'placa' => ['required', 'string', 'max:7', 'unique:vehiculos,placa,'.$vehiculo->placa.',placa', self::ALFANUMERICO_RULE],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'marca' => ['nullable', 'string', 'max:100', self::ALFANUMERICO_RULE],
            'modelo' => ['nullable', 'string', 'max:100', self::ALFANUMERICO_RULE],
            'serie' => ['nullable', 'string', 'max:100', self::CODIGO_RULE],
            'color' => ['nullable', 'string', 'max:50', self::SOLO_LETRAS_RULE],
            'motor' => ['nullable', 'string', 'max:100', self::CODIGO_RULE],
            'vin' => ['nullable', 'string', 'max:100', 'unique:vehiculos,vin,'.$vehiculo->placa.',placa', self::CODIGO_RULE],
        ]);

        $vehiculo->update([
            'placa' => Vehiculo::normalizarPlaca($request->placa),
            'cliente_id' => $request->cliente_id,
            'marca' => $request->marca,
            'modelo' => $request->modelo,
            'serie' => $request->serie,
            'color' => $request->color,
            'motor' => $request->motor,
            'vin' => $request->vin,
        ]);

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo actualizado correctamente.');
    }

    /**
     * Remove the specified vehículo from the database.
     */
    public function destroy(Vehiculo $vehiculo): RedirectResponse
    {
        $vehiculo->delete();

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo eliminado correctamente.');
    }

    /**
     * Consulta los datos del vehículo por placa para autocompletar el formulario.
     *
     * Prioriza los datos locales si la placa ya está registrada; de lo contrario,
     * consulta la API vehicular. Si la API no responde, devuelve success true con
     * solo la placa (nada que autocompletar), sin bloquear el flujo.
     */
    public function consultarPlaca(Request $request): JsonResponse
    {
        $request->validate([
            'placa' => ['required', 'string', 'max:7', self::PLACA_RULE],
        ]);

        $placa = Vehiculo::normalizarPlaca($request->placa);

        $vehiculo = Vehiculo::where('placa', $placa)->first();

        if ($vehiculo) {
            return response()->json([
                'success' => true,
                'data' => [
                    'placa' => $vehiculo->placa,
                    'marca' => $vehiculo->marca,
                    'modelo' => $vehiculo->modelo,
                    'serie' => $vehiculo->serie,
                    'color' => $vehiculo->color,
                    'motor' => $vehiculo->motor,
                    'vin' => $vehiculo->vin,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge(
                ['placa' => $placa],
                $this->vehiculoApiService->buscarPorPlaca($placa)
            ),
        ]);
    }
}
