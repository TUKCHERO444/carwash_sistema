<?php

namespace App\Policies;

use App\Models\User;
use App\Services\AdministratorProtectionService;

class UserPolicy
{
    public function __construct(
        private AdministratorProtectionService $administratorProtection,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo('acceso-usuarios');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo('acceso-usuarios');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo('acceso-usuarios')
            && $this->administratorProtection->puedeGestionarUsuario($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo('acceso-usuarios')
            && ! $actor->is($target)
            && $this->administratorProtection->puedeGestionarUsuario($actor, $target);
    }

    public function toggle(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo('acceso-usuarios')
            && ! $actor->is($target)
            && $this->administratorProtection->puedeGestionarUsuario($actor, $target);
    }
}
