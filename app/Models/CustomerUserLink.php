<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerUserLink extends Model
{
    protected $fillable = [
        'user_id',
        'cliente_id',
        'estado',
        'rol',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * ¿El vínculo está vigente para ownership?
     */
    public function estaActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }
}
