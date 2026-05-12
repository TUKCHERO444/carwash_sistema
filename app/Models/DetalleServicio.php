<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleServicio extends Model
{
    protected $fillable = [
        'ingreso_id',
        'servicio_id',
    ];

    /**
     * Relación con Ingreso
     */
    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso::class);
    }

    /**
     * Relación con Servicio
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}
