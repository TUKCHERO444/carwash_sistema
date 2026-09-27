<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compra a proveedor.
 *
 * Es una cabecera con estado: nace en `borrador` sin número y sin efecto sobre el
 * inventario. La recepción es la única transición que genera entrada de Kardex,
 * egreso de caja y actualización del costo de los productos.
 */
class Compra extends Model
{
    use HasFactory;

    protected $fillable = [
        'correlativo',
        'proveedor_id',
        'fecha',
        'tipo_documento',
        'numero_documento',
        'estado',
        'subtotal',
        'total',
        'observaciones',
        'user_id',
        'caja_id',
        'egreso_caja_id',
        'fecha_recepcion',
        'fecha_anulacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'fecha_recepcion' => 'datetime',
    ];

    /** Estados en los que la compra ya no admite edición de líneas. */
    public const ESTADOS_CERRADOS = ['recibida', 'anulada'];

    /** Un borrador es la única etapa editable y anulable del Hito 1. */
    public function esBorrador(): bool
    {
        return $this->estado === 'borrador';
    }

    public function esRecibida(): bool
    {
        return $this->estado === 'recibida';
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function egresoCaja(): BelongsTo
    {
        return $this->belongsTo(EgresoCaja::class, 'egreso_caja_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleCompra::class);
    }

    /**
     * Texto de la etiqueta del estado, para badges y vistas.
     */
    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            'recibida' => 'Recibida',
            'anulada' => 'Anulada',
            default => 'Borrador',
        };
    }
}
