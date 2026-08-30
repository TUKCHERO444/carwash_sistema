<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoKardex extends Model
{
    protected $table = 'movimientos_kardex';

    protected $fillable = [
        'producto_id',
        'tipo',
        'fuente',
        'origen_id',
        'cantidad',
        'stock_antes',
        'stock_despues',
        'usuario_id',
        'fecha_movimiento',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'stock_antes' => 'integer',
        'stock_despues' => 'integer',
        'fecha_movimiento' => 'datetime',
    ];

    /**
     * Relación con Producto.
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Relación con User (usuario que registró la operación origen).
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
