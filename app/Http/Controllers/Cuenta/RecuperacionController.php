<?php

namespace App\Http\Controllers\Cuenta;

use App\Http\Controllers\Controller;
use App\Mail\CuentaRecuperacionMail;
use App\Models\OneTimeToken;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Recuperación de acceso de cuentas públicas con revocación global.
 */
class RecuperacionController extends Controller
{
    public function __construct(
        private AuditService $auditService,
    ) {}

    /**
     * Formulario para solicitar el enlace. Autenticados van a su panel.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('cuenta.panel');
        }

        return view('cuenta.recuperacion');
    }

    /**
     * Emite el token de recuperación. Respuesta siempre genérica.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if ($user !== null && $user->hasRole('Cliente')) {
            [, $secreto] = OneTimeToken::emitir($user, OneTimeToken::PURPOSE_RECUPERACION, 60);

            Mail::to($user->email)->send(new CuentaRecuperacionMail(
                $user->name,
                route('cuenta.recuperacion.editar', ['token' => $secreto]),
            ));

            $this->auditService->registrar($user, 'solicitud_recuperacion', null, null, 'cuenta', $user->id);
        }

        return back()->with('success', 'Si el correo está registrado, recibirás un enlace para restablecer tu acceso.');
    }

    /**
     * Formulario de nueva contraseña (token válido, sin consumir).
     */
    public function edit(string $token): View|RedirectResponse
    {
        $registro = OneTimeToken::valido($token, OneTimeToken::PURPOSE_RECUPERACION);

        if ($registro === null) {
            return redirect()->route('cuenta.login')
                ->withErrors(['email' => 'El enlace de recuperación no es válido o ya venció. Solicita uno nuevo.']);
        }

        return view('cuenta.nueva-clave', ['token' => $token]);
    }

    /**
     * Cambia la contraseña, consume el token y revoca otras sesiones.
     */
    public function update(Request $request, string $token): RedirectResponse
    {
        $validados = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $registro = OneTimeToken::consumir($token, OneTimeToken::PURPOSE_RECUPERACION);

        if ($registro === null) {
            return redirect()->route('cuenta.login')
                ->withErrors(['email' => 'El enlace de recuperación no es válido o ya venció. Solicita uno nuevo.']);
        }

        $user = $registro->user;
        $nuevaClave = $validados['password'];
        $estabaBloqueada = $user->locked_until !== null;

        DB::transaction(function () use ($user, $nuevaClave) {
            $user->update([
                'password' => $nuevaClave,
                'password_changed_at' => now(),
                'failed_attempts' => 0,
                'locked_until' => null,
            ]);

            $this->revocarOtrasSesiones($user->id);
        });

        $this->auditService->registrar($user, 'cambio_clave_recuperacion', null, null, 'cuenta', $user->id);
        $this->auditService->registrar($user, 'revocacion_global', null, null, 'cuenta', $user->id);

        if ($estabaBloqueada) {
            $this->auditService->registrarSesion($user, 'cuenta desbloqueada', $user->id);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('cuenta.panel')
            ->with('success', 'Contraseña actualizada. Sesión iniciada.');
    }

    /**
     * Revoca todas las sesiones del usuario salvo la actual.
     */
    public function revocarTodas(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->revocarOtrasSesiones($user->id);
        $request->session()->regenerate();

        $this->auditService->registrar($user, 'revocacion_global', null, null, 'cuenta');

        return back()->with('success', 'Se cerraron tus otras sesiones.');
    }

    private function revocarOtrasSesiones(int $userId): void
    {
        $actual = session()->getId();

        DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '<>', $actual)
            ->delete();
    }
}
