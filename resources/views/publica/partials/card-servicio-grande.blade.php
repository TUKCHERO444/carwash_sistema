{{-- Card grande lateral del mosaico de servicios. Recibe $categoria. --}}
{{-- FUTURO: route('publica.categoria', $categoria['slug']) --}}
<a href="#"
   class="group relative flex h-full min-h-[24rem] flex-col items-center justify-center overflow-hidden rounded-2xl p-8 text-center shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl sm:p-10 lg:min-h-0">
    {{-- Fondo: imagen real futura; hoy un degradé de marca. --}}
    <div class="absolute inset-0"
         style="background: linear-gradient(160deg, rgba(7, 91, 138, 0.75) 0%, rgba(11, 38, 56, 0.98) 60%);">
        @if ($categoria['imagen'])
            {{-- FUTURO: <img src="{{ $categoria['imagen'] }}" alt="{{ $categoria['nombre'] }}" class="h-full w-full object-cover"> --}}
        @endif
    </div>

    {{-- Glow decorativo y marca de agua con el ícono (placeholder visual). --}}
    <div class="absolute inset-0" aria-hidden="true">
        <span class="absolute -right-10 -top-10 h-44 w-44 rounded-full bg-brand-cyan-500/10 blur-2xl transition-colors duration-300 group-hover:bg-brand-cyan-500/25"></span>
        <span class="absolute -bottom-12 -left-12 text-white/10 transition-transform duration-300 group-hover:scale-110">
            @include('publica.partials.icono-servicio', ['icono' => $categoria['icono'], 'clase' => 'h-44 w-44 sm:h-56 sm:w-56'])
        </span>
    </div>

    <div class="relative flex flex-col items-center text-center">
        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 text-brand-cyan-400 ring-1 ring-white/20 backdrop-blur-sm transition-colors duration-300 group-hover:bg-brand-cyan-500 group-hover:text-navy-900">
            @include('publica.partials.icono-servicio', ['icono' => $categoria['icono'], 'clase' => 'h-8 w-8'])
        </span>

        <h3 class="mt-6 text-2xl font-semibold uppercase tracking-wide text-white-cold drop-shadow-sm sm:text-3xl">
            {{ mb_strtoupper($categoria['nombre']) }}
        </h3>
        <p class="mt-3 max-w-xs text-sm leading-relaxed text-navy-100 sm:text-base">
            {{ $categoria['descripcion'] }}
        </p>

        <span class="mt-7 inline-flex items-center gap-2 rounded-full bg-brand-cyan-500 px-7 py-3 text-sm font-semibold uppercase text-navy-900 shadow-lg shadow-navy-900/40 transition-colors duration-300 group-hover:bg-brand-cyan-400">
            VER MÁS
            <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
            </svg>
        </span>
    </div>
</a>