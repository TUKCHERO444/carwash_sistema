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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Crear marca</h1>
        <a href="{{ route('marcas.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </a>
    </div>

    {{-- Form --}}
    <div class="bg-surface rounded-lg border border-main p-6 max-w-lg">
        <form id="form-marca" action="{{ route('marcas.store') }}" method="POST" enctype="multipart/form-data" novalidate>
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
                    required
                    autocomplete="off"
                    data-filter="letters"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('nombre') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Nombre de la marca"
                >
                <p class="mt-1 text-xs text-secondary">Solo letras.</p>
                @error('nombre')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Descripción --}}
            <div class="mb-6">
                <label for="descripcion" class="label-main mb-1">
                    Descripción <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <textarea
                    id="descripcion"
                    name="descripcion"
                    maxlength="100"
                    rows="3"
                    data-filter="alphanumeric"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('descripcion') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Descripción de la marca..."
                >{{ old('descripcion') }}</textarea>
                <p class="mt-1 text-xs text-secondary">Solo letras y números. Máximo 100 caracteres.</p>
                @error('descripcion')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Foto --}}
            <div class="mb-6">
                <label for="foto" class="label-main mb-1">
                    Foto de la marca
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
                    aria-label="Guardar nueva marca"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar
                </button>
                <a href="{{ route('marcas.index') }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</div>
@vite('resources/js/marcas/validate.js')
@endsection
