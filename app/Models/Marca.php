<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marca extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'foto',
    ];

    /**
     * Accesor para obtener la URL de la foto.
     * Maneja tanto URLs absolutas (Cloudinary) como rutas locales.
     */
    public function getFotoUrlAttribute(): ?string
    {
        if (! $this->foto) {
            return null;
        }

        if (str_starts_with($this->foto, 'http')) {
            return $this->foto;
        }

        return asset('storage/'.$this->foto);
    }

    /**
     * Relación con Producto
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }
}
