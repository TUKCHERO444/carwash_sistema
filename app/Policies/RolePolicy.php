<?php

namespace App\Policies;

use App\Models\User;
use App\Services\AdministratorProtectionService;
use App\Support\RoleNames;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function __construct(
        private AdministratorProtectionService $administratorProtection,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo('acceso-roles');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo('acceso-roles');
    }

    public function update(User $actor, Role $role): bool
    {
        if (! $actor->hasPermissionTo('acceso-roles') || $role->name === RoleNames::ADMINISTRADOR) {
            return false;
        }

        return $actor->hasRole(RoleNames::ADMINISTRADOR)
            || $this->administratorProtection->puedeAsignarRol($actor, $role->name);
    }

    public function delete(User $actor, Role $role): bool
    {
        if (! $actor->hasPermissionTo('acceso-roles') || $role->name === RoleNames::ADMINISTRADOR) {
            return false;
        }

        return $actor->hasRole(RoleNames::ADMINISTRADOR)
            || $this->administratorProtection->puedeAsignarRol($actor, $role->name);
    }
}
