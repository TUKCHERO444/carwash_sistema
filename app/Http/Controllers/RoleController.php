<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Solo letras (con acentos españoles y ñ) y espacios internos simples.
     * Rechaza números, símbolos y espacios dobles.
     */
    private const SOLO_LETRAS_RULE = 'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u';

    /**
     * Display a listing of roles with their permissions.
     */
    public function index(): View
    {
        $roles = Role::with('permissions')->get();

        return view('roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): View
    {
        $permissions = Permission::all()->groupBy(function ($perm) {
            if (str_contains($perm->name, 'usuarios') || str_contains($perm->name, 'roles')) {
                return 'Seguridad';
            }
            if (str_contains($perm->name, 'trabajadores')) {
                return 'Personal';
            }
            if (str_contains($perm->name, 'inventario') || str_contains($perm->name, 'servicios')) {
                return 'Inventario y Servicios';
            }
            if (str_contains($perm->name, 'vehiculos') || str_contains($perm->name, 'clientes') || str_contains($perm->name, 'automotores')) {
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

        return view('roles.create', compact('permissions'));
    }

    /**
     * Store a newly created role in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:20', self::SOLO_LETRAS_RULE, 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('roles.index')
            ->with('success', 'Rol creado correctamente.');
    }

    /**
     * Show the form for editing an existing role.
     */
    public function edit(Role $role): View
    {
        $permissions = Permission::all()->groupBy(function ($perm) {
            if (str_contains($perm->name, 'usuarios') || str_contains($perm->name, 'roles')) {
                return 'Seguridad';
            }
            if (str_contains($perm->name, 'trabajadores')) {
                return 'Personal';
            }
            if (str_contains($perm->name, 'inventario') || str_contains($perm->name, 'servicios')) {
                return 'Inventario y Servicios';
            }
            if (str_contains($perm->name, 'vehiculos') || str_contains($perm->name, 'clientes') || str_contains($perm->name, 'automotores')) {
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

        return view('roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update the specified role in the database.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:20', self::SOLO_LETRAS_RULE, 'unique:roles,name,'.$role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->update(['name' => $request->name]);

        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('roles.index')
            ->with('success', 'Rol actualizado correctamente.');
    }

    /**
     * Remove the specified role from the database.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->count() > 0) {
            return redirect()->route('roles.index')
                ->with('error', 'No se puede eliminar el rol porque tiene usuarios asignados.');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Rol eliminado correctamente.');
    }
}
