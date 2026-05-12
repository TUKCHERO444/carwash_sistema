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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Crear producto</h1>
        <a href="{{ route('productos.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </a>
    </div>

    {{-- Form --}}
    <div class="bg-surface rounded-lg border border-main p-6 max-w-lg">
        <form action="{{ route('productos.store') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf

            {{-- Nombre --}}
            <div class="mb-5">
                <label for="nombre" class="label-main mb-1">
                    Nombre <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    value="{{ old('nombre') }}"
                    autocomplete="off"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('nombre') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Nombre del producto"
                >
                @error('nombre')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Categoría --}}
            <div class="mb-5">
                <label for="categoria_id" class="label-main mb-1">
                    Categoría
                </label>
                <select
                    id="categoria_id"
                    name="categoria_id"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('categoria_id') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                    <option value="">Sin categoría</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ old('categoria_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('categoria_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Precio Compra --}}
            <div class="mb-5">
                <label for="precio_compra" class="label-main mb-1">
                    Precio de compra <span class="text-red-500">*</span>
                </label>
                <input
                    type="number"
                    id="precio_compra"
                    name="precio_compra"
                    value="{{ old('precio_compra') }}"
                    step="0.01"
                    min="0.01"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('precio_compra') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="0.00"
                >
                @error('precio_compra')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Precio Venta --}}
            <div class="mb-5">
                <label for="precio_venta" class="label-main mb-1">
                    Precio de venta <span class="text-red-500">*</span>
                </label>
                <input
                    type="number"
                    id="precio_venta"
                    name="precio_venta"
                    value="{{ old('precio_venta') }}"
                    step="0.01"
                    min="0.01"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('precio_venta') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="0.00"
                >
                @error('precio_venta')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Stock --}}
            <div class="mb-5">
                <label for="stock" class="label-main mb-1">
                    Stock <span class="text-red-500">*</span>
                </label>
                <input
                    type="number"
                    id="stock"
                    name="stock"
                    value="{{ old('stock', 0) }}"
                    min="0"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('stock') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="0"
                >
                @error('stock')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Inventario --}}
            <div class="mb-5">
                <label for="inventario" class="label-main mb-1">
                    Inventario <span class="text-red-500">*</span>
                </label>
                <input
                    type="number"
                    id="inventario"
                    name="inventario"
                    value="{{ old('inventario', 0) }}"
                    min="0"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('inventario') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="0"
                >
                @error('inventario')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Foto --}}
            <div class="mb-6">
                <label for="foto" class="label-main mb-1">
                    Foto del producto
                </label>
                <input
                    type="file"
                    id="foto"
                    name="foto"
                    accept="image/jpeg,image/jpg,image/png,image/webp"
                    class="w-full text-sm text-secondary file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-gray-100 dark:file:bg-slate-800 file:text-primary dark:file:text-text-primary-dark hover:file:bg-gray-200 dark:hover:file:bg-slate-700 transition-colors
                           {{ $errors->has('foto') ? 'border border-red-400 rounded-lg bg-red-50 dark:bg-red-900/20 p-1' : '' }}"
                >
                <p class="mt-1 text-xs text-secondary">JPG, PNG o WebP. Máximo 2 MB.</p>
                @error('foto')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror

                {{-- Preview --}}
                <div id="bloque-preview" class="hidden mt-3 p-2 border border-main rounded-lg bg-gray-50 dark:bg-slate-800/50 inline-block">
                    <p class="text-[10px] uppercase font-bold text-secondary mb-1">Vista previa</p>
                    <img id="preview-foto" src="#" alt="Vista previa" class="w-32 h-32 object-cover rounded shadow-sm border border-main">
                </div>
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    aria-label="Guardar nuevo producto"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar
                </button>
                <a href="{{ route('productos.index') }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</div>

@vite('resources/js/productos/create.js')
@endsection
