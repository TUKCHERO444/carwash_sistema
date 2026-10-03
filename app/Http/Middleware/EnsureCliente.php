<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta de la zona privada de clientes (/cuenta/*).
 *
 * Exige sesión + rol Cliente + cuenta ACTIVE/activa/verificada.
 * No otorga nada operativo: el panel sigue exigiendo permission:acceso-*.
 */
class EnsureCliente
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('cuenta.login');
        }

        $user = Auth::user();

        if (! $user->hasRole('Cliente') || ! $user->puedeAccederCuenta()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('cuenta.login')
                ->withErrors(['email' => 'Tu cuenta no está activa. Verifica tu correo o contacta al local.']);
        }

        return $next($request);
    }
}
