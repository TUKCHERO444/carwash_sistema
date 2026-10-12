<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'dni',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'telefono',
    ];

    protected $casts = [
        'dni' => 'string',   // nullable en BD
        'nombre' => 'string',   // nullable en BD
        'apellido_paterno' => 'string',   // nullable en BD
        'apellido_materno' => 'string',   // nullable en BD
    ];

    /**
     * Relación con Vehiculo (1 cliente posee N vehículos; FK cliente_id).
     */
    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class, 'cliente_id', 'id');
    }

    /**
     * Nombre completo: nombres + apellido paterno + apellido materno.
     * Devuelve solo las partes disponibles (compatibilidad con legado).
     */
    public function getNombreCompletoAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->nombre,
            $this->apellido_paterno,
            $this->apellido_materno,
        ])));
    }

    /**
     * Relación con CambioAceite
     */
    public function cambioAceites(): HasMany
    {
        return $this->hasMany(CambioAceite::class);
    }

    /**
     * Relación con Lavado
     */
    public function lavados(): HasMany
    {
        return $this->hasMany(Lavado::class);
    }

    /**
     * Vínculo 1:1 con la cuenta de acceso (zona /cuenta/*). Sin credenciales aquí.
     */
    public function customerLink(): HasOne
    {
        return $this->hasOne(CustomerUserLink::class);
    }

    /**
     * Cuentas de acceso vinculadas (hoy 1:1; preparado para N:M futuro).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customer_user_links')
            ->withPivot('estado', 'rol')
            ->withTimestamps();
    }
}
