<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContenidoWeb extends Model
{
    use HasFactory;

    protected $table = 'contenido_web';

    protected $fillable = [
        'clave',
        'valor',
        'tipo',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'string',
        ];
    }
}
