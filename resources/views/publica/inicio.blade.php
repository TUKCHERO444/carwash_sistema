@extends('layouts.publica')

@section('titulo', 'Carwash El Chinito — Cuidado automotriz de confianza')

@section('content')

    {{-- ============================================================
        1. HERO
        FUTURO: imagen/fondo del hero configurable desde el panel.
    ============================================================ --}}
    <section class="relative overflow-hidden bg-navy-900">
        <div class="absolute inset-0"
             style="background:
                 radial-gradient(60rem 30rem at 85% 10%, rgba(25, 169, 229, 0.16), transparent 60%),
                 radial-gradient(40rem 24rem at 10% 90%, rgba(7, 91, 138, 0.35), transparent 60%),
                 linear-gradient(180deg, #0B2638 0%, #0E3044 100%);">
        </div>
        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 py-24 sm:py-32 text-center">
            <p class="inline-flex items-center gap-2 rounded-full border border-brand-cyan-500/40 bg-navy-800/60 px-4 py-1.5 text-xs sm:text-sm font-semibold uppercase tracking-widest text-cyan-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Brillo y cuidado profesional para tu vehículo
            </p>
            <h1 class="mt-6 text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tight text-white-cold leading-tight">
                Tu auto merece el mejor<br class="hidden sm:block">
                <span class="text-cyan-400">carwash de la ciudad</span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-base sm:text-lg text-navy-100 leading-relaxed">
                Lavado especializado, detailing, cambio de aceite y productos de primeras marcas.
                Cuidamos tu vehículo como si fuera nuestro.
            </p>
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="#" class="cw-btn-primary w-full sm:w-auto text-center">
                    Reservar mi lavado
                </a>
                <a href="#servicios" class="cw-btn-secondary w-full sm:w-auto text-center">
                    Ver servicios
                </a>
            </div>
        </div>
    </section>

    {{-- ============================================================
        2. MARCAS
        Sección configurable desde el panel (toggle inicio_mostrar_marcas);
        logos en wordmark; el logotipo real por marca es FUTURO.
    ============================================================ --}}
    @if ($contenido->bool('inicio_mostrar_marcas'))
    <section class="bg-white-cold py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <h2 class="text-center text-sm sm:text-base font-semibold uppercase tracking-widest text-steel-700">
                {{ $contenido->text('marcas_titulo') }}
            </h2>
            @include('publica.partials.grilla-marcas', ['marcas' => $marcas])
        </div>
    </section>
    @endif

    {{-- ============================================================
        3. SERVICIOS
        Servicios reales activos del panel; oculta según panel de contenidos.
    ============================================================ --}}
    @if ($contenido->bool('inicio_mostrar_servicios'))
    <section id="servicios" class="bg-white-cold pt-4 pb-20 sm:pt-6 sm:pb-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <span class="text-sm font-semibold uppercase tracking-widest text-brand-blue-700">Nuestros servicios</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-semibold text-navy-900">Cuidado completo para tu vehículo</h2>
                <p class="mt-4 text-base leading-relaxed text-steel-700">
                    Elige el servicio que buscas para proteger y resaltar a tu engreído.
                </p>
            </div>

            <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6">
                @forelse ($servicios as $servicio)
                    <a href="{{ route('publica.servicios') }}"
                       class="group bg-white rounded-2xl border border-steel-200 p-8 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-lg hover:border-brand-cyan-500/60">
                        <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-brand-blue-50 text-brand-blue-700 transition-colors duration-200 group-hover:bg-brand-cyan-500 group-hover:text-navy-900">
                            @switch($servicio->icono)
                                @case('sparkles')
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                    </svg>
                                    @break
                                @case('shield')
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                    @break
                                @case('oil')
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 008 10.172V5L7 4z"/>
                                    </svg>
                                    @break
                                @default
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                            @endswitch
                        </span>
                        <h3 class="mt-6 text-xl font-semibold text-navy-900 group-hover:text-brand-blue-700 transition-colors">
                            {{ $servicio->nombre }}
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-steel-700">
                            {{ $servicio->descripcion }}
                        </p>
                        <span class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-brand-blue-700 group-hover:text-brand-cyan-600 transition-colors">
                            VER MÁS
                            <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </span>
                    </a>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-steel-300 bg-white p-10 text-center text-steel-500">
                        Aún no tenemos servicios disponibles.
                    </div>
                @endforelse
            </div>
        </div>
    </section>
    @endif

    {{-- ============================================================
        4. BOLETÍN (franja navy)
    ============================================================ --}}
    <section class="bg-navy-900 py-16 sm:py-20">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 text-center">
            <h2 class="text-2xl sm:text-3xl font-semibold text-white-cold">Boletín El Chinito</h2>
            <p class="mt-3 text-base leading-relaxed text-navy-100">Suscríbete y recibe promociones exclusivas y novedades de nuestros servicios.</p>
            {{-- FUTURO: POST real al endpoint del boletín. --}}
            <form action="#" method="post" class="mt-8 flex flex-col sm:flex-row gap-3">
                @csrf
                <label for="newsletter-email" class="sr-only">Tu email</label>
                <input id="newsletter-email" type="email" name="email" required placeholder="Tu email"
                       class="flex-1 rounded-lg border border-navy-600 bg-navy-800 px-4 py-3 text-sm text-white-cold placeholder:text-steel-400 focus:border-brand-cyan-500 focus:outline-none focus:ring-1 focus:ring-brand-cyan-500">
                <button type="submit" class="rounded-lg bg-brand-cyan-500 px-6 py-3 text-sm font-semibold text-navy-900 hover:bg-cyan-400 transition-colors">
                    Suscribirse
                </button>
            </form>
        </div>
    </section>

    {{-- ============================================================
        5. PRODUCTOS DESTACADOS
        Productos activos reales (foto Cloudinary, marca y precio).
        Sección configurable desde el panel (toggle inicio_mostrar_productos).
    ============================================================ --}}
    @if ($contenido->bool('inicio_mostrar_productos'))
    <section class="bg-white-cold py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <span class="text-sm font-semibold uppercase tracking-widest text-brand-blue-700">Tienda</span>
                    <h2 class="mt-3 text-3xl sm:text-4xl font-semibold text-navy-900">Te recomendamos</h2>
                </div>
                <a href="{{ route('publica.productos') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-blue-700 hover:text-brand-cyan-600 transition-colors">
                    VER TODOS LOS PRODUCTOS
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>

            {{-- Fila única de 6 cards compactas (grid hasta 6 columnas) --}}
            <div class="mt-10 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 sm:gap-5">
                @forelse ($productos as $producto)
                    @include('publica.partials.card-producto-pequena', ['producto' => $producto])
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-steel-300 bg-white p-10 text-center text-steel-500">
                        Próximamente: productos disponibles para tu auto.
                    </div>
                @endforelse
            </div>
        </div>
    </section>
    @endif

    {{-- ============================================================
        6. VIDEO / TRABAJOS DESTACADOS
        Sección configurable desde el panel (toggle inicio_mostrar_proyecto);
        thumbnail y URL reales del video son FUTURO.
    ============================================================ --}}
    @if ($contenido->bool('inicio_mostrar_proyecto'))
    <section class="bg-white-cold pb-20 sm:pb-24">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 text-center">
            <span class="text-sm font-semibold uppercase tracking-widest text-brand-blue-700">Nuestro trabajo</span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-semibold text-navy-900">Dale un vistazo a nuestro último proyecto</h2>

            {{-- FUTURO: thumbnail real (imagen) + URL de video desde el panel. --}}
            <div class="relative mt-10 aspect-video rounded-2xl overflow-hidden bg-navy-900 cursor-pointer flex items-center justify-center">
                <svg class="absolute inset-0 h-full w-full opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                </svg>
                <button type="button" aria-label="Reproducir video" class="cw-play-btn">
                    <svg class="h-7 w-7 ml-1" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5.14v13.72L19 12 8 5.14z"/>
                    </svg>
                </button>
            </div>
        </div>
    </section>
    @endif

    {{-- ============================================================
        7. SELLOS DE CONFIANZA
    ============================================================ --}}
    @include('publica.partials.sellos-confianza')

@endsection