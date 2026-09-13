@extends('layouts.publica')

@section('titulo', $producto->nombre.' — Carwash El Chinito')

@section('descripcion', $producto->descripcion ?? 'Consulta la disponibilidad y el precio de '.$producto->nombre.' en Carwash El Chinito.')

@section('content')

    {{-- ============================================================
        1. CABECERA DE PÁGINA (breadcrumb completo)
    ============================================================ --}}
    <section class="bg-navy-900">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 py-10 sm:py-12 text-center">
            <nav aria-label="Ruta de navegación" class="text-xs sm:text-sm text-steel-400">
                <a href="{{ route('inicio') }}" class="hover:text-brand-cyan-400 transition-colors">Inicio</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <a href="{{ route('publica.productos') }}" class="hover:text-brand-cyan-400 transition-colors">Productos</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <a href="{{ route('publica.productos.categoria', ['categoria' => $categoria]) }}" class="hover:text-brand-cyan-400 transition-colors">{{ $categoria->nombre }}</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span class="text-white-cold" aria-current="page">{{ mayusculas($producto->nombre) }}</span>
            </nav>
        </div>
    </section>

    {{-- ============================================================
        2. DETALLE DEL PRODUCTO (archivo: reference product page).
        Galería + info pegajosa: marca, título, precio, estado de stock
        y descripción. CTA lleva al formulario de cotización (no hay
        carrito aún), replicando el botón "Añadir al carrito"/"Agotado"
        del tema de referencia.
    ============================================================ --}}
    <section class="bg-white-cold py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            @php
                $agotado = (int) $producto->stock <= 0;
            @endphp

            <div class="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-14">

                {{-- Galería (imagen del producto o placeholder). --}}
                <div class="relative aspect-square overflow-hidden rounded-2xl border border-steel-200 bg-navy-800">
                    @if ($producto->foto_url)
                        <img src="{{ $producto->foto_url }}" alt="{{ $producto->nombre }}" class="h-full w-full object-cover" loading="lazy">
                    @else
                        <span class="flex h-full w-full items-center justify-center text-navy-600">
                            <svg aria-hidden="true" class="h-24 w-24" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </span>
                    @endif
                    @if ($agotado)
                        <span class="absolute inset-0 flex items-center justify-center bg-navy-900/55">
                            <span class="rounded-full bg-white px-5 py-2 text-sm font-semibold uppercase tracking-widest text-red-600">Agotado</span>
                        </span>
                    @endif
                </div>

                {{-- Información del producto. --}}
                <div class="flex flex-col">
                    @if ($producto->marca)
                        <span class="text-xs font-semibold uppercase tracking-widest text-brand-blue-700">{{ mb_strtoupper($producto->marca->nombre) }}</span>
                    @endif
                    <h1 class="mt-2 text-4xl sm:text-5xl font-semibold leading-tight text-navy-900">{{ mayusculas($producto->nombre) }}</h1>

                    <p class="mt-4 text-2xl sm:text-3xl font-semibold text-navy-900">
                        S/ {{ number_format((float) $producto->precio_venta, 2, '.', '') }}
                    </p>

                    <p class="mt-3 inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold {{ $agotado ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' }}">
                        <span aria-hidden="true" class="h-2 w-2 rounded-full {{ $agotado ? 'bg-red-500' : 'bg-green-500' }}"></span>
                        {{ $agotado ? 'Sin stock disponible' : 'Disponible' }}
                    </p>

                    @if ($producto->descripcion)
                        <p class="mt-6 text-base leading-relaxed text-steel-700">{{ $producto->descripcion }}</p>
                    @endif

                    <div class="mt-8 flex flex-wrap gap-3">
                        @if ($agotado)
                            <span class="inline-flex cursor-not-allowed items-center justify-center rounded-xl bg-navy-900 px-8 py-3.5 text-sm font-semibold uppercase tracking-wide text-white-cold opacity-50">
                                Agotado
                            </span>
                        @else
                            <a href="{{ route('publica.cotiza') }}"
                               class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-cyan-500 px-8 py-3.5 text-sm font-semibold uppercase tracking-wide text-navy-900 transition-colors hover:bg-brand-cyan-400">
                                Cotizar este producto
                                <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                </svg>
                            </a>
                        @endif
                        <a href="{{ route('publica.productos.categoria', ['categoria' => $categoria]) }}"
                           class="inline-flex items-center justify-center rounded-xl border border-steel-300 px-6 py-3.5 text-sm font-semibold uppercase tracking-wide text-navy-900 transition-colors hover:border-brand-cyan-500 hover:text-brand-blue-700">
                            Ver todos en {{ $categoria->nombre }}
                        </a>
                    </div>

                    <dl class="mt-8 border-t border-steel-200 pt-6 text-sm">
                        <div class="flex items-baseline justify-between gap-4 py-1.5">
                            <dt class="text-steel-600">Categoría</dt>
                            <dd>
                                <a href="{{ route('publica.productos.categoria', ['categoria' => $categoria]) }}" class="font-semibold text-brand-blue-700 hover:text-brand-blue-800 transition-colors">
                                    {{ $categoria->nombre }}
                                </a>
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4 py-1.5">
                            <dt class="text-steel-600">Marca</dt>
                            <dd class="font-semibold text-navy-900">{{ $producto->marca?->nombre ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4 py-1.5">
                            <dt class="text-steel-600">Disponibilidad</dt>
                            <dd class="font-semibold {{ $agotado ? 'text-red-600' : 'text-green-600' }}">{{ $agotado ? 'Agotado' : 'En stock' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Productos relacionados de la misma categoría. --}}
            @if ($relacionados->isNotEmpty())
                <div class="mt-16">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <span class="text-sm font-semibold uppercase tracking-widest text-brand-blue-700">También te puede interesar</span>
                            <h2 class="mt-2 text-2xl sm:text-3xl font-semibold text-navy-900">Más en {{ $categoria->nombre }}</h2>
                        </div>
                        <a href="{{ route('publica.productos.categoria', ['categoria' => $categoria]) }}"
                           class="hidden sm:inline-flex items-center gap-1.5 text-sm font-semibold text-brand-blue-700 hover:text-brand-cyan-600 transition-colors">
                            VER TODOS
                            <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </a>
                    </div>

                    <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 sm:gap-6">
                        @foreach ($relacionados as $relacionado)
                            @php
                                $relAgotado = (int) $relacionado->stock <= 0;
                                $rutaRel = route('publica.productos.detalle', ['categoria' => $categoria, 'producto' => $relacionado]);
                            @endphp
                            <article class="group flex flex-col overflow-hidden rounded-2xl border border-steel-200 bg-white shadow-sm transition-shadow hover:shadow-md">
                                <a href="{{ $rutaRel }}" class="relative aspect-square overflow-hidden bg-navy-800">
                                    @if ($relacionado->foto_url)
                                        <img src="{{ $relacionado->foto_url }}" alt="{{ $relacionado->nombre }}"
                                             class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                                    @else
                                        <span class="flex h-full w-full items-center justify-center text-navy-600">
                                            <svg aria-hidden="true" class="h-14 w-14" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                        </span>
                                    @endif
                                    @if ($relAgotado)
                                        <span class="absolute inset-0 flex items-center justify-center bg-navy-900/55">
                                            <span class="rounded-full bg-white px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-600">Agotado</span>
                                        </span>
                                    @endif
                                </a>
                                <div class="flex flex-1 flex-col gap-2 p-4">
                                    @if ($relacionado->marca)
                                        <span class="text-xs font-semibold uppercase tracking-widest text-brand-blue-700">{{ mb_strtoupper($relacionado->marca->nombre) }}</span>
                                    @endif
                                    <a href="{{ $rutaRel }}" class="text-2xl font-semibold leading-snug text-navy-900 line-clamp-2 transition-colors hover:text-brand-blue-700">{{ mayusculas($relacionado->nombre) }}</a>
                                    <p class="mt-auto pt-1 text-lg font-semibold text-navy-900">
                                        S/ {{ number_format((float) $relacionado->precio_venta, 2, '.', '') }}
                                    </p>
                                    <a href="{{ $rutaRel }}"
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
                </div>
            @endif
        </div>
    </section>

    {{-- ============================================================
        3. SELLOS DE CONFIANZA
    ============================================================ --}}
    @include('publica.partials.sellos-confianza')

@endsection