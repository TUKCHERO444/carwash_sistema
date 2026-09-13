<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Categoria extends Model
{
    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'contador_productos',
    ];

    protected $casts = [
        'contador_productos' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Categoria $categoria) {
            if ($categoria->isDirty('nombre') || ! $categoria->slug) {
                $categoria->slug = Str::slug($categoria->nombre);
            }
        });
    }

    /**
     * Relación con Producto
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }
}
