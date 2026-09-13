<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Servicio extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'activo',
        'orden',
        'icono',
        'imagen',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    /**
     * Scope para la página pública: solo servicios activos,
     * ordenados por posición manual y luego por nombre.
     */
    public function scopeWeb($query)
    {
        return $query->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre');
    }

    /**
     * Relación con Lavado a través de detalle_servicios
     */
    public function lavados(): BelongsToMany
    {
        return $this->belongsToMany(Lavado::class, 'detalle_servicios')
            ->withTimestamps();
    }
}
