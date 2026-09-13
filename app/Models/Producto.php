<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    /**
     * Accesor para obtener la URL de la foto.
     * Maneja tanto URLs absolutas (Cloudinary) como rutas locales.
     */
    public function getFotoUrlAttribute(): ?string
    {
        if (! $this->foto) {
            return null;
        }

        if (str_starts_with($this->foto, 'http')) {
            return $this->foto;
        }

        return asset('storage/'.$this->foto);
    }

    /**
     * Accesor que indica si el producto tiene stock bajo respecto al
     * inventario del ciclo vigente. La alerta se activa cuando ya se ha
     * consumido el 75% del inventario (queda 25% o menos).
     * Con inventario 0 no hay ciclo de referencia, por lo que nunca alerta.
     */
    public function getEstaEnAlertaAttribute(): bool
    {
        if ($this->inventario <= 0) {
            return false;
        }

        $consumido = $this->inventario - $this->stock;

        return $consumido >= (int) ceil($this->inventario * 0.75);
    }

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio_compra',
        'precio_venta',
        'stock',
        'inventario',
        'activo',
        'foto',
        'categoria_id',
        'marca_id',
    ];

    protected $casts = [
        'precio_compra' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'stock' => 'integer',
        'inventario' => 'integer',
        'activo' => 'boolean',
        'categoria_id' => 'integer',
        'marca_id' => 'integer',
    ];

    /**
     * Relación con Categoria
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Relación con Marca
     */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    /**
     * Relación con CambioAceite a través de cambio_productos
     */
    public function cambioAceites(): BelongsToMany
    {
        return $this->belongsToMany(CambioAceite::class, 'cambio_productos')
            ->withPivot('cantidad')
            ->withTimestamps();
    }

    /**
     * Relación con Venta a través de detalle_ventas
     */
    public function ventas(): BelongsToMany
    {
        return $this->belongsToMany(Venta::class, 'detalle_ventas')
            ->withPivot('cantidad', 'precio_unitario', 'subtotal')
            ->withTimestamps();
    }

    /**
     * Relación con los movimientos de Kardex del producto.
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoKardex::class);
    }
}
