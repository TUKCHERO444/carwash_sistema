<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marca extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    /**
     * Relación con Producto
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }
}
