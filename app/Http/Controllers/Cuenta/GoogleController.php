<?php

namespace App\Http\Controllers\Cuenta;

use App\Http\Controllers\Controller;
use App\Mail\CuentaGoogleMail;
use App\Models\Cliente;
use App\Models\CustomerUserLink;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Services\AuditService;
use App\Services\GoogleOidcException;
use App\Services\GoogleOidcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Inicio de sesión con Google (OIDC Authorization Code + PKCE).
 *
 * Vinculación por (provider, sub); el email nunca identifica.
 * Email coincidente con cuenta local exige prueba de control
 * (sesión local o confirmación explícita), nunca auto-vincula.
 */
class GoogleController extends Controller
{
    public function __construct(
        private GoogleOidcService $oidc,
        private AuditService $auditService,
    ) {}

    /**
     * Redirige a Google con state/nonce/PKCE-S256.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->oidc->configurado()) {
            return redirect()->route('cuenta.login')
                ->withErrors(['email' => 'El ingreso con Google no está configurado.']);
        }

        return redirect()->away($this->oidc->iniciarIntento($request));
    }

    /**
     * Callback: valida state, canjea con verifier y valida el ID token.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->query('error')) {
            return $this->fallo($request, null, 'google_callback_fallido');
        }

        $intento = $this->oidc->consumirIntento($request);

        if ($intento === null || empty($request->query('code'))) {
            return $this->fallo($request, null, 'google_callback_fallido', 'intento ausente o state inválido (posible sesión perdida entre redirect y callback)');
        }

        try {
            $tokens = $this->oidc->canjearCodigo($request->query('code'), $intento['verifier']);
            $claims = $this->oidc->validarIdToken($tokens['id_token'], $intento['nonce']);
        } catch (GoogleOidcException $e) {
            return $this->fallo($request, null, 'google_callback_fallido', $e->getMessage());
        }

        $sub = $claims['sub'];
        $email = isset($claims['email']) ? strtolower(trim((string) $claims['email'])) : null;
        $verificado = ($claims['email_verified'] ?? false) === true;

        $identidad = SocialIdentity::where('provider', SocialIdentity::PROVIDER_GOOGLE)
            ->where('provider_subject', $sub)
            ->first();

        // Sub conocido: ingreso directo.
        if ($identidad !== null) {
            return $this->ingresar($request, $identidad->user, $identidad);
        }

        // Sesión local activa = prueba de control: vinculación explícita.
        if (Auth::check()) {
            return $this->vincularA(Auth::user(), $sub, $email, $verificado, true);
        }

        if ($email === null) {
            return $this->fallo($request, null, 'google_callback_fallido');
        }

        $existente = User::where('email', $email)->first();

        // Email coincidente: NO auto-vincular; exigir la cuenta local.
        if ($existente !== null) {
            $request->session()->put('google_oidc.pendiente', [
                'sub' => $sub,
                'email' => $email,
                'nombre' => $claims['name'] ?? null,
                'verificado' => $verificado,
                'expira' => now()->addMinutes(15)->getTimestamp(),
            ]);

            $this->auditService->registrar($existente, 'vinculacion_requerida', null, null, 'cuenta', $existente->id);

            return redirect()->route('cuenta.login')
                ->with('info', 'Ese correo ya tiene cuenta. Inicia sesión para vincular tu Google.');
        }

        // Alta nueva: cuenta verificada por Google + ficha + vínculo + identidad.
        $user = DB::transaction(function () use ($email, $claims, $sub, $verificado) {
            $user = User::create([
                'name' => $this->nombreDesdeClaims($claims, $email),
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'activo' => true,
                'status' => $verificado ? 'ACTIVE' : 'PENDING_EMAIL_VERIFICATION',
                'email_verified_at' => $verificado ? now() : null,
            ]);
            $user->assignRole('Cliente');

            $cliente = Cliente::create(['nombre' => $user->name]);

            CustomerUserLink::create([
                'user_id' => $user->id,
                'cliente_id' => $cliente->id,
                'estado' => 'ACTIVO',
            ]);

            $user->socialIdentities()->create([
                'provider' => SocialIdentity::PROVIDER_GOOGLE,
                'provider_subject' => $sub,
                'provider_email' => $email,
                'provider_email_verified' => $verificado,
                'linked_at' => now(),
                'last_login_at' => now(),
            ]);

            return $user;
        });

        $this->auditService->registrar($user, 'google_registro', null, null, 'cuenta', $user->id);

        Mail::to($user->email)->send(new CuentaGoogleMail(
            $user->name,
            'Cuenta creada con Google',
            'Creaste tu cuenta de Carwash El Chinito con Google. Si quieres usar contraseña, recupérala desde "Olvidé mi contraseña".',
        ));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('cuenta.panel')
            ->with('success', '¡Bienvenido a Carwash El Chinito!');
    }

    /**
     * Confirma la vinculación pendiente (email ya controlado con sesión).
     */
    public function vincular(Request $request): View|RedirectResponse
    {
        $pendiente = $request->session()->get('google_oidc.pendiente');

        if (! is_array($pendiente) || ($pendiente['expira'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget('google_oidc.pendiente');

            return redirect()->route('cuenta.panel');
        }

        return view('cuenta.google-vincular', ['pendiente' => $pendiente]);
    }

    /**
     * Ejecuta la vinculación pendiente contra la cuenta en sesión.
     */
    public function confirmarVinculo(Request $request): RedirectResponse
    {
        $pendiente = $request->session()->pull('google_oidc.pendiente');
        $user = $request->user();

        if (! is_array($pendiente)
            || ($pendiente['expira'] ?? 0) < now()->getTimestamp()
            || strtolower(trim($user->email)) !== strtolower(trim((string) ($pendiente['email'] ?? '')))
        ) {
            return redirect()->route('cuenta.panel')
                ->withErrors(['email' => 'La vinculación no es válida para esta cuenta.']);
        }

        // Otro usuario pudo vincular el mismo sub mientras tanto.
        if (SocialIdentity::where('provider', SocialIdentity::PROVIDER_GOOGLE)
            ->where('provider_subject', $pendiente['sub'])
            ->exists()
        ) {
            return redirect()->route('cuenta.panel')
                ->withErrors(['email' => 'Esa cuenta de Google ya está vinculada a otro usuario.']);
        }

        return $this->vincularA($user, $pendiente['sub'], $pendiente['email'], (bool) ($pendiente['verificado'] ?? false), false);
    }

    /**
     * Desvincula Google. Nunca permite quitar el último método activo.
     */
    public function desvincular(Request $request): RedirectResponse
    {
        $user = $request->user();

        $identidad = $user->socialIdentities()
            ->where('provider', SocialIdentity::PROVIDER_GOOGLE)
            ->first();

        if ($identidad === null) {
            return back();
        }

        if ($user->password_changed_at === null) {
            return back()->withErrors(['email' => 'Crea primero una contraseña (recupérala) antes de desvincular Google: es tu único acceso.']);
        }

        $identidad->delete();

        $this->auditService->registrar($user, 'google_desvinculacion', null, null, 'cuenta');

        Mail::to($user->email)->send(new CuentaGoogleMail(
            $user->name,
            'Google desvinculado',
            'Desvinculaste tu cuenta de Google de Carwash El Chinito. Seguirás entrando con tu correo y contraseña.',
        ));

        return back()->with('success', 'Cuenta de Google desvinculada.');
    }

    /**
     * Ingreso con sub conocido: checks + sesión + auditoría.
     */
    private function ingresar(Request $request, User $user, SocialIdentity $identidad): RedirectResponse
    {
        if (! $user->hasRole('Cliente') || ! $user->puedeAccederCuenta()) {
            $this->auditService->registrar($user, 'google_callback_fallido', null, null, 'cuenta', $user->id);

            return redirect()->route('cuenta.login')
                ->withErrors(['email' => 'Las credenciales no son válidas o la cuenta no está activa.']);
        }

        $identidad->update(['last_login_at' => now()]);
        $user->update(['last_login_at' => now(), 'failed_attempts' => 0, 'locked_until' => null]);

        Auth::login($user);
        $request->session()->regenerate();

        $this->auditService->registrarSesion($user, 'inicio sesión google');

        return redirect()->route('cuenta.panel');
    }

    /**
     * Vincula un sub a la cuenta en sesión (prueba de control superada).
     */
    private function vincularA(User $user, string $sub, ?string $email, bool $verificado, bool $desdeCallback): RedirectResponse
    {
        $existente = SocialIdentity::where('provider', SocialIdentity::PROVIDER_GOOGLE)
            ->where('provider_subject', $sub)
            ->first();

        if ($existente !== null && $existente->user_id !== $user->id) {
            return redirect()->route('cuenta.panel')
                ->withErrors(['email' => 'Esa cuenta de Google ya está vinculada a otro usuario.']);
        }

        if ($existente === null) {
            $user->socialIdentities()->create([
                'provider' => SocialIdentity::PROVIDER_GOOGLE,
                'provider_subject' => $sub,
                'provider_email' => $email,
                'provider_email_verified' => $verificado,
                'linked_at' => now(),
            ]);

            $this->auditService->registrar($user, 'google_vinculacion', null, null, 'cuenta');

            Mail::to($user->email)->send(new CuentaGoogleMail(
                $user->name,
                'Google vinculado',
                'Vinculaste tu cuenta de Google a Carwash El Chinito. Ya puedes ingresar con ella.',
            ));
        }

        if ($desdeCallback) {
            Auth::login($user);
            request()->session()->regenerate();
        }

        return redirect()->route('cuenta.panel')
            ->with('success', 'Cuenta de Google vinculada.');
    }

    /**
     * Callback fallido genérico (sin filtrar la causa al visitante).
     * Sin titular identificable no hay fila de auditoría (usuario_id
     * NOT NULL): queda en log para métricas de callbacks fallidos.
     */
    private function fallo(Request $request, ?User $user, string $accion, ?string $detalle = null): RedirectResponse
    {
        if ($user !== null) {
            $this->auditService->registrar($user, $accion, null, null, 'cuenta', $user->id);
        } else {
            Log::warning('google_callback_fallido', ['ip' => $request->ip(), 'detalle' => $detalle]);
        }

        return redirect()->route('cuenta.login')
            ->withErrors(['email' => 'No se pudo completar el ingreso con Google. Inténtalo de nuevo.']);
    }

    private function nombreDesdeClaims(array $claims, string $email): string
    {
        $nombre = trim((string) ($claims['name'] ?? ''));

        if ($nombre !== '') {
            return mb_substr($nombre, 0, 100);
        }

        return mb_substr(strtok($email, '@') ?: 'Cliente', 0, 100);
    }
}
