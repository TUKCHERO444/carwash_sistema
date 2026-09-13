@extends('layouts.publica')

@section('titulo', 'Servicios — Carwash El Chinito')

@section('descripcion', 'Lavado especializado, detailing, cambio de aceite, PPF y polarizados. Conoce todos los servicios de Carwash El Chinito.')

@section('content')

    {{-- ============================================================
        1. CABECERA DE PÁGINA (breadcrumb + título)
    ============================================================ --}}
    <section class="bg-navy-900">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 py-12 sm:py-16 text-center">
            <nav aria-label="Ruta de navegación" class="text-xs sm:text-sm text-steel-400">
                <a href="{{ route('inicio') }}" class="hover:text-brand-cyan-400 transition-colors">Inicio</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span class="text-white-cold" aria-current="page">Servicios</span>
            </nav>
            <h1 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight text-white-cold leading-tight">
                Nuestros servicios
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-base sm:text-lg text-navy-100 leading-relaxed">
                {{ $contenido->text('servicios_intro') }}
            </p>
        </div>
    </section>

    {{-- ============================================================
        2. GRID DE SERVICIOS REALES (servicios activos del panel)
    ============================================================ --}}
    <section class="bg-white-cold py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <span class="text-sm font-semibold uppercase tracking-widest text-brand-blue-700">Servicios</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-semibold text-navy-900">
                    {{ $contenido->text('servicios_titulo') }}
                </h2>
                <p class="mt-4 text-base leading-relaxed text-steel-700">
                    Elige el servicio que buscas para proteger y resaltar a tu engreído.
                </p>
            </div>

            <div class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @forelse ($servicios as $servicio)
                    @include('publica.partials.card-servicio-item', ['servicio' => $servicio])
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-steel-300 bg-white p-10 text-center text-steel-500">
                        Aún no tenemos servicios disponibles.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ============================================================
        3. SELLOS DE CONFIANZA
    ============================================================ --}}
    @include('publica.partials.sellos-confianza')

@endsection