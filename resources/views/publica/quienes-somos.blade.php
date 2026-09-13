@extends('layouts.publica')

@section('titulo', '¿Quiénes somos? — Carwash El Chinito')

@section('descripcion', 'Conoce la historia de Carwash El Chinito: nuestra misión, visión y los valores que guían el cuidado de tu auto.')

@section('content')

    {{-- ============================================================
        1. CABECERA DE PÁGINA (breadcrumb + título)
    ============================================================ --}}
    <section class="bg-navy-900">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 py-12 sm:py-16 text-center">
            <nav aria-label="Ruta de navegación" class="text-xs sm:text-sm text-steel-400">
                <a href="{{ route('inicio') }}" class="hover:text-brand-cyan-400 transition-colors">Inicio</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span class="text-white-cold" aria-current="page">¿Quiénes somos?</span>
            </nav>
            <h1 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight text-white-cold leading-tight">
                ¿Quiénes somos?
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-base sm:text-lg text-navy-100 leading-relaxed">
                Conoce la historia detrás de Carwash El Chinito y lo que nos motiva a cuidar tu vehículo.
            </p>
        </div>
    </section>

    {{-- ============================================================
        2. ACERCA DE NOSOTROS
        Arquitectura del tema de referencia (page__content rte):
        columna centrada y estrecha con párrafos justificados.
        FUTURO: historia editable desde el panel de contenidos.
    ============================================================ --}}
    <section class="bg-white-cold py-16 sm:py-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <span class="text-sm font-semibold uppercase tracking-widest text-brand-blue-700">Nuestra empresa</span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-semibold text-navy-900">Una pasión por los vehículos</h2>

            {{-- FUTURO: párrafos provenientes del panel de contenidos. --}}
            <div class="mt-8 space-y-5 text-base leading-relaxed text-steel-700 text-justify">
                <p>
                    Carwash El Chinito es una empresa de estética automotriz, apasionada por los vehículos
                    y en constante búsqueda de las nuevas tendencias internacionales, ofreciendo siempre
                    las mejores opciones a nuestros clientes.
                </p>
                <p>
                    Nuestro objetivo es brindar una experiencia de excelencia, tanto en la aplicación como
                    en la venta de los productos 100% originales que utilizamos en cada servicio, con un
                    equipo experto y equipos de última generación, hasta la entrega de tu auto en óptimas
                    condiciones.
                </p>
                <p>
                    Nos entusiasma destacar la belleza de cada vehículo y a la vez protegerlo para que se
                    mantenga como nuevo, reflejando así la personalidad de nuestros clientes.
                </p>
            </div>
        </div>
    </section>

    {{-- ============================================================
        3. MISIÓN Y VISIÓN
        Mismo estilo narrativo (columna centrada + texto justificado).
        FUTURO: textos editables desde el panel de contenidos.
    ============================================================ --}}
    <section class="bg-navy-900 py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <span class="text-sm font-semibold uppercase tracking-widest text-brand-cyan-500">Nuestra razón de ser</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-semibold text-white-cold">Misión y visión</h2>
            </div>

            <div class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="rounded-2xl bg-navy-800 border border-navy-700 p-8 sm:p-10">
                    <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-brand-cyan-500/10 border border-brand-cyan-500/20 text-brand-cyan-400">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/>
                            <circle cx="12" cy="12" r="5"/>
                            <circle cx="12" cy="12" r="1.5"/>
                        </svg>
                    </span>
                    <h3 class="mt-6 text-xl sm:text-2xl font-semibold text-white-cold">Nuestra misión</h3>
                    <p class="mt-4 text-base leading-relaxed text-navy-100 text-justify">{{ $mision }}</p>
                </div>

                <div class="rounded-2xl bg-navy-800 border border-navy-700 p-8 sm:p-10">
                    <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-brand-cyan-500/10 border border-brand-cyan-500/20 text-brand-cyan-400">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </span>
                    <h3 class="mt-6 text-xl sm:text-2xl font-semibold text-white-cold">Nuestra visión</h3>
                    <p class="mt-4 text-base leading-relaxed text-navy-100 text-justify">{{ $vision }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================================
        4. VALORES
        Grid de tarjetas (ícono + título + detalle), mismo lenguaje
        visual de los sellos de confianza del sitio.
        FUTURO: valores editables desde el panel de contenidos.
    ============================================================ --}}
    <section class="bg-white-cold py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <span class="text-sm font-semibold uppercase tracking-widest text-brand-blue-700">Lo que nos define</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-semibold text-navy-900">Nuestros valores</h2>
                <p class="mt-4 text-base leading-relaxed text-steel-700">
                    Principios que guían cada lavado, cada pulido y cada atención a nuestro cliente.
                </p>
            </div>

            <div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach ($valores as $valor)
                    <div class="rounded-2xl bg-white border border-steel-200 p-6 shadow-sm">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-blue-50 text-brand-blue-700">
                            @if ($valor['icono'] === 'chat')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                </svg>
                            @elseif ($valor['icono'] === 'clock')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @elseif ($valor['icono'] === 'shield')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            @else
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @endif
                        </span>
                        <p class="mt-4 text-sm font-semibold text-navy-900">{{ $valor['titulo'] }}</p>
                        <p class="mt-1.5 text-xs leading-relaxed text-steel-600">{{ $valor['detalle'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================================================
        5. SELLOS DE CONFIANZA
    ============================================================ --}}
    @include('publica.partials.sellos-confianza')

@endsection