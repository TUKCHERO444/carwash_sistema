@extends('layouts.app')

@section('content')
<div class="p-6">

    @if(session('success'))
        <div role="alert" class="mb-4 px-4 py-3 rounded-lg bg-green-100 text-green-800 border border-green-200 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div role="alert" class="mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-200 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Asistencia</h1>
            <p class="text-sm text-secondary mt-1">Consulta y registra la asistencia diaria del personal.</p>
        </div>
        <button type="button"
                data-abrir-hoy
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Registrar asistencia de hoy
        </button>
    </div>

    {{-- Calendario --}}
    <div class="bg-surface rounded-xl border border-main transition-colors duration-300 overflow-hidden">
        <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-main">
            <button type="button"
                    data-nav-prev
                    aria-label="Mes anterior"
                    class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-secondary hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <h2 id="asistencia-titulo-mes" class="text-lg font-semibold text-primary capitalize"></h2>
            <button type="button"
                    data-nav-next
                    aria-label="Mes siguiente"
                    class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-secondary hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        <div id="asistencia-calendario" class="p-4 sm:p-6"></div>

        <div class="px-6 pb-5 flex flex-wrap gap-x-5 gap-y-2 text-xs text-secondary">
            <span class="inline-flex items-center gap-2">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Asistieron todos
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-500"></span> Asistencia parcial
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500"></span> Nadie asistió
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-slate-500"></span> Sin datos / futuro
            </span>
        </div>
    </div>

    {{-- Modal de detalle --}}
    <x-modal id="modal-asistencia" title="Detalle de asistencia" maxWidth="2xl">
        <div class="px-6 pb-4 sm:pb-6">
            <p id="asistencia-fecha" class="text-sm font-medium text-secondary capitalize mb-4"></p>

            <div id="asistencia-resumen" class="grid grid-cols-2 gap-3 mb-4"></div>

            <div id="asistencia-mensaje" class="hidden mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-200 text-sm"></div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <h4 class="text-xs font-medium uppercase tracking-wide text-secondary mb-2">Asistentes</h4>
                    <ul id="asistencia-asistentes" class="divide-y divide-main border border-main rounded-lg max-h-60 overflow-y-auto"></ul>
                </div>
                <div>
                    <h4 class="text-xs font-medium uppercase tracking-wide text-secondary mb-2">No asistieron</h4>
                    <ul id="asistencia-no-asistentes" class="divide-y divide-main border border-main rounded-lg max-h-60 overflow-y-auto"></ul>
                </div>
            </div>

            <div id="asistencia-gestion" class="hidden mt-5 border-t border-main pt-4">
                <h4 class="text-sm font-medium text-primary mb-3">Registrar asistencia</h4>
                <p class="text-xs text-secondary mb-3">Marca a los trabajadores presentes y ajusta su hora de entrada.</p>
                <div id="asistencia-gestion-filas" class="space-y-1 max-h-56 overflow-y-auto pr-1"></div>
            </div>
        </div>

        <x-slot:footer>
            <button type="button"
                    id="asistencia-guardar"
                    class="hidden items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 transition-colors w-full sm:w-auto">
                Guardar asistencia
            </button>
            <button type="button"
                    data-modal-close
                    class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-main bg-surface text-secondary px-4 py-2 text-sm font-medium hover:text-primary transition-colors">
                Cerrar
            </button>
        </x-slot:footer>
    </x-modal>

</div>

<script>
    window.asistenciaHoy = @json(now()->toDateString());
    window.asistenciaHoraActual = @json(now()->format('H:i'));
    window.asistenciaTotalActivos = @json($totalActivos);
</script>
@vite('resources/js/asistencia/index.js')
@endsection