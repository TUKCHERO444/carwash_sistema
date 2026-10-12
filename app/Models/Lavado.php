<?php

namespace App\Models;

use App\Services\TiempoAtencionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        'vehiculo_id',
        'tipo_vehiculo_id',
        'fecha',
        'estado',
        'precio',
        'total',
        'foto',
        'user_id',
        'caja_id',
        'atencion_inicio_at',
        'atencion_fin_at',
        'tiempo_atencion_minutos',
    ];

    protected $casts = [
        'fecha' => 'date',
        'precio' => 'decimal:2',
        'total' => 'decimal:2',
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
     * Relación con Vehiculo (vehículo físico atendido).
     * FK vehiculo_id apunta a la PK string vehiculos.placa.
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id', 'placa');
    }

    /**
     * Relación con TipoVehiculo (categoría de servicio con precio).
     */
    public function tipoVehiculo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class, 'tipo_vehiculo_id');
    }

    /**
     * Líneas de pago del ticket (1 simple, N mixto). Suman el total.
     */
    public function pagos(): MorphMany
    {
        return $this->morphMany(Pago::class, 'pagable');
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

    /**
     * Orden de la cola de atención: arriba los últimos registrados,
     * abajo los más antiguos (timestamp de registro descendente).
     */
    public function scopeColaAtencion($query)
    {
        return $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
    }

    /**
     * Culminados ordenados por fin de atención descendente.
     */
    public function scopeCulminadosPorAtencion($query)
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
