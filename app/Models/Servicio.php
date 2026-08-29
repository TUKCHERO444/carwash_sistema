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
    ];

    protected $casts = [
        'precio' => 'decimal:2',
    ];

    /**
     * Relación con Ingreso a través de detalle_servicios
     */
    public function ingresos(): BelongsToMany
    {
        return $this->belongsToMany(Ingreso::class, 'detalle_servicios')
            ->withTimestamps();
    }
}
