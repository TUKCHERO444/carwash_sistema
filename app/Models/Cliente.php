<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Relación con Automotor (1 cliente posee N vehículos; FK cliente_id).
     */
    public function automotores(): HasMany
    {
        return $this->hasMany(Automotor::class, 'cliente_id', 'id');
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
     * Relación con Ingreso
     */
    public function ingresos(): HasMany
    {
        return $this->hasMany(Ingreso::class);
    }
}
