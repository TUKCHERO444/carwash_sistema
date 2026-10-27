<?php

namespace App\Http\Controllers;

use App\Services\AdministratorProtectionService;
use App\Services\AuditService;
use App\Support\RoleNames;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Solo letras (con acentos españoles y ñ) y espacios internos simples.
     * Rechaza números, símbolos y espacios dobles.
     */
    private const SOLO_LETRAS_RULE = 'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u';

    public function __construct(
        private AuditService $auditService,
        private AdministratorProtectionService $administratorProtection,
    ) {}

    /**
     * Display a listing of roles with their permissions.
     */
    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::with('permissions')->get();

        return view('roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): View
    {
        Gate::authorize('create', Role::class);

        $permissions = $this->agruparPermisos($this->permisosAsignables());

        return view('roles.create', compact('permissions'));
    }

    /**
     * Store a newly created role in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Role::class);

        $request->validate([
            'name' => ['required', 'string', 'max:20', self::SOLO_LETRAS_RULE, 'unique:roles,name', Rule::notIn([RoleNames::ADMINISTRADOR])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($this->permisosAsignables()->pluck('name')->all())],
        ], [
            'name.not_in' => 'El nombre Administrador está reservado para el rol del sistema.',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        $permisos = $request->permissions ?? [];

        $role->syncPermissions($permisos);

        $this->auditService->registrar($role, 'asignar permisos', null, ['permisos' => $permisos]);

        return redirect()->route('roles.index')
            ->with('success', 'Rol creado correctamente.');
    }

    /**
     * Show the form for editing an existing role.
     */
    public function edit(Role $role): View
    {
        $this->asegurarRolEditable($role);

        Gate::authorize('update', $role);

        $permissions = $this->agruparPermisos($this->permisosAsignables());

        return view('roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update the specified role in the database.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->asegurarRolEditable($role);

        Gate::authorize('update', $role);

        $request->validate([
            'name' => ['required', 'string', 'max:20', self::SOLO_LETRAS_RULE, 'unique:roles,name,'.$role->id, Rule::notIn([RoleNames::ADMINISTRADOR])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($this->permisosAsignables()->pluck('name')->all())],
        ], [
            'name.not_in' => 'El nombre Administrador está reservado para el rol del sistema.',
        ]);

        $permisosAntes = $role->permissions()->pluck('name')->sort()->values()->all();

        $role->update(['name' => $request->name]);

        $permisos = $request->permissions ?? [];

        $role->syncPermissions($permisos);

        $permisosDespues = $role->permissions()->pluck('name')->sort()->values()->all();

        if ($permisosAntes !== $permisosDespues) {
            $this->auditService->registrar(
                $role,
                'cambiar permisos',
                ['permisos' => $permisosAntes],
                ['permisos' => $permisosDespues]
            );
        }

        return redirect()->route('roles.index')
            ->with('success', 'Rol actualizado correctamente.');
    }

    /**
     * Remove the specified role from the database.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === RoleNames::ADMINISTRADOR) {
            return redirect()->route('roles.index')
                ->with('error', 'El rol Administrador está protegido y no puede eliminarse.');
        }

        Gate::authorize('delete', $role);

        if ($role->users()->count() > 0) {
            return redirect()->route('roles.index')
                ->with('error', 'No se puede eliminar el rol porque tiene usuarios asignados.');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Rol eliminado correctamente.');
    }

    private function asegurarRolEditable(Role $role): void
    {
        if ($role->name === RoleNames::ADMINISTRADOR) {
            abort(403, 'El rol Administrador está protegido y no puede modificarse.');
        }
    }

    private function permisosAsignables(): Collection
    {
        $actor = auth()->user();

        $query = Permission::query();

        if (! $this->administratorProtection->esAdministrador($actor)) {
            $query->whereIn('name', $actor->getAllPermissions()->pluck('name'));
        }

        return $query->orderBy('name')->get();
    }

    private function agruparPermisos(Collection $permisos): Collection
    {
        return $permisos->groupBy(function (Permission $perm) {
            if (str_contains($perm->name, 'usuarios') || str_contains($perm->name, 'roles')) {
                return 'Seguridad';
            }
            if (str_contains($perm->name, 'trabajadores')) {
                return 'Personal';
            }
            if (str_contains($perm->name, 'inventario') || str_contains($perm->name, 'servicios')) {
                return 'Inventario y Servicios';
            }
            if (str_contains($perm->name, 'vehiculos') || str_contains($perm->name, 'tipos-vehiculo') || str_contains($perm->name, 'clientes')) {
                return 'Clientes y Vehículos';
            }
            if (str_contains($perm->name, 'ventas')) {
                return 'Ventas y Operaciones';
            }
            if (str_contains($perm->name, 'caja')) {
                return 'Caja y Reportes';
            }

            return 'Otros';
        });
    }
}
