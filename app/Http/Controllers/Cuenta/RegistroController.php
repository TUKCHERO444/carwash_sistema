<?php

namespace App\Http\Controllers\Cuenta;

use App\Http\Controllers\Controller;
use App\Mail\CuentaVerificacionMail;
use App\Models\Cliente;
use App\Models\CustomerUserLink;
use App\Models\OneTimeToken;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DataNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Registro público de cuentas de cliente (zona /cuenta/*).
 *
 * Crea en transacción: users (PENDING) + clientes + vínculo 1:1
 * + token de verificación. No otorga acceso operativo.
 */
class RegistroController extends Controller
{
    public function __construct(
        private AuditService $auditService,
        private DataNormalizer $normalizer,
    ) {}

    /**
     * Muestra el formulario de registro. Autenticados van a su panel.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('cuenta.panel');
        }

        return view('cuenta.registro');
    }

    /**
     * Valida y crea la cuenta + ficha comercial + vínculo + token.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $validados = $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'dni' => ['nullable', 'string', 'max:12'],
            'telefono' => ['nullable', 'string', 'max:15'],
        ], [], [
            'nombre' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'dni' => 'DNI',
            'telefono' => 'teléfono',
        ]);

        $dni = $this->normalizer->normalizarDni($validados['dni'] ?? null);
        $telefono = $this->normalizer->normalizarTelefono($validados['telefono'] ?? null);

        if (($validados['dni'] ?? null) && $dni === null) {
            return back()->withErrors(['dni' => 'El DNI debe tener 8 dígitos.'])->withInput($request->except('password', 'password_confirmation'));
        }

        if (($validados['telefono'] ?? null) && $telefono === null) {
            return back()->withErrors(['telefono' => 'El teléfono debe tener 9 dígitos.'])->withInput($request->except('password', 'password_confirmation'));
        }

        [$user, $secreto] = DB::transaction(function () use ($validados, $dni, $telefono) {
            $user = User::create([
                'name' => $this->normalizer->normalizarNombre($validados['nombre']),
                'email' => $validados['email'],
                'password' => $validados['password'],
                'activo' => true,
                'status' => 'PENDING_EMAIL_VERIFICATION',
            ]);
            $user->assignRole('Cliente');

            $cliente = Cliente::create([
                'dni' => $dni,
                'nombre' => $user->name,
                'telefono' => $telefono,
            ]);

            CustomerUserLink::create([
                'user_id' => $user->id,
                'cliente_id' => $cliente->id,
                'estado' => 'ACTIVO',
            ]);

            [, $secreto] = OneTimeToken::emitir($user, OneTimeToken::PURPOSE_VERIFICACION, 60 * 24);

            return [$user, $secreto];
        });

        Mail::to($user->email)->send(new CuentaVerificacionMail(
            $user->name,
            route('cuenta.verificacion', ['token' => $secreto]),
        ));

        $this->auditService->registrar($user, 'registro', null, null, 'cuenta', $user->id);

        return redirect()->route('cuenta.login')
            ->with('success', 'Cuenta creada. Revisa tu correo para verificarla antes de ingresar.');
    }
}
