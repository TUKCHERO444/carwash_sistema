<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Pago extends Model
{
    /**
     * Línea de pago de un ticket (venta, lavado o cambio de aceite).
     * Un ticket mixto tiene 2..N líneas; uno simple, exactamente 1.
     */
    protected $table = 'pagos';

    protected $fillable = [
        'metodo_pago_id',
        'monto',
        'user_id',
        'caja_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    /**
     * Ticket origen (Venta, Lavado o CambioAceite).
     */
    public function pagable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Método de pago de la línea.
     */
    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
    }

    /**
     * Usuario que registró la línea.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Caja del ticket al momento del pago.
     */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }
}
