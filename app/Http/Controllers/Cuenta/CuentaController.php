<?php

namespace App\Http\Controllers\Cuenta;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Zona privada del cliente: panel de bienvenida y sus vehículos.
 */
class CuentaController extends Controller
{
    /**
     * Panel de bienvenida del cliente autenticado.
     */
    public function panel(Request $request): View
    {
        $user = $request->user();
        $cliente = $user->customerLink?->cliente;

        return view('cuenta.panel', compact('user', 'cliente'));
    }
}
