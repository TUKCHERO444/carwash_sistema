<?php

namespace App\Http\Controllers\Cuenta;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LoginLockoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Inicio y cierre de sesión de la zona pública (/cuenta/*).
 *
 * Respuesta genérica anti-enumeración ante cualquier fallo
 * (inexistente, incorrecta, no verificada, bloqueada o suspendida).
 */
class LoginController extends Controller
{
    private const MENSAJE_GENERICO = 'Las credenciales no son válidas o la cuenta no está activa.';

    public function __construct(
        private AuditService $auditService,
        private LoginLockoutService $lockout,
    ) {}

    /**
     * Muestra el formulario. Autenticados van a su panel.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('cuenta.panel');
        }

        return view('cuenta.login');
    }

    /**
     * Autentica la cuenta pública con bloqueo progresivo.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $credenciales = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credenciales['email'])->first();

        if ($user === null) {
            return back()->withErrors(['email' => self::MENSAJE_GENERICO])
                ->withInput($request->except('password'));
        }

        if ($this->lockout->estaBloqueado($user)) {
            $this->auditService->registrarSesion($user, 'intento con cuenta bloqueada', $user->id);

            return $this->respuestaIntentoFallido($request, $user);
        }

        if (! Hash::check($credenciales['password'], $user->password)) {
            $this->lockout->registrarFallo($user, 'intento fallido cuenta');

            return $this->respuestaIntentoFallido($request, $user);
        }

        if (! $user->hasRole('Cliente') || ! $user->puedeAccederCuenta()) {
            $this->lockout->registrarFallo($user, 'intento fallido cuenta');

            return $this->respuestaIntentoFallido($request, $user);
        }

        $this->lockout->registrarExito($user);

        Auth::login($user);
        $request->session()->regenerate();

        $this->auditService->registrarSesion($user, 'inicio de sesión (cuenta)');

        return redirect()->route('cuenta.panel');
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
     * Cierra la sesión de la zona pública.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $this->auditService->registrarSesion($user, 'cierre de sesión (cuenta)');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('inicio');
    }
}
