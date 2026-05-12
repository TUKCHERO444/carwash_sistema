<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = [
        'dni',
        'nombre',
        'telefono',
        'placa',
    ];

    protected $casts = [
        'dni'    => 'string',   // nullable en BD
        'nombre' => 'string',   // nullable en BD
    ];

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

    /**
     * Relación con Venta
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }
}
