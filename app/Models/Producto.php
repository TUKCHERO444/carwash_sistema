<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Producto extends Model
{
    use HasFactory;

    /**
     * Accesor para obtener la URL de la foto.
     * Maneja tanto URLs absolutas (Cloudinary) como rutas locales.
     */
    public function getFotoUrlAttribute(): ?string
    {
        if (!$this->foto) {
            return null;
        }

        if (str_starts_with($this->foto, 'http')) {
            return $this->foto;
        }

        return asset('storage/' . $this->foto);
    }

    protected $fillable = [
        'nombre',
        'precio_compra',
        'precio_venta',
        'stock',
        'inventario',
        'activo',
        'foto',
        'categoria_id',
    ];

    protected $casts = [
        'precio_compra' => 'decimal:2',
        'precio_venta'  => 'decimal:2',
        'stock'         => 'integer',
        'inventario'    => 'integer',
        'activo'        => 'boolean',
        'categoria_id'  => 'integer',
    ];

    /**
     * Relación con Categoria
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
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
}
