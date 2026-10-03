<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrito extends Model
{
    protected $fillable = [
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CarritoItem::class);
    }

    /**
     * Total = Σ cantidad × precio snapshot (2 decimales).
     */
    protected function total(): Attribute
    {
        return Attribute::get(fn () => round(
            $this->items->sum(fn (CarritoItem $item) => $item->cantidad * (float) $item->precio_unitario),
            2
        ));
    }

    /**
     * Conteo = Σ cantidades (para el header).
     */
    protected function conteo(): Attribute
    {
        return Attribute::get(fn () => (int) $this->items->sum('cantidad'));
    }
}
