@extends('layouts.publica')

@section('titulo', $categoria->nombre.' — Productos — Carwash El Chinito')

@section('descripcion', $categoria->descripcion ?? 'Productos de la colección '.$categoria->nombre.' en Carwash El Chinito.')

@section('content')

    {{-- ============================================================
        1. CABECERA DE PÁGINA (breadcrumb + título de la colección)
    ============================================================ --}}
    <section class="bg-navy-900">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 py-12 sm:py-16 text-center">
            <nav aria-label="Ruta de navegación" class="text-xs sm:text-sm text-steel-400">
                <a href="{{ route('inicio') }}" class="hover:text-brand-cyan-400 transition-colors">Inicio</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <a href="{{ route('publica.productos') }}" class="hover:text-brand-cyan-400 transition-colors">Productos</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span class="text-white-cold" aria-current="page">{{ $categoria->nombre }}</span>
            </nav>
            <h1 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight text-white-cold leading-tight">
                {{ mb_strtoupper($categoria->nombre) }}
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-base sm:text-lg text-navy-100 leading-relaxed">
                {{ $categoria->descripcion ?? 'Productos de la colección '.$categoria->nombre.' para el cuidado de tu auto.' }}
            </p>
        </div>
    </section>

    {{-- ============================================================
        2. LISTADO DE PRODUCTOS (archivo: reference collection page).
        FUTURO: agregar orden por precio/marca (?orden=), botón
        "Añadir al carrito" y rating cuando exista el carrito y las
        reseñas. Hoy la card muestra estado de stock real de la BD.
    ============================================================ --}}
    <section class="bg-white-cold py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">

            {{-- Barra superior: contador + enlace a todas las colecciones. --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-steel-200 pb-4">
                <p class="text-sm text-steel-600">
                    Mostrando <span class="font-semibold text-navy-900">{{ $productos->total() }}</span> {{ $productos->total() === 1 ? 'producto' : 'productos' }}
                </p>
                <a href="{{ route('publica.productos') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-blue-700 hover:text-brand-blue-800 transition-colors">
                    <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Todas las categorías
                </a>
            </div>

            @if ($productos->isEmpty())
                {{-- Estado vacío --}}
                <div class="mt-16 mb-8 flex flex-col items-center gap-5 text-center">
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-steel-100 text-steel-500">
                        <svg aria-hidden="true" class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-xl font-semibold text-navy-900">Aún no hay productos en esta categoría</h2>
                        <p class="mt-2 text-sm text-steel-600">Estamos reponiendo el stock. Explora las demás colecciones mientras tanto.</p>
                    </div>
                    <a href="{{ route('publica.productos') }}" class="inline-flex items-center justify-center rounded-xl bg-brand-cyan-500 px-6 py-3 text-sm font-semibold uppercase tracking-wide text-navy-900 hover:bg-brand-cyan-400 transition-colors">
                        Ver todas las categorías
                    </a>
                </div>
            @else
                {{-- Grid de cards de producto (grid adaptable hasta 4 columnas). --}}
                <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 sm:gap-6">

                    @foreach ($productos as $producto)
                        @php
                            $agotado = (int) $producto->stock <= 0;
                        @endphp
                        <article class="group flex flex-col overflow-hidden rounded-2xl border border-steel-200 bg-white shadow-sm transition-shadow hover:shadow-md">
                            @php
                                $rutaDetalle = route('publica.productos.detalle', ['categoria' => $categoria, 'producto' => $producto]);
                            @endphp
                            {{-- Imagen del producto (o placeholder si no tiene foto). --}}
                            <a href="{{ $rutaDetalle }}" class="relative aspect-square overflow-hidden bg-navy-800">
                                @if ($producto->foto_url)
                                    <img src="{{ $producto->foto_url }}" alt="{{ $producto->nombre }}"
                                         class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                                @else
                                    <span class="flex h-full w-full items-center justify-center text-navy-600">
                                        <svg aria-hidden="true" class="h-16 w-16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                        </svg>
                                    </span>
                                @endif

                                {{-- Sello de agotado sobre la imagen. --}}
                                @if ($agotado)
                                    <span class="absolute inset-0 flex items-center justify-center bg-navy-900/55">
                                        <span class="rounded-full bg-white px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-600">Agotado</span>
                                    </span>
                                @endif
                            </a>

                            {{-- Información de la card. --}}
                            <div class="flex flex-1 flex-col gap-2 p-4">
                                @if ($producto->marca)
                                    <span class="text-xs font-semibold uppercase tracking-widest text-brand-blue-700">{{ mb_strtoupper($producto->marca->nombre) }}</span>
                                @endif
                                <a href="{{ $rutaDetalle }}" class="text-2xl font-semibold leading-snug text-navy-900 line-clamp-2 transition-colors hover:text-brand-blue-700">{{ mayusculas($producto->nombre) }}</a>
                                <p class="mt-auto pt-1 text-lg font-semibold text-navy-900">
                                    S/ {{ number_format((float) $producto->precio_venta, 2, '.', '') }}
                                </p>
                                <p class="text-xs font-medium {{ $agotado ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $agotado ? 'Sin stock disponible' : 'Disponible' }}
                                </p>
                                <a href="{{ $rutaDetalle }}"
                                   class="mt-2 inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-cyan-500 px-4 py-2.5 text-sm font-semibold uppercase tracking-wide text-navy-900 transition-colors hover:bg-brand-cyan-400">
                                    VER DETALLE
                                    <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                    </svg>
                                </a>
                            </div>
                        </article>
                    @endforeach

                </div>

                {{-- Paginación. --}}
                @if ($productos->hasPages())
                    <div class="mt-10">
                        {{ $productos->links() }}
                    </div>
                @endif
            @endif

        </div>
    </section>

    {{-- ============================================================
        3. SELLOS DE CONFIANZA
    ============================================================ --}}
    @include('publica.partials.sellos-confianza')

@endsection