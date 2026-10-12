<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehiculo;

/**
 * Ownership de vehículos: solo la cuenta vinculada al cliente dueño.
 */
class VehiculoPolicy
{
    /**
     * ¿La cuenta puede ver este vehículo?
     */
    public function view(User $user, Vehiculo $vehiculo): bool
    {
        return $user->clientes()
            ->wherePivot('estado', 'ACTIVO')
            ->where('clientes.id', $vehiculo->cliente_id)
            ->exists();
    }
}
