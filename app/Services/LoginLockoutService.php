<?php

namespace App\Services;

use App\Models\User;

/**
 * Bloqueo temporal compartido por el login público y el panel operativo.
 */
class LoginLockoutService
{
    public const MAX_INTENTOS = 5;

    public const MINUTOS_BLOQUEO_BASE = 15;

    public const MINUTOS_BLOQUEO_MAX = 60;

    public function __construct(
        private AuditService $auditService,
    ) {}

    public function estaBloqueado(User $user): bool
    {
        return $user->locked_until !== null && $user->locked_until->isFuture();
    }

    /**
     * Incrementa el contador y bloquea al alcanzar el máximo.
     * No extiende un bloqueo ya vigente.
     */
    public function registrarFallo(?User $user, string $accion): void
    {
        if ($user === null || $this->estaBloqueado($user)) {
            return;
        }

        $intentos = ((int) $user->failed_attempts) + 1;
        $bloqueado = $intentos >= self::MAX_INTENTOS;

        $user->forceFill([
            'failed_attempts' => $intentos,
            'locked_until' => $bloqueado
                ? now()->addMinutes($this->minutosDeBloqueo($intentos))
                : $user->locked_until,
        ])->save();

        $this->auditService->registrarSesion($user, $accion, $user->id);

        if ($bloqueado) {
            $this->auditService->registrarSesion($user, 'cuenta bloqueada', $user->id);
        }
    }

    /**
     * Limpia el bloqueo y registra el último acceso exitoso.
     */
    public function registrarExito(User $user): void
    {
        $estabaBloqueado = $user->locked_until !== null;

        $user->forceFill([
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();

        if ($estabaBloqueado) {
            $this->auditService->registrarSesion($user, 'cuenta desbloqueada', $user->id);
        }
    }

    private function minutosDeBloqueo(int $intentos): int
    {
        $bloques = intdiv($intentos, self::MAX_INTENTOS);

        return min(
            self::MINUTOS_BLOQUEO_BASE * max(1, $bloques),
            self::MINUTOS_BLOQUEO_MAX
        );
    }
}
