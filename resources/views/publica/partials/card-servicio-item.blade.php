{{-- Card de servicio real para la página pública. Recibe $servicio (Servicio). --}}
<article class="group relative flex h-full min-h-[24rem] flex-col items-center justify-center overflow-hidden rounded-2xl p-8 text-center shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl sm:p-10">
    {{-- Fondo: imagen real si existe + overlay navy; si no, degradé de marca. --}}
    <div class="absolute inset-0">
        @if ($servicio->imagen)
            <img src="{{ $servicio->imagen }}" alt="{{ $servicio->nombre }}"
                 class="h-full w-full object-cover">
            <div class="absolute inset-0"
                 style="background: linear-gradient(165deg, rgba(7, 91, 138, 0.72) 0%, rgba(11, 38, 56, 0.96) 62%);"></div>
        @else
            <div class="absolute inset-0"
                 style="background: linear-gradient(160deg, rgba(7, 91, 138, 0.75) 0%, rgba(11, 38, 56, 0.98) 60%);"></div>
        @endif
    </div>

    {{-- Glow decorativo y marca de agua con el ícono. --}}
    <div class="absolute inset-0" aria-hidden="true">
        <span class="absolute -right-10 -top-10 h-44 w-44 rounded-full bg-brand-cyan-500/10 blur-2xl transition-colors duration-300 group-hover:bg-brand-cyan-500/25"></span>
        <span class="absolute -bottom-12 -left-12 text-white/10 transition-transform duration-300 group-hover:scale-110">
            @include('publica.partials.icono-servicio', ['icono' => $servicio->icono, 'clase' => 'h-44 w-44 sm:h-52 sm:w-52'])
        </span>
    </div>

    <div class="relative flex flex-col items-center text-center">
        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 text-brand-cyan-400 ring-1 ring-white/20 backdrop-blur-sm transition-colors duration-300 group-hover:bg-brand-cyan-500 group-hover:text-navy-900">
            @include('publica.partials.icono-servicio', ['icono' => $servicio->icono, 'clase' => 'h-8 w-8'])
        </span>

        <h3 class="mt-6 text-2xl font-semibold uppercase tracking-wide text-white-cold drop-shadow-sm sm:text-3xl">
            {{ mb_strtoupper($servicio->nombre) }}
        </h3>
        <p class="mt-3 max-w-xs text-sm leading-relaxed text-navy-100 sm:text-base">
            {{ $servicio->descripcion }}
        </p>

        <span class="mt-7 rounded-full bg-brand-cyan-500 px-6 py-3 text-sm font-semibold uppercase text-navy-900 shadow-lg shadow-navy-900/40">
            S/ {{ number_format($servicio->precio, 2) }}
        </span>
    </div>
</article>