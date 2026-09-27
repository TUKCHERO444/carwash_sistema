<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AjusteInventario extends Model
{
    use HasFactory;

    protected $fillable = [
        'correlativo',
        'tipo',
        'motivo',
        'observaciones',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const TIPOS = [
        'positivo' => 'Ajuste Positivo',
        'negativo' => 'Ajuste Negativo',
        'merma' => 'Merma',
        'daño' => 'Daño',
        'conteo_fisico' => 'Conteo Físico',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleAjusteInventario::class);
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'detalle_ajustes_inventario')
            ->withPivot('cantidad', 'stock_antes', 'stock_despues')
            ->withTimestamps();
    }

    public function etiquetaTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function esEntrada(): bool
    {
        return $this->tipo === 'positivo';
    }

    public function esSalida(): bool
    {
        return in_array($this->tipo, ['negativo', 'merma', 'daño']);
    }

    public function esConteo(): bool
    {
        return $this->tipo === 'conteo_fisico';
    }
}
