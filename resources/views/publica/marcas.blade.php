@extends('layouts.publica')

@section('titulo', 'Marcas — Carwash El Chinito')

@section('descripcion', 'Trabajamos con las mejores marcas de estética automotriz: Carpro, Wurth, Scangrip, Rupes, Menzerna y Flex.')

@section('content')

    {{-- ============================================================
        1. CABECERA DE PÁGINA (breadcrumb + título)
        El título y la introducción provienen del panel de contenidos web.
    ============================================================ --}}
    <section class="bg-navy-900">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 py-12 sm:py-16 text-center">
            <nav aria-label="Ruta de navegación" class="text-xs sm:text-sm text-steel-400">
                <a href="{{ route('inicio') }}" class="hover:text-brand-cyan-400 transition-colors">Inicio</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span class="text-white-cold" aria-current="page">Marcas</span>
            </nav>
            <h1 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight text-white-cold leading-tight">
                {{ $contenido->text('marcas_titulo') }}
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-base sm:text-lg text-navy-100 leading-relaxed">
                {{ $contenido->text('marcas_intro') }}
            </p>
        </div>
    </section>

    {{-- ============================================================
        2. LISTADO DE MARCAS (tabla junta)
        Tabla junta de logos de las marcas seleccionadas en el panel de
        contenidos web (curaduría). Carga solo la foto de cada marca en
        tamaño y resolución estable; crece con la cantidad seleccionada.
    ============================================================ --}}
    <section class="bg-white-cold py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            @include('publica.partials.grilla-marcas', ['marcas' => $marcas])
        </div>
    </section>

    {{-- ============================================================
        3. SELLOS DE CONFIANZA
    ============================================================ --}}
    @include('publica.partials.sellos-confianza')

@endsection