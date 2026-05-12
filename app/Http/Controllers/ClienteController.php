<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
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
        $request->validate([
            'dni'      => ['required', 'string', 'size:8', 'regex:/^\d{8}$/', 'unique:clientes,dni'],
            'nombre'   => ['required', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'placa'    => ['required', 'string', 'max:7'],
        ]);

        Cliente::create($request->only('dni', 'nombre', 'telefono', 'placa'));

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
        $request->validate([
            'dni'      => ['required', 'string', 'size:8', 'regex:/^\d{8}$/', "unique:clientes,dni,{$cliente->id}"],
            'nombre'   => ['required', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'placa'    => ['required', 'string', 'max:7'],
        ]);

        $cliente->update($request->only('dni', 'nombre', 'telefono', 'placa'));

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    /**
     * Elimina un cliente si no tiene registros asociados.
     *
     * Checks independientes (en orden):
     *   1. $cliente->ingresos()->exists()      → redirect + flash 'error'
     *   2. $cliente->ventas()->exists()        → redirect + flash 'error'
     *   3. $cliente->cambioAceites()->exists() → redirect + flash 'error'
     *   Si ninguno aplica → delete() + redirect + flash 'success'
     */
    public function destroy(Cliente $cliente): RedirectResponse
    {
        if ($cliente->ingresos()->exists()) {
            return redirect()->route('clientes.index')
                ->with('error', 'No se puede eliminar el cliente porque tiene ingresos asociados.');
        }

        if ($cliente->ventas()->exists()) {
            return redirect()->route('clientes.index')
                ->with('error', 'No se puede eliminar el cliente porque tiene ventas asociadas.');
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
     * Busca un cliente por placa y devuelve sus datos y conteo de servicios.
     */
    public function buscarPorPlaca(Request $request): \Illuminate\Http\JsonResponse
    {
        $placa = $request->get('placa');

        if (!$placa) {
            return response()->json(['error' => 'Placa no proporcionada'], 400);
        }

        $cliente = Cliente::withCount(['ingresos', 'cambioAceites'])
                          ->where('placa', $placa)
                          ->first();

        if (!$cliente) {
            return response()->json(null);
        }

        return response()->json([
            'id' => $cliente->id,
            'placa' => $cliente->placa,
            'nombre' => $cliente->nombre,
            'telefono' => $cliente->telefono,
            'ingresos_count' => $cliente->ingresos_count,
            'cambio_aceites_count' => $cliente->cambio_aceites_count,
        ]);
    }
}
