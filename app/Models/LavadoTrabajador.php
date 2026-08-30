<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LavadoTrabajador extends Model
{
    protected $table = 'lavado_trabajadores';

    protected $fillable = [
        'lavado_id',
        'trabajador_id',
    ];

    /**
     * Relación con Lavado
     */
    public function lavado(): BelongsTo
    {
        return $this->belongsTo(Lavado::class);
    }

    /**
     * Relación con Trabajador
     */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }
}
