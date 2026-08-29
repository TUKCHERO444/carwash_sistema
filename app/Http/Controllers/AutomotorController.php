<?php

namespace App\Http\Controllers;

use App\Models\Automotor;
use App\Models\Cliente;
use App\Services\AutomotorApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AutomotorController extends Controller
{
    /**
     * Regla de placa: 6-7 caracteres alfanuméricos y opcional guion medio.
     */
    private const PLACA_RULE = 'regex:/^[A-Z0-9-]{6,7}$/i';

    public function __construct(
        private AutomotorApiService $automotorApiService,
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
     * Display a listing of all automotores ordered by placa.
     */
    public function index(): View
    {
        $automotores = Automotor::with('cliente')->orderBy('placa')->paginate(10);

        return view('automotores.index', compact('automotores'));
    }

    /**
     * Show the form for creating a new automotor.
     */
    public function create(): View
    {
        $clientes = Cliente::orderBy('nombre')->get();

        return view('automotores.create', compact('clientes'));
    }

    /**
     * Store a newly created automotor in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'placa' => ['required', 'string', 'max:7', 'unique:automotores,placa', self::ALFANUMERICO_RULE],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'marca' => ['nullable', 'string', 'max:100', self::ALFANUMERICO_RULE],
            'modelo' => ['nullable', 'string', 'max:100', self::ALFANUMERICO_RULE],
            'serie' => ['nullable', 'string', 'max:100', self::CODIGO_RULE],
            'color' => ['nullable', 'string', 'max:50', self::SOLO_LETRAS_RULE],
            'motor' => ['nullable', 'string', 'max:100', self::CODIGO_RULE],
            'vin' => ['nullable', 'string', 'max:100', self::CODIGO_RULE],
        ]);

        Automotor::create([
            'placa' => Automotor::normalizarPlaca($request->placa),
            'cliente_id' => $request->cliente_id,
            'marca' => $request->marca,
            'modelo' => $request->modelo,
            'serie' => $request->serie,
            'color' => $request->color,
            'motor' => $request->motor,
            'vin' => $request->vin,
        ]);

        return redirect()->route('automotores.index')
            ->with('success', 'Automotor creado correctamente.');
    }

    /**
     * Show the form for editing an existing automotor.
     */
    public function edit(Automotor $automotor): View
    {
        $clientes = Cliente::orderBy('nombre')->get();

        return view('automotores.edit', compact('automotor', 'clientes'));
    }

    /**
     * Update the specified automotor in the database.
     */
    public function update(Request $request, Automotor $automotor): RedirectResponse
    {
        $request->validate([
            'placa' => ['required', 'string', 'max:7', 'unique:automotores,placa,'.$automotor->placa.',placa', self::ALFANUMERICO_RULE],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'marca' => ['nullable', 'string', 'max:100', self::ALFANUMERICO_RULE],
            'modelo' => ['nullable', 'string', 'max:100', self::ALFANUMERICO_RULE],
            'serie' => ['nullable', 'string', 'max:100', self::CODIGO_RULE],
            'color' => ['nullable', 'string', 'max:50', self::SOLO_LETRAS_RULE],
            'motor' => ['nullable', 'string', 'max:100', self::CODIGO_RULE],
            'vin' => ['nullable', 'string', 'max:100', self::CODIGO_RULE],
        ]);

        $automotor->update([
            'placa' => Automotor::normalizarPlaca($request->placa),
            'cliente_id' => $request->cliente_id,
            'marca' => $request->marca,
            'modelo' => $request->modelo,
            'serie' => $request->serie,
            'color' => $request->color,
            'motor' => $request->motor,
            'vin' => $request->vin,
        ]);

        return redirect()->route('automotores.index')
            ->with('success', 'Automotor actualizado correctamente.');
    }

    /**
     * Remove the specified automotor from the database.
     */
    public function destroy(Automotor $automotor): RedirectResponse
    {
        $automotor->delete();

        return redirect()->route('automotores.index')
            ->with('success', 'Automotor eliminado correctamente.');
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

        $placa = Automotor::normalizarPlaca($request->placa);

        $automotor = Automotor::where('placa', $placa)->first();

        if ($automotor) {
            return response()->json([
                'success' => true,
                'data' => [
                    'placa' => $automotor->placa,
                    'marca' => $automotor->marca,
                    'modelo' => $automotor->modelo,
                    'serie' => $automotor->serie,
                    'color' => $automotor->color,
                    'motor' => $automotor->motor,
                    'vin' => $automotor->vin,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge(
                ['placa' => $placa],
                $this->automotorApiService->buscarPorPlaca($placa)
            ),
        ]);
    }
}
