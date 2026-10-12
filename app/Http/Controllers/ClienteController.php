<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Vehiculo;
use App\Services\DniApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    /**
     * Solo letras (con acentos españoles y ñ) y espacios internos simples.
     * Rechaza números, símbolos y espacios dobles.
     */
    private const SOLO_LETRAS_RULE = 'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u';

    public function __construct(
        private DniApiService $dniApiService,
    ) {}

    /**
     * Muestra la lista paginada de clientes.
     */
    public function index(): View
    {
        $clientes = Cliente::paginate(10);

        return view('clientes.index', compact('clientes'));
    }

    /**
     * Muestra el formulario de creación.
     */
    public function create(): View
    {
        return view('clientes.create');
    }

    /**
     * Valida y persiste un nuevo cliente.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate($this->validationRules());

        Cliente::create($request->only('dni', 'nombre', 'apellido_paterno', 'apellido_materno', 'telefono'));

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente creado correctamente.');
    }

    /**
     * Muestra el formulario de edición con datos precargados.
     * Usa Route Model Binding: Cliente $cliente
     */
    public function edit(Cliente $cliente): View
    {
        return view('clientes.edit', compact('cliente'));
    }

    /**
     * Valida y actualiza un cliente existente.
     */
    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $request->validate($this->validationRules($cliente));

        $cliente->update($request->only('dni', 'nombre', 'apellido_paterno', 'apellido_materno', 'telefono'));

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    /**
     * Reglas de validación compartidas por store() y update().
     *
     * Solo el nombre es obligatorio. DNI, apellidos y teléfono son opcionales
     * (el DNI ya es nullable en BD). La placa se elimina de clientes y se
     * gestiona ahora a través de vehículos.
     *
     * En update(), el DNI se excluye a sí mismo de la comprobación unique.
     */
    private function validationRules(?Cliente $cliente = null): array
    {
        $uniqueDni = $cliente
            ? 'unique:clientes,dni,'.$cliente->id
            : 'unique:clientes,dni';

        return [
            'dni' => ['nullable', 'string', 'digits:8', 'regex:/^[0-9]{8}$/', $uniqueDni],
            'nombre' => ['required', 'string', 'max:50', self::SOLO_LETRAS_RULE],
            'apellido_paterno' => ['nullable', 'string', 'max:50', self::SOLO_LETRAS_RULE],
            'apellido_materno' => ['nullable', 'string', 'max:50', self::SOLO_LETRAS_RULE],
            'telefono' => ['nullable', 'string', 'digits:9', 'regex:/^[0-9]{9}$/'],
        ];
    }

    /**
     * Elimina un cliente si no tiene registros asociados.
     *
     * Checks independientes (en orden):
     *   1. $cliente->lavados()->exists()   → redirect + flash 'error'
     *   2. $cliente->vehiculos()->exists() → redirect + flash 'error'
     *   3. $cliente->cambioAceites()->exists() → redirect + flash 'error'
     *   Si ninguno aplica → delete() + redirect + flash 'success'
     */
    public function destroy(Cliente $cliente): RedirectResponse
    {
        if ($cliente->lavados()->exists()) {
            return redirect()->route('clientes.index')
                ->with('error', 'No se puede eliminar el cliente porque tiene lavados asociados.');
        }

        if ($cliente->vehiculos()->exists()) {
            return redirect()->route('clientes.index')
                ->with('error', 'No se puede eliminar el cliente porque tiene vehículos asociados.');
        }

        if ($cliente->cambioAceites()->exists()) {
            return redirect()->route('clientes.index')
                ->with('error', 'No se puede eliminar el cliente porque tiene cambios de aceite asociados.');
        }

        $cliente->delete();

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente eliminado correctamente.');
    }

    /**
     * Busca el vehículo por placa y devuelve los datos del cliente asociado
     * y sus conteos de servicios (usa vehiculos, no clientes.placa).
     */
    public function buscarPorPlaca(Request $request): JsonResponse
    {
        $placa = $request->get('placa');

        if (! $placa) {
            return response()->json(['success' => false, 'error' => 'Placa no proporcionada'], 400);
        }

        $placa = Vehiculo::normalizarPlaca($placa);

        $vehiculo = Vehiculo::with('cliente')->where('placa', $placa)->first();

        if (! $vehiculo || ! $vehiculo->cliente) {
            return response()->json(['success' => false]);
        }

        $cliente = $vehiculo->cliente;

        return response()->json([
            'success' => true,
            'cliente' => [
                'id' => $cliente->id,
                'placa' => $vehiculo->placa,
                'nombre' => $cliente->nombre,
                'nombre_completo' => $cliente->nombre_completo,
                'dni' => $cliente->dni,
                'telefono' => $cliente->telefono,
                'lavados_count' => $cliente->lavados()->count(),
                'cambios_aceite_count' => $cliente->cambioAceites()->count(),
            ],
            'vehiculo' => [
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'color' => $vehiculo->color,
                'motor' => $vehiculo->motor,
            ],
        ]);
    }

    /**
     * Resumen de lavados confirmados de un cliente para el modal de
     * frecuencia en el listado. Solo lectura: agregados sobre los registros
     * existentes (sin cargar el historial completo, sin contadores manuales).
     */
    public function resumenLavados(Cliente $cliente): JsonResponse
    {
        $confirmados = $cliente->lavados()->confirmados();

        $total = (clone $confirmados)->count();

        $ultimo = (clone $confirmados)
            ->with(['vehiculo:placa,marca,modelo', 'tipoVehiculo:id,nombre'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        $vehiculos = (clone $confirmados)
            ->whereNotNull('vehiculo_id')
            ->selectRaw('vehiculo_id as placa, COUNT(*) as lavados')
            ->groupBy('vehiculo_id')
            ->orderByDesc('lavados')
            ->get()
            ->map(fn ($fila) => [
                'placa' => (string) $fila->placa,
                'lavados' => (int) $fila->lavados,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'cliente' => [
                'id' => $cliente->id,
                'nombre_completo' => $cliente->nombre_completo,
                'dni' => $cliente->dni,
                'telefono' => $cliente->telefono,
            ],
            'resumen' => [
                'total_lavados' => $total,
                'ultimo_lavado' => $ultimo ? [
                    'fecha' => $ultimo->fecha?->format('d/m/Y'),
                    'placa' => $ultimo->vehiculo?->placa,
                    'tipo_vehiculo' => $ultimo->tipoVehiculo?->nombre,
                ] : null,
                'vehiculos' => $vehiculos,
            ],
        ]);
    }

    /**
     * Consulta los datos de la persona por DNI para autocompletar el formulario.
     *
     * Prioriza los datos locales si el DNI ya está registrado; de lo contrario,
     * consulta la API de DNI. Si la API no responde, devuelve success true sin
     * datos (nada que autocompletar), sin bloquear el flujo.
     */
    public function consultarDni(Request $request): JsonResponse
    {
        $request->validate([
            'dni' => ['required', 'string', 'digits:8', 'regex:/^[0-9]{8}$/'],
        ]);

        $cliente = Cliente::where('dni', $request->dni)->first();

        if ($cliente) {
            return response()->json([
                'success' => true,
                'data' => [
                    'numero' => $cliente->dni,
                    'nombres' => $cliente->nombre,
                    'apellido_paterno' => $cliente->apellido_paterno,
                    'apellido_materno' => $cliente->apellido_materno,
                    'nombre_completo' => $cliente->nombre_completo,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->dniApiService->buscarPorDni($request->dni),
        ]);
    }
}
