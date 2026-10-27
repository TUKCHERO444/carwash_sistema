<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LoginLockoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MENSAJE_GENERICO = 'Las credenciales proporcionadas no coinciden con nuestros registros.';

    public function __construct(
        private AuditService $auditService,
        private LoginLockoutService $lockout,
    ) {}

    /**
     * Show the login form.
     * Redirects to /dashboard if the user is already authenticated, or to
     * /cuenta when the session belongs to a public client account.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->hasRole('Cliente') && $user->roles->pluck('name')->diff(['Cliente'])->isEmpty()) {
                return redirect()->route('cuenta.panel');
            }

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
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user === null) {
            return back()->withErrors(['email' => self::MENSAJE_GENERICO])
                ->withInput($request->except('password'));
        }

        if ($this->lockout->estaBloqueado($user)) {
            $this->auditService->registrarSesion($user, 'intento con cuenta bloqueada', $user->id);

            return $this->respuestaIntentoFallido($request, $user);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            $this->lockout->registrarFallo($user, 'intento fallido panel');

            return $this->respuestaIntentoFallido($request, $user);
        }

        if (! $user->activo || ($user->status !== null && $user->status !== 'ACTIVE')) {
            $this->lockout->registrarFallo($user, 'intento fallido panel');

            return $this->respuestaIntentoFallido($request, $user);
        }

        $this->lockout->registrarExito($user);

        Auth::login($user);
        $this->auditService->registrarSesion($user, 'inicio de sesión');

        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    /**
     * Devuelve el formulario con el aviso de bloqueo cuando corresponde.
     */
    private function respuestaIntentoFallido(Request $request, User $user): RedirectResponse
    {
        if ($this->lockout->estaBloqueado($user)) {
            $request->session()->flash('bloqueo_hasta', $user->locked_until->toIso8601String());
        }

        return back()->withErrors(['email' => self::MENSAJE_GENERICO])
            ->withInput($request->except('password'));
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
