<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caja extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'estado',
        'monto_inicial',
        'fecha_apertura',
        'fecha_cierre',
        'efectivo_esperado',
        'efectivo_contado',
        'diferencia',
        'arqueo_user_id',
        'fecha_arqueo',
        'observacion_arqueo',
    ];

    protected $casts = [
        'monto_inicial' => 'decimal:2',
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'efectivo_esperado' => 'decimal:2',
        'efectivo_contado' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'fecha_arqueo' => 'datetime',
    ];

    /**
     * Relación con User (usuario que abrió la caja)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con User (responsable del arqueo de cierre)
     */
    public function arqueoUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arqueo_user_id');
    }

    /**
     * Relación con EgresoCaja
     */
    public function egresos(): HasMany
    {
        return $this->hasMany(EgresoCaja::class);
    }

    /**
     * Relación con Venta
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /**
     * Relación con CambioAceite
     */
    public function cambioAceites(): HasMany
    {
        return $this->hasMany(CambioAceite::class);
    }

    /**
     * Relación con Lavado (Lavados Vehiculares)
     */
    public function lavados(): HasMany
    {
        return $this->hasMany(Lavado::class);
    }

    /**
     * Scope para filtrar cajas abiertas
     */
    public function scopeAbierta($query)
    {
        return $query->where('estado', 'abierta');
    }

    /**
     * Scope para filtrar cajas cerradas
     */
    public function scopeCerrada($query)
    {
        return $query->where('estado', 'cerrada');
    }
}
