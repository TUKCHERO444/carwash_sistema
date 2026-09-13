{{-- Tarjeta promocional de categoría de producto (estilo promo-block del tema de
     referencia: imagen de fondo + overlay + CTA flotante blanco "VER MÁS").
     Recibe $categoria (slug, nombre, descripcion, icono, imagen) y $dimension (clases de tamaño).
     La imagen es la foto del primer producto activo de la categoría con foto
     (desde el panel); si no hay, se usa el degradado del ícono como fondo. --}}
@php
    $fondo = match ($categoria['icono']) {
        'lavado'           => 'from-navy-600 via-navy-800 to-navy-950',
        'ceramico'         => 'from-brand-blue-700 via-navy-900 to-navy-950',
        'descontaminado'   => 'from-navy-700 via-navy-900 to-slate-950',
        'limpiadores'      => 'from-brand-cyan-600 via-navy-800 to-navy-950',
        'carroceria'       => 'from-navy-800 via-navy-900 to-navy-950',
        default            => 'from-navy-700 via-navy-900 to-navy-950',
    };
@endphp
<a href="{{ route('publica.productos.categoria', ['categoria' => $categoria['slug']]) }}"
   class="group relative flex flex-col justify-end overflow-hidden rounded-2xl bg-gradient-to-br {{ $fondo }} p-8 sm:p-10 text-white-cold shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-lg {{ $dimension ?? '' }}">

    {{-- Brillo decorativo (sustituye al fotográfico del tema). --}}
    <div class="absolute inset-0" aria-hidden="true" style="background:
        radial-gradient(24rem 16rem at 85% 0%, rgba(25, 169, 229, 0.22), transparent 60%);">
    </div>
    {{-- Imagen real de la categoría (portada del primer producto activo con
         foto desde el panel). Se monta sobre el degradado, que queda de respaldo. --}}
    @if ($categoria['imagen'])
        <img src="{{ $categoria['imagen'] }}" alt="{{ $categoria['nombre'] }}"
             class="absolute inset-0 h-full w-full object-cover opacity-90 transition-transform duration-300 group-hover:scale-105"
             aria-hidden="true" loading="lazy">
    @endif

    {{-- Overlay inferior de legibilidad (linear-gradient del tema). --}}
    <div class="absolute inset-0 bg-gradient-to-t from-navy-950/70 via-navy-950/10 to-transparent" aria-hidden="true"></div>

    {{-- Marca de agua del ícono. --}}
    <span class="absolute top-6 right-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-white/90 backdrop-blur-sm">
        @include('publica.partials.icono-producto', ['icono' => $categoria['icono'], 'clase' => 'h-7 w-7'])
    </span>

    <div class="relative max-w-sm">
        <h3 class="text-xl sm:text-2xl font-semibold uppercase tracking-widest text-white-cold">{{ mb_strtoupper($categoria['nombre']) }}</h3>
        <p class="mt-3 text-sm leading-relaxed text-navy-100">{{ $categoria['descripcion'] }}</p>
        <span class="mt-6 inline-flex items-center gap-2 rounded-full bg-white px-6 py-2.5 text-sm font-semibold uppercase text-navy-900 transition-colors duration-200 group-hover:bg-brand-cyan-500">
            VER MÁS
            <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
            </svg>
        </span>
    </div>
</a>