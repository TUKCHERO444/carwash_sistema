<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActivo
{
    /**
     * Handle an incoming request.
     *
     * If the authenticated user has activo = 0, log them out,
     * invalidate the session, regenerate the CSRF token, and
     * redirect to the login page with an error message.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->withErrors(['email' => 'Tu cuenta ha sido desactivada.']);
        }
        return $next($request);
    }
}
