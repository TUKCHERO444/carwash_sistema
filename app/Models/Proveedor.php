<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use HasFactory;

    /**
     * El pluralizador de Laravel convierte "Proveedor" en "proveedors",
     * por lo que la tabla se declara explícitamente.
     */
    protected $table = 'proveedores';

    protected $fillable = [
        'ruc',
        'razon_social',
        'direccion',
        'estado_tributario',
        'condicion',
        'estado',
    ];

    protected $casts = [
        'estado' => 'integer',
    ];

    /**
     * RUC siempre sin espacios y solo dígitos.
     */
    public function setRucAttribute(?string $value): void
    {
        $this->attributes['ruc'] = preg_replace('/\D/', '', (string) $value);
    }

    /**
     * Compras registradas contra este proveedor, de la más reciente a la más antigua.
     */
    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class)->latest('fecha');
    }
}
