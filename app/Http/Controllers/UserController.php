<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\PasswordSegura;
use App\Services\AdministratorProtectionService;
use App\Services\AuditService;
use App\Support\RoleNames;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Solo letras (con acentos españoles y ñ) y espacios internos simples.
     * Rechaza números, símbolos y espacios dobles.
     */
    private const SOLO_LETRAS_RULE = 'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u';

    private const MENSAJES_PASSWORD = [
        'password.required' => 'La contraseña es obligatoria.',
        'password.string' => 'La contraseña debe ser texto.',
        'password.confirmed' => 'La confirmación de la contraseña no coincide.',
    ];

    public function __construct(
        private AuditService $auditService,
        private AdministratorProtectionService $administratorProtection,
    ) {}

    /**
     * Display a paginated listing of users with their roles.
     */
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        $users = User::with(['roles.permissions', 'permissions'])->paginate(10);

        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        Gate::authorize('create', User::class);

        $roles = $this->rolesAsignables();

        return view('users.create', compact('roles'));
    }

    /**
     * Store a newly created user in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $request->validate([
            'name' => ['required', 'string', 'max:20', self::SOLO_LETRAS_RULE],
            'email' => ['required', 'string', 'email:rfc', 'max:100', 'unique:users'],
            'password' => $this->reglasPassword($request),
            'role' => ['required', 'string', Rule::in($this->rolesAsignables()->pluck('name')->all())],
        ], self::MENSAJES_PASSWORD);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->syncRoles([$request->role]);

        $this->auditService->registrar($user, 'asignar rol', null, ['rol' => $request->role]);

        return redirect()->route('users.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    /**
     * Show the form for editing an existing user.
     */
    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        $roles = $this->rolesAsignables();

        return view('users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified user in the database.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $esPropio = $user->is(auth()->user());
        $rolesAntes = $user->getRoleNames()->sort()->values()->all();

        $request->validate([
            'name' => ['required', 'string', 'max:20', self::SOLO_LETRAS_RULE],
            'email' => ['required', 'string', 'email:rfc', 'max:100', 'unique:users,email,'.$user->id],
            'password' => $this->reglasPassword($request, $user),
            'role' => $esPropio
                ? ['nullable', 'string']
                : ['required', 'string', Rule::in($this->rolesAsignables()->pluck('name')->all())],
        ], self::MENSAJES_PASSWORD);

        if ($esPropio && $request->filled('role') && ! in_array($request->role, $rolesAntes, true)) {
            throw ValidationException::withMessages([
                'role' => 'No puedes cambiar tu propio rol.',
            ]);
        }

        if (! $esPropio) {
            $quitaAdministrador = in_array(RoleNames::ADMINISTRADOR, $rolesAntes, true)
                && $request->role !== RoleNames::ADMINISTRADOR;

            if ($quitaAdministrador && $this->administratorProtection->esAdministradorCritico($user)) {
                throw ValidationException::withMessages([
                    'role' => 'No puedes quitar el rol al último Administrador.',
                ]);
            }
        }

        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if (! empty($request->password)) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        if (! $esPropio) {
            $user->syncRoles([$request->role]);

            $rolesDespues = $user->getRoleNames()->sort()->values()->all();

            if ($rolesAntes !== $rolesDespues) {
                $this->auditService->registrar(
                    $user,
                    'cambiar rol',
                    ['roles' => $rolesAntes],
                    ['roles' => $rolesDespues]
                );
            }
        }

        return redirect()->route('users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Remove the specified user from the database.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return redirect()->route('users.index')
                ->with('error', 'No puedes eliminar tu propio usuario.');
        }

        Gate::authorize('delete', $user);

        if ($this->administratorProtection->esAdministradorCritico($user)) {
            return redirect()->route('users.index')
                ->with('error', 'No puedes eliminar al último Administrador.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }

    /**
     * @return array<int, mixed>
     */
    private function reglasPassword(Request $request, ?User $user = null): array
    {
        $name = $request->input('name');
        $email = $request->input('email');

        return [
            $user === null ? 'required' : 'nullable',
            ...($user === null ? [] : ['sometimes']),
            'string',
            'confirmed',
            new PasswordSegura(
                $user,
                is_string($name) ? $name : null,
                is_string($email) ? $email : null,
            ),
        ];
    }

    private function rolesAsignables(): Collection
    {
        return $this->administratorProtection->rolesAsignablesPara(auth()->user());
    }
}
