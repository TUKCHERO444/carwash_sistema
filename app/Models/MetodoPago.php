<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoPago extends Model
{
    protected $table = 'metodos_pago';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
        'es_efectivo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'es_efectivo' => 'boolean',
    ];

    /**
     * Líneas de pago registradas con este método.
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * Normaliza el código a minúsculas sin espacios.
     */
    public static function normalizarCodigo(?string $codigo): string
    {
        return strtolower(str_replace(' ', '', (string) $codigo));
    }
}
