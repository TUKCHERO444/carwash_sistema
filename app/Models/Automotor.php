<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Automotor extends Model
{
    /**
     * Configuración de clave primaria: placa (string), sin autoincremento.
     */
    protected $table = 'automotores';

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
     * Relación con Cliente (1:N -> 1 automotor pertenece a 1 cliente)
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
