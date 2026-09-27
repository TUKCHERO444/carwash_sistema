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
        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 py-24 sm:py-32 text-center" data-cw-group>
            <p class="inline-flex items-center gap-2 rounded-full border border-brand-cyan-500/40 bg-navy-800/60 px-4 py-1.5 text-xs sm:text-sm font-semibold uppercase tracking-widest text-cyan-400" data-cw-anim="up">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Brillo y cuidado profesional para tu vehículo
            </p>
            <h1 class="mt-6 text-4xl sm:text-5xl lg:text-6xl font-semibold uppercase tracking-tight text-white-cold leading-tight" data-cw-anim="up">
                Tu auto merece el mejor<br class="hidden sm:block">
                <span class="text-cyan-400">carwash de la ciudad</span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-base sm:text-lg text-navy-100 leading-relaxed" data-cw-anim="up">
                Lavado especializado, detailing, cambio de aceite y productos de primeras marcas.
                Cuidamos tu vehículo como si fuera nuestro.
            </p>
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-3" data-cw-anim="up">
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
            <h2 class="text-center text-sm sm:text-base font-semibold uppercase tracking-widest text-steel-700" data-cw-anim="fade">
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
            <div class="mx-auto max-w-2xl text-center" data-cw-group>
                <span class="text-sm font-semibold uppercase tracking-widest text-brand-blue-700" data-cw-anim="up">Nuestros servicios</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-semibold uppercase text-navy-900" data-cw-anim="up">Cuidado completo para tu vehículo</h2>
                <p class="mt-4 text-base leading-relaxed text-steel-700" data-cw-anim="up">
                    Elige el servicio que buscas para proteger y resaltar a tu engreído.
                </p>
            </div>

            {{-- Laterales al 85% de su ancho original; ese 30% pasa a la card central. --}}
            <div class="mt-12 grid grid-cols-1 md:grid-cols-[0.85fr_1.3fr_0.85fr] gap-6" data-cw-group>
                @forelse ($servicios as $servicio)
                    <a href="{{ route('publica.servicios') }}"
                       class="group relative flex h-full flex-col items-start justify-end overflow-hidden rounded-2xl p-8 text-left shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl" data-cw-anim="up">
                        {{-- Fondo: misma paleta navy que las cards de la página de servicios. --}}
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
                            <span class="absolute -right-10 -top-10 h-44 w-44 rounded-full bg-brand-cyan-500/10 blur-2xl transition-colors duration-300 group-hover:bg-brand-cyan-500/25" aria-hidden="true"></span>
                        </div>

                        <div class="relative flex w-full flex-col">
                            <h3 class="text-xl font-semibold uppercase tracking-wide text-white-cold drop-shadow-sm sm:text-2xl">
                                {{ mb_strtoupper($servicio->nombre) }}
                            </h3>
                            <p class="mt-3 text-sm leading-relaxed text-navy-100">
                                {{ $servicio->descripcion }}
                            </p>

                            <span class="mt-6 inline-flex items-center gap-2 self-start rounded-lg bg-white px-6 py-3 text-sm font-semibold uppercase text-navy-900 shadow-lg shadow-navy-900/40 transition-colors duration-300 group-hover:bg-brand-cyan-500">
                                VER MÁS
                                <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                </svg>
                            </span>
                        </div>
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
        <div class="mx-auto max-w-2xl px-4 sm:px-6 text-center" data-cw-group>
            <h2 class="text-2xl sm:text-3xl font-semibold uppercase text-white-cold" data-cw-anim="up">Boletín El Chinito</h2>
            <p class="mt-3 text-base leading-relaxed text-navy-100" data-cw-anim="up">Suscríbete y recibe promociones exclusivas y novedades de nuestros servicios.</p>
            {{-- FUTURO: POST real al endpoint del boletín. --}}
            <form action="#" method="post" class="mt-8 flex flex-col sm:flex-row gap-3" data-cw-anim="up">
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
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4" data-cw-anim="up">
                <div>
                    <span class="text-sm font-semibold uppercase tracking-widest text-brand-blue-700">Tienda</span>
                    <h2 class="mt-3 text-3xl sm:text-4xl font-semibold uppercase text-navy-900">Te recomendamos</h2>
                </div>
                <a href="{{ route('publica.productos') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-blue-700 hover:text-brand-cyan-600 transition-colors">
                    VER TODOS LOS PRODUCTOS
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>

            {{-- Fila única de 6 cards compactas (grid hasta 6 columnas) --}}
            <div class="mt-10 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 sm:gap-5" data-cw-group>
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
        <div class="mx-auto max-w-4xl px-4 sm:px-6 text-center" data-cw-group>
            <span class="text-sm font-semibold uppercase tracking-widest text-brand-blue-700" data-cw-anim="up">Nuestro trabajo</span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-semibold uppercase text-navy-900" data-cw-anim="up">Dale un vistazo a nuestro último proyecto</h2>

            {{-- FUTURO: thumbnail real (imagen) + URL de video desde el panel. --}}
            <div class="relative mt-10 aspect-video rounded-2xl overflow-hidden bg-navy-900 cursor-pointer flex items-center justify-center" data-cw-anim="up">
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