<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehiculo extends Model
{
    /**
     * Vehículo físico del cliente.
     * Clave primaria: placa (string), sin autoincremento.
     */
    protected $table = 'vehiculos';

    protected $primaryKey = 'placa';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'placa',
        'cliente_id',
        'marca',
        'modelo',
        'serie',
        'color',
        'motor',
        'vin',
    ];

    /**
     * Relación con Cliente (1:N -> 1 vehículo pertenece a 1 cliente)
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Normaliza la placa a mayúsculas y sin espacios.
     */
    public static function normalizarPlaca(?string $placa): string
    {
        return strtoupper(str_replace(' ', '', (string) $placa));
    }
}
