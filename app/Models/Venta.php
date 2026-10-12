<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Venta extends Model
{
    protected $fillable = [
        'correlativo',
        'observacion',
        'subtotal',
        'total',
        'user_id',
        'caja_id',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
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
     * Devoluciones registradas sobre esta venta (la venta nunca se modifica).
     */
    public function devoluciones(): HasMany
    {
        return $this->hasMany(DevolucionVenta::class, 'venta_id');
    }

    /**
     * Cantidad ya devuelta de una línea de detalle (suma de devoluciones previas).
     */
    public function cantidadDevueltaPorDetalle(int $detalleVentaId): int
    {
        return (int) DetalleDevolucionVenta::where('detalle_venta_id', $detalleVentaId)
            ->whereHas('devolucion', fn ($q) => $q->where('venta_id', $this->id))
            ->sum('cantidad');
    }

    /**
     * Líneas de pago del ticket (1 simple, N mixto). Suman el total.
     */
    public function pagos(): MorphMany
    {
        return $this->morphMany(Pago::class, 'pagable');
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
