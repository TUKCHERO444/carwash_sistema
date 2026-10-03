<?php

namespace App\Http\Controllers\Cuenta;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
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

    private const MAX_INTENTOS = 5;

    public function __construct(
        private AuditService $auditService,
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

        if ($user === null || ! Hash::check($credenciales['password'], $user->password)) {
            $this->registrarFallo($user);

            return back()->withErrors(['email' => self::MENSAJE_GENERICO])
                ->withInput($request->except('password'));
        }

        if (! $user->hasRole('Cliente') || ! $user->puedeAccederCuenta()) {
            $this->registrarFallo($user);

            return back()->withErrors(['email' => self::MENSAJE_GENERICO])
                ->withInput($request->except('password'));
        }

        $user->update([
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $this->auditService->registrarSesion($user, 'inicio de sesión (cuenta)');

        return redirect()->route('cuenta.panel');
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

    /**
     * Suma un intento fallido y bloquea 15 min al llegar al máximo.
     */
    private function registrarFallo(?User $user): void
    {
        if ($user === null) {
            return;
        }

        $intentos = $user->failed_attempts + 1;

        $user->update([
            'failed_attempts' => $intentos,
            'locked_until' => $intentos >= self::MAX_INTENTOS ? now()->addMinutes(15) : $user->locked_until,
        ]);

        $this->auditService->registrarSesion($user, 'intento fallido cuenta', $user->id);
    }
}
