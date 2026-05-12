<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CambioProducto extends Model
{
    protected $fillable = [
        'cambio_aceite_id',
        'producto_id',
        'cantidad',
        'precio',
        'total',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio'   => 'decimal:2',
        'total'    => 'decimal:2',
    ];

    /**
     * Relación con CambioAceite
     */
    public function cambioAceite(): BelongsTo
    {
        return $this->belongsTo(CambioAceite::class);
    }

    /**
     * Relación con Producto
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
