<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private AuditService $auditService,
    ) {}

    /**
     * Show the login form.
     * Redirects to /dashboard if the user is already authenticated.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * Validates the email and password fields, attempts authentication,
     * regenerates the session on success, and redirects to /dashboard.
     * On failure, returns back with a generic error on the 'email' key.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials)) {
            if (! Auth::user()->activo) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Tu cuenta estǭ inactiva. Contacta al administrador.',
                ])->withInput($request->except('password'));
            }

            $this->auditService->registrarSesion(Auth::user(), 'inicio de sesión');

            $request->session()->regenerate();

            return redirect('/dashboard');
        }

        return back()->withErrors([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ])->withInput($request->except('password'));
    }

    /**
     * Log the user out of the application.
     *
     * Invalidates the session, regenerates the CSRF token, and redirects to /login.
     */
    public function logout(Request $request): RedirectResponse
    {
        // Capturar el usuario antes de destruir la sesión.
        $user = Auth::user();
        $this->auditService->registrarSesion($user, 'cierre de sesión');

        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
