<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Lavado extends Model
{
    use HasFactory;

    /**
     * Accesor para obtener la URL de la foto.
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

    protected $fillable = [
        'cliente_id',
        'automotor_id',
        'vehiculo_id',
        'fecha',
        'estado',
        'precio',
        'total',
        'foto',
        'user_id',
        'metodo_pago',
        'monto_efectivo',
        'monto_yape',
        'monto_izipay',
        'caja_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'precio' => 'decimal:2',
        'total' => 'decimal:2',
        'monto_efectivo' => 'decimal:2',
        'monto_yape' => 'decimal:2',
        'monto_izipay' => 'decimal:2',
    ];

    /**
     * Relación con Cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Relación con Automotor (vehículo físico atendido).
     * FK automotor_id apunta a la PK string automotores.placa.
     */
    public function automotor(): BelongsTo
    {
        return $this->belongsTo(Automotor::class, 'automotor_id', 'placa');
    }

    /**
     * Relación con Vehiculo
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /**
     * Relación con User (usuario que registró el lavado)
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
     * Relación con Trabajador a través de lavado_trabajadores
     */
    public function trabajadores(): BelongsToMany
    {
        return $this->belongsToMany(Trabajador::class, 'lavado_trabajadores')
            ->withTimestamps();
    }

    /**
     * Relación con Servicio a través de detalle_servicios
     */
    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'detalle_servicios')
            ->withTimestamps();
    }

    /**
     * Scope para filtrar lavados en estado pendiente
     */
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    /**
     * Scope para filtrar lavados en estado confirmado
     */
    public function scopeConfirmados($query)
    {
        return $query->where('estado', 'confirmado');
    }
}
