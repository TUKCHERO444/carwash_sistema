<?php

namespace App\Services;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class AdministratorProtectionService
{
    public function esAdministrador(User $user): bool
    {
        return $user->hasRole(RoleNames::ADMINISTRADOR);
    }

    public function puedeGestionarUsuario(User $actor, User $target): bool
    {
        if ($this->esAdministrador($actor)) {
            return true;
        }

        if ($this->esAdministrador($target)) {
            return false;
        }

        return $this->permisosIncluyen($actor, $target->getAllPermissions()->pluck('name'));
    }

    public function puedeAsignarRol(User $actor, string $roleName): bool
    {
        if ($this->esAdministrador($actor)) {
            return true;
        }

        if ($roleName === RoleNames::ADMINISTRADOR) {
            return false;
        }

        $role = Role::with('permissions')->where('name', $roleName)->first();

        if ($role === null) {
            return false;
        }

        return $this->permisosIncluyen($actor, $role->permissions->pluck('name'));
    }

    public function rolesAsignablesPara(User $actor): Collection
    {
        return Role::with('permissions')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role) => $this->puedeAsignarRol($actor, $role->name))
            ->values();
    }

    public function puedeIniciarSesion(User $user): bool
    {
        return (bool) $user->activo
            && ($user->status === null || $user->status === 'ACTIVE');
    }

    public function otrosAdministradores(User $user): int
    {
        return User::role(RoleNames::ADMINISTRADOR)
            ->whereKeyNot($user->getKey())
            ->count();
    }

    public function otrosAdministradoresUtilizables(User $user): int
    {
        return User::role(RoleNames::ADMINISTRADOR)
            ->whereKeyNot($user->getKey())
            ->where('activo', true)
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', 'ACTIVE');
            })
            ->count();
    }

    public function esUltimoAdministrador(User $user): bool
    {
        return $this->esAdministrador($user)
            && $this->otrosAdministradores($user) === 0;
    }

    public function esUltimoAdministradorUtilizable(User $user): bool
    {
        return $this->esAdministrador($user)
            && $this->puedeIniciarSesion($user)
            && $this->otrosAdministradoresUtilizables($user) === 0;
    }

    public function esAdministradorCritico(User $user): bool
    {
        return $this->esUltimoAdministrador($user)
            || $this->esUltimoAdministradorUtilizable($user);
    }

    private function permisosIncluyen(User $actor, Collection $permisosRequeridos): bool
    {
        return $permisosRequeridos
            ->diff($actor->getAllPermissions()->pluck('name'))
            ->isEmpty();
    }
}
