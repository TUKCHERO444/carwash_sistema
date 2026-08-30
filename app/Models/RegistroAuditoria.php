<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RegistroAuditoria extends Model
{
    protected $table = 'registros_auditoria';

    protected $fillable = [
        'modulo',
        'accion',
        'auditable_type',
        'auditable_id',
        'datos_antes',
        'datos_despues',
        'usuario_id',
        'fecha_movimiento',
    ];

    protected $casts = [
        'datos_antes' => 'array',
        'datos_despues' => 'array',
        'fecha_movimiento' => 'datetime',
    ];

    /**
     * Relación polimórfica con el registro auditado.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Usuario que ejecutó la acción.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
