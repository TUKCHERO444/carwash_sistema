<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OneTimeToken extends Model
{
    public const PURPOSE_VERIFICACION = 'verificacion';

    public const PURPOSE_RECUPERACION = 'recuperacion';

    public const PURPOSE_VINCULACION = 'vinculacion';

    protected $fillable = [
        'user_id',
        'purpose',
        'token_hash',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Emite un token de un solo uso. Persiste solo el hash.
     *
     * @return array{0: self, 1: string} [modelo, secreto en claro (única vez)]
     */
    public static function emitir(User $user, string $purpose, int $minutosVida): array
    {
        $secreto = Str::random(64);

        $token = self::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $secreto),
            'expires_at' => now()->addMinutes($minutosVida),
        ]);

        return [$token, $secreto];
    }

    /**
     * Consume un token por su secreto. Retorna el modelo o null si inválido.
     * El consumo es atómico: marca consumed_at dentro de la misma llamada.
     */
    public static function consumir(string $secreto, string $purpose): ?self
    {
        $token = self::where('purpose', $purpose)
            ->where('token_hash', hash('sha256', $secreto))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($token === null) {
            return null;
        }

        $token->update(['consumed_at' => now()]);

        return $token;
    }

    /**
     * Busca un token válido sin consumirlo (p. ej. para mostrar formularios).
     */
    public static function valido(string $secreto, string $purpose): ?self
    {
        return self::where('purpose', $purpose)
            ->where('token_hash', hash('sha256', $secreto))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
