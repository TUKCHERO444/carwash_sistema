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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Crear trabajador</h1>
        <a href="{{ route('trabajadores.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </a>
    </div>

    {{-- Form --}}
    <div class="bg-surface rounded-lg border border-main p-6 max-w-lg">
        <form action="{{ route('trabajadores.store') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf

            {{-- DNI --}}
            <div class="mb-5">
                <label for="dni" class="label-main mb-1">
                    DNI <span class="text-red-500">*</span>
                </label>
                <div class="flex gap-2">
                    <input
                        type="text"
                        id="dni"
                        name="dni"
                        value="{{ old('dni') }}"
                        autocomplete="off"
                        maxlength="8"
                        inputmode="numeric"
                        data-filter="digits"
                        data-length="8"
                        class="flex-1 px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                               {{ $errors->has('dni') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                        placeholder="12345678"
                    >
                    <button
                        type="button"
                        id="btn-consultar-dni"
                        aria-label="Consultar DNI"
                        class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                        </svg>
                        <span id="label-consultar-dni">Consultar DNI</span>
                    </button>
                </div>
                <p class="mt-1 text-xs text-secondary">Exactamente 8 dígitos, solo números.</p>
                @error('dni')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Nombre --}}
            <div class="mb-5">
                <label for="nombre" class="label-main mb-1">
                    Nombres <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    value="{{ old('nombre') }}"
                    autocomplete="off"
                    maxlength="50"
                    data-filter="letters"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('nombre') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Nombres del trabajador"
                >
                <p class="mt-1 text-xs text-secondary">Solo letras. Máximo 50 caracteres.</p>
                @error('nombre')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Apellido Paterno --}}
            <div class="mb-5">
                <label for="apellido_paterno" class="label-main mb-1">
                    Apellido paterno <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="apellido_paterno"
                    name="apellido_paterno"
                    value="{{ old('apellido_paterno') }}"
                    autocomplete="off"
                    maxlength="50"
                    data-filter="letters"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('apellido_paterno') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Apellido paterno"
                >
                <p class="mt-1 text-xs text-secondary">Solo letras. Máximo 50 caracteres.</p>
                @error('apellido_paterno')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Apellido Materno --}}
            <div class="mb-5">
                <label for="apellido_materno" class="label-main mb-1">
                    Apellido materno <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="apellido_materno"
                    name="apellido_materno"
                    value="{{ old('apellido_materno') }}"
                    autocomplete="off"
                    maxlength="50"
                    data-filter="letters"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('apellido_materno') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Apellido materno"
                >
                <p class="mt-1 text-xs text-secondary">Solo letras. Máximo 50 caracteres.</p>
                @error('apellido_materno')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Foto --}}
            <div class="mb-5">
                <label for="foto" class="label-main mb-1">
                    Foto del trabajador
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

            {{-- Estado --}}
            <div class="mb-6">
                <label for="estado" class="label-main mb-1">
                    Estado <span class="text-red-500">*</span>
                </label>
                <select
                    id="estado"
                    name="estado"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('estado') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                    <option value="1" {{ old('estado', '1') === '1' ? 'selected' : '' }}>Activo</option>
                    <option value="0" {{ old('estado') === '0' ? 'selected' : '' }}>Inactivo</option>
                </select>
                @error('estado')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    aria-label="Guardar nuevo trabajador"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar
                </button>
                <a href="{{ route('trabajadores.index') }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</div>
@vite('resources/js/trabajadores/create.js')
@endsection
