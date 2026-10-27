<?php

namespace App\Support;

use RuntimeException;

/**
 * Falla el arranque si producción queda con una configuración insegura.
 */
final class ProductionSecurityGuard
{
    public static function assertSafeConfiguration(): void
    {
        if (config('app.env') !== 'production') {
            return;
        }

        if ((bool) config('app.debug')) {
            throw new RuntimeException(
                'Configuración insegura: APP_DEBUG debe ser false en producción.'
            );
        }

        if (! (bool) config('session.secure')) {
            throw new RuntimeException(
                'Configuración insegura: SESSION_SECURE_COOKIE debe ser true en producción.'
            );
        }

        if (! (bool) config('session.http_only')) {
            throw new RuntimeException(
                'Configuración insegura: SESSION_HTTP_ONLY debe ser true en producción.'
            );
        }

        if (! in_array(config('session.same_site'), ['lax', 'strict'], true)) {
            throw new RuntimeException(
                'Configuración insegura: SESSION_SAME_SITE debe ser lax o strict en producción.'
            );
        }
    }
}
