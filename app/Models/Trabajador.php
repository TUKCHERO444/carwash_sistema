<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trabajador extends Model
{
    use HasFactory;

    protected $table = 'trabajadores';

    /**
     * Accesor para obtener la URL de la foto.
     * Maneja tanto URLs absolutas (Cloudinary) como rutas locales.
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

    /**
     * Accesor para el nombre completo del trabajador:
     * nombres + apellido paterno + apellido materno.
     */
    public function getNombreCompletoAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->nombre,
            $this->apellido_paterno,
            $this->apellido_materno,
        ])));
    }

    protected $fillable = [
        'dni',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'foto',
        'estado',
        'pago_diario',
    ];

    protected $casts = [
        'estado' => 'boolean',
        'pago_diario' => 'decimal:2',
    ];

    /**
     * Relación con CambioAceite
     */
    public function cambioAceites(): HasMany
    {
        return $this->hasMany(CambioAceite::class);
    }

    /**
     * Relación con Lavado a través de lavado_trabajadores
     */
    public function lavados(): BelongsToMany
    {
        return $this->belongsToMany(Lavado::class, 'lavado_trabajadores');
    }
}
