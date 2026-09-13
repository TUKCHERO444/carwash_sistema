{{-- Card pequeña de la columna central del mosaico de servicios. Recibe $categoria. --}}
{{-- FUTURO: route('publica.categoria', $categoria['slug']) --}}
<a href="#"
   class="group flex h-full flex-col rounded-2xl border border-steel-200 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-brand-cyan-500/60 hover:shadow-lg sm:p-6">
    <div class="flex items-center gap-4">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-blue-50 text-brand-blue-700 transition-colors duration-300 group-hover:bg-brand-cyan-500 group-hover:text-navy-900">
            @include('publica.partials.icono-servicio', ['icono' => $categoria['icono'], 'clase' => 'h-6 w-6'])
        </span>
        <h3 class="text-base font-semibold uppercase tracking-wide text-navy-900 transition-colors duration-300 group-hover:text-brand-blue-700">
            {{ mb_strtoupper($categoria['nombre']) }}
        </h3>
    </div>

    <p class="mt-3 text-sm leading-relaxed text-steel-700 line-clamp-2">
        {{ $categoria['descripcion'] }}
    </p>

    <span class="mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-semibold text-brand-blue-700 transition-colors duration-300 group-hover:text-brand-cyan-600">
        VER MÁS
        <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
        </svg>
    </span>
</a>