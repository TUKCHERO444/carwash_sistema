<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleServicio extends Model
{
    protected $fillable = [
        'lavado_id',
        'servicio_id',
    ];

    /**
     * Relación con Lavado
     */
    public function lavado(): BelongsTo
    {
        return $this->belongsTo(Lavado::class);
    }

    /**
     * Relación con Servicio
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}
