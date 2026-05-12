<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Caja;

class Ingreso extends Model
{
    protected $fillable = [
        'cliente_id',
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
        'fecha'          => 'date',
        'precio'         => 'decimal:2',
        'total'          => 'decimal:2',
        'monto_efectivo' => 'decimal:2',
        'monto_yape'     => 'decimal:2',
        'monto_izipay'   => 'decimal:2',
    ];

    /**
     * Relación con Cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Relación con Vehiculo
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /**
     * Relación con User (usuario que registró el ingreso)
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
     * Relación con Trabajador a través de ingreso_trabajadores
     */
    public function trabajadores(): BelongsToMany
    {
        return $this->belongsToMany(Trabajador::class, 'ingreso_trabajadores')
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
     * Scope para filtrar ingresos en estado pendiente
     */
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    /**
     * Scope para filtrar ingresos en estado confirmado
     */
    public function scopeConfirmados($query)
    {
        return $query->where('estado', 'confirmado');
    }
}
