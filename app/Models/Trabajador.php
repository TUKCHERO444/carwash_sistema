<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Trabajador extends Model
{
    protected $table = 'trabajadores';

    protected $fillable = [
        'nombre',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    /**
     * Relación con CambioAceite
     */
    public function cambioAceites(): HasMany
    {
        return $this->hasMany(CambioAceite::class);
    }

    /**
     * Relación con Ingreso a través de ingreso_trabajadores
     */
    public function ingresos(): BelongsToMany
    {
        return $this->belongsToMany(Ingreso::class, 'ingreso_trabajadores');
    }
}
