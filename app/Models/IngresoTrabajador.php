<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngresoTrabajador extends Model
{
    protected $table = 'ingreso_trabajadores';

    protected $fillable = [
        'ingreso_id',
        'trabajador_id',
    ];

    /**
     * Relación con Ingreso
     */
    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso::class);
    }

    /**
     * Relación con Trabajador
     */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }
}
