<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleAjusteInventario extends Model
{
    use HasFactory;

    protected $table = 'detalle_ajustes_inventario';

    protected $fillable = [
        'ajuste_id',
        'producto_id',
        'cantidad',
        'stock_antes',
        'stock_despues',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'stock_antes' => 'integer',
        'stock_despues' => 'integer',
    ];

    public function ajuste(): BelongsTo
    {
        return $this->belongsTo(AjusteInventario::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
