<?php

namespace App\Http\Controllers;

use App\Models\Automotor;
use App\Models\Cliente;
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
     * gestiona ahora a través de automotores.
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
     *   1. $cliente->ingresos()->exists()   → redirect + flash 'error'
     *   2. $cliente->automotores()->exists() → redirect + flash 'error'
     *   3. $cliente->cambioAceites()->exists() → redirect + flash 'error'
     *   Si ninguno aplica → delete() + redirect + flash 'success'
     */
    public function destroy(Cliente $cliente): RedirectResponse
    {
        if ($cliente->ingresos()->exists()) {
            return redirect()->route('clientes.index')
                ->with('error', 'No se puede eliminar el cliente porque tiene ingresos asociados.');
        }

        if ($cliente->automotores()->exists()) {
            return redirect()->route('clientes.index')
                ->with('error', 'No se puede eliminar el cliente porque tiene automotores asociados.');
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
     * Busca el automotor por placa y devuelve los datos del cliente asociado
     * y sus conteos de servicios (usa automotores, no clientes.placa).
     */
    public function buscarPorPlaca(Request $request): JsonResponse
    {
        $placa = $request->get('placa');

        if (! $placa) {
            return response()->json(['success' => false, 'error' => 'Placa no proporcionada'], 400);
        }

        $placa = Automotor::normalizarPlaca($placa);

        $automotor = Automotor::with('cliente')->where('placa', $placa)->first();

        if (! $automotor || ! $automotor->cliente) {
            return response()->json(['success' => false]);
        }

        $cliente = $automotor->cliente;

        return response()->json([
            'success' => true,
            'cliente' => [
                'id' => $cliente->id,
                'placa' => $automotor->placa,
                'nombre' => $cliente->nombre,
                'nombre_completo' => $cliente->nombre_completo,
                'telefono' => $cliente->telefono,
                'ingresos_count' => $cliente->ingresos()->count(),
                'cambios_aceite_count' => $cliente->cambioAceites()->count(),
            ],
            'automotor' => [
                'placa' => $automotor->placa,
                'marca' => $automotor->marca,
                'modelo' => $automotor->modelo,
                'color' => $automotor->color,
                'motor' => $automotor->motor,
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
