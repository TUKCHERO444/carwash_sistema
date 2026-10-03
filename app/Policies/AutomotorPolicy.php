<?php

namespace App\Policies;

use App\Models\Automotor;
use App\Models\User;

/**
 * Ownership de vehículos: solo la cuenta vinculada al cliente dueño.
 */
class AutomotorPolicy
{
    /**
     * ¿La cuenta puede ver este automotor?
     */
    public function view(User $user, Automotor $automotor): bool
    {
        return $user->clientes()
            ->wherePivot('estado', 'ACTIVO')
            ->where('clientes.id', $automotor->cliente_id)
            ->exists();
    }
}
