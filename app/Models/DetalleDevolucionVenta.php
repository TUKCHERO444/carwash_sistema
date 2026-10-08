<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleDevolucionVenta extends Model
{
    protected $table = 'detalle_devoluciones_venta';

    protected $fillable = [
        'devolucion_id',
        'detalle_venta_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Cabecera de la devolución.
     */
    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(DevolucionVenta::class, 'devolucion_id');
    }

    /**
     * Línea original vendida (trazabilidad del tope vendido − devuelto).
     */
    public function detalleVenta(): BelongsTo
    {
        return $this->belongsTo(DetalleVenta::class, 'detalle_venta_id');
    }

    /**
     * Producto reincorporado al inventario.
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
