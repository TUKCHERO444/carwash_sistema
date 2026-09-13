@extends('layouts.publica')

@section('titulo', 'Productos — Carwash El Chinito')

@section('descripcion', 'Shampoos, ceras, selladores cerámicos, limpiadores y productos de detailing para tu auto. Conoce las categorías de productos de Carwash El Chinito.')

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
                <span class="text-white-cold" aria-current="page">Productos</span>
            </nav>
            <h1 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight text-white-cold leading-tight">
                {{ $contenido->text('productos_titulo') }}
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-base sm:text-lg text-navy-100 leading-relaxed">
                {{ $contenido->text('productos_intro') }}
            </p>
        </div>
    </section>

    {{-- ============================================================
        2. MOSAICO DE CATEGORÍAS
        Reparto dinámico 2-1-2 por grupos de 5 sobre categorías reales
        con productos activos (icono derivado del slug, imagen FUTURO).
        Sección configurable desde el panel (toggle productos_mostrar_mosaico).
    ============================================================ --}}
    @if ($contenido->bool('productos_mostrar_mosaico'))
    <section class="bg-white-cold py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">

            @if (empty($categorias_productos))
                <div class="rounded-2xl border border-dashed border-steel-300 bg-white p-10 text-center text-steel-500">
                    Aún no tenemos categorías con productos disponibles.
                </div>
            @else
                @php
                    // Reparto 2-1-2 (mismo criterio que resources/js/contenido-web/reparto.js):
                    // índice % 5 → 0,1 izquierda; 2 centro; 3,4 derecha.
                    $colIzq = [];
                    $centro = [];
                    $colDer = [];
                    foreach ($categorias_productos as $indice => $categoria) {
                        switch ($indice % 5) {
                            case 0:
                            case 1:
                                $colIzq[] = $categoria;
                                break;
                            case 2:
                                $centro[] = $categoria;
                                break;
                            case 3:
                            case 4:
                                $colDer[] = $categoria;
                                break;
                        }
                    }
                @endphp

                <div class="mt-12 grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-stretch">
                    {{-- Columna izquierda: tiles grandes / medianos alternados. --}}
                    <div class="flex flex-col gap-6">
                        @foreach ($colIzq as $columna => $categoria)
                            @include('publica.partials.promo-categoria-producto', [
                                'categoria' => $categoria,
                                'dimension' => ($columna % 2 === 0)
                                    ? 'lg:flex-[1.15] min-h-[18rem] lg:min-h-0'
                                    : 'lg:flex-1 min-h-[16rem] lg:min-h-0',
                            ])
                        @endforeach
                    </div>

                    {{-- Columna central: un tile por grupo de 5. --}}
                    <div class="flex flex-col gap-6">
                        @foreach ($centro as $categoria)
                            @include('publica.partials.promo-categoria-producto', [
                                'categoria' => $categoria,
                                'dimension' => 'lg:flex-1 min-h-[20rem] lg:min-h-0',
                            ])
                        @endforeach
                    </div>

                    {{-- Columna derecha: tiles grandes / medianos alternados. --}}
                    <div class="flex flex-col gap-6">
                        @foreach ($colDer as $columna => $categoria)
                            @include('publica.partials.promo-categoria-producto', [
                                'categoria' => $categoria,
                                'dimension' => ($columna % 2 === 0)
                                    ? 'lg:flex-[1.15] min-h-[18rem] lg:min-h-0'
                                    : 'lg:flex-1 min-h-[16rem] lg:min-h-0',
                            ])
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
    @endif

    {{-- ============================================================
        3. SELLOS DE CONFIANZA
    ============================================================ --}}
    @include('publica.partials.sellos-confianza')

@endsection