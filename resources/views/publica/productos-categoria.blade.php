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
        Tabla junta de productos con filtros (orden alfabético, orden por
        stock y búsqueda dinámica) que actúan en conjunto o por separado.
        Paginación de 20 productos con la selección de la categoría en el
        centro y el contador (total — mostrados por página) en la esquina.
    ============================================================ --}}
    <section class="bg-white-cold py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">

            {{-- Barra de control: categoría centrada + filtros (izq) y contador (der). --}}
            <div class="border-b border-steel-200 pb-5">
                <h2 class="text-center text-2xl sm:text-3xl font-semibold tracking-tight text-navy-900">
                    {{ mb_strtoupper($categoria->nombre) }}
                </h2>

                <form id="filtros-productos" action="{{ route('publica.productos.categoria', $categoria) }}" method="GET"
                      class="mt-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    {{-- Filtros (esquina izquierda). --}}
                    <div class="flex flex-wrap items-end gap-3">
                        {{-- Orden alfabético --}}
                        <div>
                            <label for="letra" class="block text-xs font-semibold uppercase tracking-widest text-steel-600 mb-1">
                                Orden alfabético
                            </label>
                            <select id="letra" name="letra" data-auto-submit
                                    class="rounded-lg border border-steel-300 bg-white px-3 py-2 text-sm text-navy-900 focus:outline-none focus:ring-1 focus:ring-brand-cyan-500">
                                <option value="" {{ $letra === '' ? 'selected' : '' }}>Sin orden</option>
                                <option value="asc" {{ $letra === 'asc' ? 'selected' : '' }}>A → Z</option>
                                <option value="desc" {{ $letra === 'desc' ? 'selected' : '' }}>Z → A</option>
                            </select>
                        </div>

                        {{-- Orden por stock disponible --}}
                        <div>
                            <label for="stock" class="block text-xs font-semibold uppercase tracking-widest text-steel-600 mb-1">
                                Stock disponible
                            </label>
                            <select id="stock" name="stock" data-auto-submit
                                    class="rounded-lg border border-steel-300 bg-white px-3 py-2 text-sm text-navy-900 focus:outline-none focus:ring-1 focus:ring-brand-cyan-500">
                                <option value="" {{ $stock === '' ? 'selected' : '' }}>Sin orden</option>
                                <option value="mayor" {{ $stock === 'mayor' ? 'selected' : '' }}>Mayor cantidad</option>
                                <option value="menor" {{ $stock === 'menor' ? 'selected' : '' }}>Menor cantidad</option>
                            </select>
                        </div>

                        {{-- Búsqueda dinámica por nombre, categoría o marca --}}
                        <div class="min-w-[200px] flex-1 sm:min-w-[240px] sm:flex-none">
                            <label for="q" class="block text-xs font-semibold uppercase tracking-widest text-steel-600 mb-1">
                                Buscar
                            </label>
                            <input type="search" id="q" name="q" value="{{ $q }}"
                                   placeholder="Nombre, categoría o marca"
                                   autocomplete="off"
                                   data-auto-search
                                   class="w-full rounded-lg border border-steel-300 bg-white px-3 py-2 text-sm text-navy-900 placeholder:text-steel-400 focus:outline-none focus:ring-1 focus:ring-brand-cyan-500">
                        </div>

                        <a href="{{ route('publica.productos.categoria', $categoria) }}"
                           class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-blue-700 hover:text-brand-blue-800 transition-colors">
                            <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Limpiar filtros
                        </a>
                    </div>

                    {{-- Contador (esquina derecha): total — mostrados en la página. --}}
                    <p class="text-sm text-steel-600 lg:text-right lg:shrink-0">
                        <span class="font-semibold text-navy-900">{{ $totalCategoria }}</span>
                        {{ $totalCategoria === 1 ? 'producto' : 'productos' }}
                        <span class="text-steel-400">—</span>
                        mostrando <span class="font-semibold text-navy-900">{{ $productos->count() }}</span>
                    </p>
                </form>
            </div>

            @if ($productos->isEmpty())
                @if ($letra !== '' || $stock !== '' || $q !== '')
                    {{-- Estado vacío por filtros. --}}
                    <div class="mt-16 mb-8 flex flex-col items-center gap-5 text-center">
                        <span class="flex h-20 w-20 items-center justify-center rounded-full bg-steel-100 text-steel-500">
                            <svg aria-hidden="true" class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </span>
                        <div>
                            <h2 class="text-xl font-semibold text-navy-900">No se encontraron productos con los filtros aplicados</h2>
                            <p class="mt-2 text-sm text-steel-600">Prueba con otra búsqueda o limpia los filtros para ver todos los productos.</p>
                        </div>
                        <a href="{{ route('publica.productos.categoria', $categoria) }}"
                           class="inline-flex items-center justify-center rounded-xl bg-brand-cyan-500 px-6 py-3 text-sm font-semibold uppercase tracking-wide text-navy-900 hover:bg-brand-cyan-400 transition-colors">
                            Limpiar filtros
                        </a>
                    </div>
                @else
                    {{-- Estado vacío real de la categoría. --}}
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
                @endif
            @else
                {{-- Tabla junta de cards de producto (hasta 4 columnas). --}}
                <div class="mt-8 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-px bg-steel-200 border border-steel-200 rounded-2xl overflow-hidden shadow-sm">

                    @foreach ($productos as $producto)
                        @include('publica.partials.card-producto-grande', [
                            'producto' => $producto,
                            'categoria' => $categoria,
                            'junta' => true,
                        ])
                    @endforeach

                </div>

                {{-- Paginación (conserva los filtros activos via query strings). --}}
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

@vite('resources/js/publica/productos-filtros.js')