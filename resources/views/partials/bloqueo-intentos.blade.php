@php
    $bloqueoHasta = session('bloqueo_hasta');
    $segundosBloqueo = $bloqueoHasta
        ? max(0, \Illuminate\Support\Carbon::parse($bloqueoHasta)->getTimestamp() - now()->getTimestamp())
        : 0;
    $rutaLoginBloqueo = $rutaLoginBloqueo ?? route('login');
@endphp

@if ($segundosBloqueo > 0)
    <div
        data-lockout-panel
        role="alert"
        class="mb-6 rounded-xl border border-red-300 bg-red-50 p-5 text-left dark:border-red-800 dark:bg-red-900/20"
    >
        <div class="flex items-start gap-3">
            <span class="mt-0.5 flex h-9 w-9 flex-none items-center justify-center rounded-full bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300">
                <svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </span>

            <div class="min-w-0 flex-1">
                <h2 class="text-base font-semibold text-red-800 dark:text-red-300">
                    Demasiados intentos fallidos
                </h2>
                <p class="mt-1 text-sm text-red-700 dark:text-red-200">
                    Has superado el máximo de intentos permitidos. Por seguridad, el acceso está bloqueado temporalmente.
                </p>
                <p class="mt-3 text-sm text-red-700 dark:text-red-200">
                    Podrás intentarlo de nuevo en
                    <strong
                        class="font-semibold tabular-nums"
                        data-lockout-countdown
                        data-lockout-until="{{ $bloqueoHasta }}"
                    >{{ sprintf('%02d:%02d', intdiv($segundosBloqueo, 60), $segundosBloqueo % 60) }}</strong>.
                </p>

                <a
                    href="{{ $rutaLoginBloqueo }}"
                    data-lockout-button
                    class="mt-4 flex w-full items-center justify-center rounded-lg bg-red-600 px-4 py-3 text-base font-semibold text-white transition-colors duration-200 hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:outline-none dark:focus:ring-offset-slate-900"
                >
                    Volver al inicio de sesión
                </a>
            </div>
        </div>
    </div>
@endif
