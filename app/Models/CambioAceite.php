<?php

namespace App\Models;

use App\Services\TiempoAtencionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CambioAceite extends Model
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

    protected $table = 'cambio_aceites';

    protected $fillable = [
        'cliente_id',
        'automotor_id',
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
        'atencion_inicio_at',
        'atencion_fin_at',
        'tiempo_atencion_minutos',
    ];

    protected $casts = [
        'fecha' => 'date',
        'precio' => 'decimal:2',
        'total' => 'decimal:2',
        'monto_efectivo' => 'decimal:2',
        'monto_yape' => 'decimal:2',
        'monto_izipay' => 'decimal:2',
        'atencion_inicio_at' => 'datetime',
        'atencion_fin_at' => 'datetime',
        'tiempo_atencion_minutos' => 'integer',
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

    /**
     * Orden de la cola de atención: arriba los últimos registrados,
     * abajo los más antiguos (timestamp de registro descendente).
     */
    public function scopeColaAtencion(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
    }

    /**
     * Culminados ordenados por fin de atención descendente.
     */
    public function scopeCulminadosPorAtencion(Builder $query): Builder
    {
        return $query->orderBy('atencion_fin_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Etiqueta del tiempo de atención en minutos/horas ("45 min", "2 h 15 min").
     */
    public function getTiempoAtencionLabelAttribute(): ?string
    {
        return app(TiempoAtencionService::class)->etiqueta($this->tiempo_atencion_minutos);
    }
}
