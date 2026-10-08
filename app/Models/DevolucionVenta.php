<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevolucionVenta extends Model
{
    protected $table = 'devoluciones_venta';

    protected $fillable = [
        'correlativo',
        'venta_id',
        'venta_nueva_id',
        'caja_id',
        'tipo',
        'motivo',
        'total_devuelto',
        'user_id',
    ];

    protected $casts = [
        'total_devuelto' => 'decimal:2',
    ];

    /**
     * Venta original sobre la que se devuelve (nunca se modifica).
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    /**
     * Venta sustituta generada en un cambio (null en devoluciones simples).
     */
    public function ventaNueva(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_nueva_id');
    }

    /**
     * Caja activa al momento de la devolución.
     */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    /**
     * Usuario que registró la devolución.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Líneas devueltas.
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleDevolucionVenta::class, 'devolucion_id');
    }

    public function esCambio(): bool
    {
        return $this->tipo === 'cambio';
    }
}
