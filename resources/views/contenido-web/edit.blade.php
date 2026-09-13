@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Flash messages --}}
    @if(session('success'))
        <div role="alert" class="mb-4 px-4 py-3 rounded-lg bg-green-100 text-green-800 border border-green-200 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div role="alert" class="mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-200 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Contenido de la web</h1>
            <p class="mt-1 text-sm text-secondary">Controla qué secciones y textos se muestran en la página pública. Todo se publica al guardar.</p>
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('contenido-web.update') }}" method="POST" class="space-y-6" novalidate>
        @csrf
        @method('PUT')

        {{-- ===================== Sección: Inicio ===================== --}}
        <div class="bg-surface rounded-lg border border-main p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-text-primary-dark">Página de inicio</h2>
            <p class="mt-1 text-sm text-secondary">Muestra u oculta cada sección de la portada.</p>

            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach([
                    'inicio_mostrar_marcas' => 'Mostrar marcas',
                    'inicio_mostrar_servicios' => 'Mostrar servicios',
                    'inicio_mostrar_productos' => 'Mostrar productos destacados',
                    'inicio_mostrar_proyecto' => 'Mostrar video / proyecto',
                ] as $clave => $etiqueta)
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        name="{{ $clave }}"
                        value="1"
                        @checked($contenido->boolValue($clave))
                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    >
                    <span class="text-sm text-primary">{{ $etiqueta }}</span>
                </label>
                @endforeach
            </div>
        </div>

        {{-- ===================== Sección: Productos ===================== --}}
        <div class="bg-surface rounded-lg border border-main p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-text-primary-dark">Página de productos</h2>
            <p class="mt-1 text-sm text-secondary">El mosaico muestra las categorías con productos activos.</p>

            <div class="mt-5">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        name="productos_mostrar_mosaico"
                        value="1"
                        @checked($contenido->boolValue('productos_mostrar_mosaico'))
                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    >
                    <span class="text-sm text-primary">Mostrar mosaico de categorías</span>
                </label>
            </div>

            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="productos_titulo" class="label-main mb-1">Título</label>
                    <input
                        type="text"
                        id="productos_titulo"
                        name="productos_titulo"
                        value="{{ $contenido->textValue('productos_titulo') }}"
                        maxlength="120"
                        autocomplete="off"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                               {{ $errors->has('productos_titulo') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    >
                    <p class="mt-1 text-xs text-secondary">Máximo 120 caracteres.</p>
                    @error('productos_titulo')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="productos_intro" class="label-main mb-1">Introducción</label>
                    <input
                        type="text"
                        id="productos_intro"
                        name="productos_intro"
                        value="{{ $contenido->textValue('productos_intro') }}"
                        maxlength="120"
                        autocomplete="off"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                               {{ $errors->has('productos_intro') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    >
                    <p class="mt-1 text-xs text-secondary">Máximo 120 caracteres.</p>
                    @error('productos_intro')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ===================== Sección: Servicios ===================== --}}
        <div class="bg-surface rounded-lg border border-main p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-text-primary-dark">Página de servicios</h2>
            <p class="mt-1 text-sm text-secondary">Los servicios publicados se controlan desde el módulo Servicios.</p>

            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="servicios_titulo" class="label-main mb-1">Título</label>
                    <input
                        type="text"
                        id="servicios_titulo"
                        name="servicios_titulo"
                        value="{{ $contenido->textValue('servicios_titulo') }}"
                        maxlength="120"
                        autocomplete="off"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                               {{ $errors->has('servicios_titulo') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    >
                    <p class="mt-1 text-xs text-secondary">Máximo 120 caracteres.</p>
                    @error('servicios_titulo')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="servicios_intro" class="label-main mb-1">Introducción</label>
                    <input
                        type="text"
                        id="servicios_intro"
                        name="servicios_intro"
                        value="{{ $contenido->textValue('servicios_intro') }}"
                        maxlength="120"
                        autocomplete="off"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                               {{ $errors->has('servicios_intro') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    >
                    <p class="mt-1 text-xs text-secondary">Máximo 120 caracteres.</p>
                    @error('servicios_intro')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ===================== Sección: Marcas ===================== --}}
        <div class="bg-surface rounded-lg border border-main p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-text-primary-dark">Página de marcas</h2>
            <p class="mt-1 text-sm text-secondary">Escoge las marcas que se muestran y su orden. Sin marcas seleccionadas se muestran todas por nombre.</p>

            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="marcas_titulo" class="label-main mb-1">Título</label>
                    <input
                        type="text"
                        id="marcas_titulo"
                        name="marcas_titulo"
                        value="{{ $contenido->textValue('marcas_titulo') }}"
                        maxlength="120"
                        autocomplete="off"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                               {{ $errors->has('marcas_titulo') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    >
                    <p class="mt-1 text-xs text-secondary">Máximo 120 caracteres.</p>
                    @error('marcas_titulo')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="marcas_intro" class="label-main mb-1">Introducción</label>
                    <input
                        type="text"
                        id="marcas_intro"
                        name="marcas_intro"
                        value="{{ $contenido->textValue('marcas_intro') }}"
                        maxlength="120"
                        autocomplete="off"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                               {{ $errors->has('marcas_intro') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    >
                    <p class="mt-1 text-xs text-secondary">Máximo 120 caracteres.</p>
                    @error('marcas_intro')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Curaduría de marcas --}}
            @php
                $estado = $contenido->estadoMarcasWeb();
                $marcasPanel = $marcas->sortBy(function ($marca) use ($estado) {
                    return $estado[$marca->id] ?? PHP_INT_MAX;
                })->values();
            @endphp

            <div class="mt-6">
                <p class="label-main mb-2">Marcas visibles en la web</p>
                <div data-curaduria-list class="space-y-2 max-h-80 overflow-y-auto pr-1">
                    @forelse ($marcasPanel as $marca)
                        <div data-curaduria-row class="flex items-center gap-3 rounded-lg border border-main bg-white dark:bg-slate-900 px-3 py-2">
                            <input
                                type="checkbox"
                                name="marcas_web[]"
                                value="{{ $marca->id }}"
                                @checked(array_key_exists($marca->id, $estado))
                                class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            >
                            <span class="flex-1 text-sm text-primary truncate">{{ $marca->nombre }}</span>
                            <input
                                type="number"
                                name="marcas_orden[{{ $marca->id }}]"
                                value="{{ $estado[$marca->id] ?? '' }}"
                                min="0"
                                step="1"
                                aria-label="Orden de {{ $marca->nombre }}"
                                class="w-20 px-2 py-1 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main"
                            >
                            <button type="button" data-curaduria-up aria-label="Subir {{ $marca->nombre }}"
                                    class="p-1.5 rounded-md text-secondary hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                </svg>
                            </button>
                            <button type="button" data-curaduria-down aria-label="Bajar {{ $marca->nombre }}"
                                    class="p-1.5 rounded-md text-secondary hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                        </div>
                    @empty
                        <p class="text-sm text-secondary">Aún no hay marcas registradas.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-3">
            <button
                type="submit"
                aria-label="Guardar contenidos de la web"
                class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Guardar cambios
            </button>
        </div>

    </form>

</div>

@vite(['resources/js/contenido-web/edit.js'])
@endsection