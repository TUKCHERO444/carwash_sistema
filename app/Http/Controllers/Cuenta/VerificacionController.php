<?php

namespace App\Http\Controllers\Cuenta;

use App\Http\Controllers\Controller;
use App\Mail\CuentaVerificacionMail;
use App\Models\OneTimeToken;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Verificación de email y activación de cuentas públicas.
 */
class VerificacionController extends Controller
{
    public function __construct(
        private AuditService $auditService,
    ) {}

    /**
     * Consume el token de un solo uso y activa la cuenta.
     */
    public function verify(Request $request, string $token): RedirectResponse
    {
        $registro = OneTimeToken::consumir($token, OneTimeToken::PURPOSE_VERIFICACION);

        if ($registro === null) {
            return redirect()->route('cuenta.login')
                ->withErrors(['email' => 'El enlace de verificación no es válido o ya venció. Solicita uno nuevo.']);
        }

        $user = $registro->user;
        $user->update([
            'email_verified_at' => now(),
            'status' => 'ACTIVE',
        ]);

        $this->auditService->registrar($user, 'verificacion', null, null, 'cuenta', $user->id);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('cuenta.panel')
            ->with('success', '¡Cuenta verificada! Bienvenido a Carwash El Chinito.');
    }

    /**
     * Reenvía el enlace de verificación. Respuesta siempre genérica.
     */
    public function reenviar(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if ($user !== null && $user->hasRole('Cliente') && ! $user->hasVerifiedEmail()) {
            [, $secreto] = OneTimeToken::emitir($user, OneTimeToken::PURPOSE_VERIFICACION, 60 * 24);

            Mail::to($user->email)->send(new CuentaVerificacionMail(
                $user->name,
                route('cuenta.verificacion', ['token' => $secreto]),
            ));
        }

        return back()->with('success', 'Si el correo está registrado y pendiente de verificación, recibirás un nuevo enlace.');
    }

    /**
     * Formulario de reenvío de verificación.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('cuenta.panel');
        }

        return view('cuenta.verificacion');
    }
}
