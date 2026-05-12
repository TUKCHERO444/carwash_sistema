<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EgresoCaja extends Model
{
    protected $table = 'egresos_caja';

    protected $fillable = [
        'caja_id',
        'monto',
        'descripcion',
        'tipo_pago',
        'user_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    /**
     * Relación con Caja
     */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    /**
     * Relación con User (usuario que registró el egreso)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
