@extends('layouts.auth')

@section('content')
    @php
        $headers = method_exists($exception, 'getHeaders') ? $exception->getHeaders() : [];
        $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 60));
        $rutaLogin = request()->is('cuenta/*') ? route('cuenta.login') : route('login');
    @endphp

    <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm transition-colors duration-300 dark:border-border-dark dark:bg-surface-dark">
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
            <svg aria-hidden="true" class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
            </svg>
        </div>

        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">
            Demasiados intentos
        </h1>
        <p class="mt-3 text-sm text-gray-600 dark:text-text-secondary-dark">
            Has superado el límite de intentos permitidos. Por seguridad, el acceso está bloqueado temporalmente.
        </p>

        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-900/20">
            <p class="text-xs font-semibold tracking-wide text-red-700 uppercase dark:text-red-300">
                Podrás reintentarlo en
            </p>
            <p
                class="mt-1 text-3xl font-semibold tabular-nums text-red-800 dark:text-red-200"
                data-retry-countdown
                data-retry-seconds="{{ $retryAfter }}"
            >{{ sprintf('%02d:%02d', intdiv($retryAfter, 60), $retryAfter % 60) }}</p>
        </div>

        <a
            href="{{ $rutaLogin }}"
            data-retry-button
            class="mt-6 flex w-full items-center justify-center rounded-lg bg-blue-600 px-6 py-3 text-base font-semibold text-white transition-colors duration-200 hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:focus:ring-offset-slate-900"
        >
            Volver al inicio de sesión
        </a>

        <p class="mt-3 text-xs text-gray-500 dark:text-text-secondary-dark">
            El botón se habilitará cuando termine el tiempo de espera.
        </p>
    </div>

    @vite('resources/js/auth/retry-countdown.js')
@endsection
