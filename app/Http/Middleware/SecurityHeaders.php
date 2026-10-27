<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad defensivas para todas las respuestas web.
 *
 * La CSP se mantiene compatible con la aplicación actual: los formularios y
 * vistas usan manejadores inline y estilos inline, por lo que `unsafe-inline`
 * sigue permitido. Aun así, restringe scripts/orígenes externos, plugins,
 * frames y la base URL, reduciendo el impacto de un XSS residual.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        if ($request->secure() || app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $scripts = ["'self'", "'unsafe-inline'"];
        $styles = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];

        if (app()->environment('local')) {
            $scripts[] = 'http://localhost:5173';
            $scripts[] = 'http://127.0.0.1:5173';
            $styles[] = 'http://localhost:5173';
            $styles[] = 'http://127.0.0.1:5173';
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scripts),
            'style-src '.implode(' ', $styles),
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https://res.cloudinary.com https://*.cloudinary.com",
            "connect-src 'self' ws: wss:",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'self'",
            "form-action 'self'",
        ]);
    }
}
