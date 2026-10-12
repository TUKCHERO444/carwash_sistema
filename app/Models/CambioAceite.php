<?php

namespace App\Models;

use App\Services\TiempoAtencionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        'vehiculo_id',
        'fecha',
        'precio',
        'total',
        'descripcion',
        'foto',
        'user_id',
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
     * Relación con Trabajadores (múltiples) a través de cambio_aceite_trabajadores.
     * Única vía de asignación trabajador↔cambio (sin columna directa).
     */
    public function trabajadores(): BelongsToMany
    {
        return $this->belongsToMany(Trabajador::class, 'cambio_aceite_trabajadores')
            ->withTimestamps();
    }

    /**
     * Líneas de pago del ticket (1 simple, N mixto). Suman el total.
     */
    public function pagos(): MorphMany
    {
        return $this->morphMany(Pago::class, 'pagable');
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
