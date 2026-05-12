<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Caja;

class Venta extends Model
{
    protected $fillable = [
        'correlativo',
        'observacion',
        'subtotal',
        'total',
        'metodo_pago',
        'monto_efectivo',
        'monto_yape',
        'monto_izipay',
        'user_id',
        'caja_id',
    ];

    protected $casts = [
        'subtotal'       => 'decimal:2',
        'total'          => 'decimal:2',
        'monto_efectivo' => 'decimal:2',
        'monto_yape'     => 'decimal:2',
        'monto_izipay'   => 'decimal:2',
    ];

    /**
     * Relación con User (usuario que registró la venta)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con Caja
     */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    /**
     * Relación con DetalleVenta
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }

    /**
     * Relación con Producto a través de detalle_ventas
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'detalle_ventas')
                    ->withPivot('cantidad', 'precio_unitario', 'subtotal')
                    ->withTimestamps();
    }
}
