<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Caja;

class CambioAceite extends Model
{
    protected $table = 'cambio_aceites';

    protected $fillable = [
        'cliente_id',
        'trabajador_id',
        'fecha',
        'precio',
        'total',
        'descripcion',
        'foto',
        'user_id',
        'metodo_pago',
        'monto_efectivo',
        'monto_yape',
        'monto_izipay',
        'caja_id',
        'estado',
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
     * Relación con Trabajador (directa, para compatibilidad con datos existentes)
     */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    /**
     * Relación con Trabajadores (múltiples) a través de cambio_aceite_trabajadores
     */
    public function trabajadores(): BelongsToMany
    {
        return $this->belongsToMany(Trabajador::class, 'cambio_aceite_trabajadores')
                    ->withTimestamps();
    }

    /**
     * Relación con User
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
     * Relación con Producto a través de cambio_productos
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'cambio_productos')
                    ->withPivot('cantidad', 'precio', 'total')
                    ->withTimestamps();
    }

    /**
     * Scope: filtra registros con estado = 'pendiente'.
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'pendiente');
    }

    /**
     * Scope: filtra registros con estado = 'confirmado'.
     */
    public function scopeConfirmados(Builder $query): Builder
    {
        return $query->where('estado', 'confirmado');
    }
}
