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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Editar servicio</h1>
        <a href="{{ route('servicios.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </a>
    </div>

    {{-- Form --}}
    <div class="bg-surface rounded-lg border border-main p-6 max-w-lg">
        <form action="{{ route('servicios.update', $servicio) }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')

            {{-- Nombre --}}
            <div class="mb-5">
                <label for="nombre" class="label-main mb-1">
                    Nombre <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    value="{{ old('nombre', $servicio->nombre) }}"
                    autocomplete="off"
                    maxlength="30"
                    data-filter="alphanumeric"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('nombre') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Nombre del servicio"
                >
                <p class="mt-1 text-xs text-secondary">Solo letras y números. Máximo 30 caracteres.</p>
                @error('nombre')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Descripción --}}
            <div class="mb-5">
                <label for="descripcion" class="label-main mb-1">
                    Descripción
                </label>
                <input
                    type="text"
                    id="descripcion"
                    name="descripcion"
                    value="{{ old('descripcion', $servicio->descripcion) }}"
                    autocomplete="off"
                    maxlength="100"
                    data-filter="alphanumeric"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('descripcion') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Descripción del servicio (letras y números)"
                >
                <p class="mt-1 text-xs text-secondary">Solo letras y números. Máximo 100 caracteres.</p>
                @error('descripcion')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Precio --}}
            <div class="mb-6">
                <label for="precio" class="label-main mb-1">
                    Precio <span class="text-red-500">*</span>
                </label>
                <input
                    type="number"
                    id="precio"
                    name="precio"
                    value="{{ old('precio', $servicio->precio) }}"
                    step="0.01"
                    min="0.01"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('precio') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="0.00"
                >
                @error('precio')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Orden --}}
            <div class="mb-5">
                <label for="orden" class="label-main mb-1">
                    Orden en la página pública
                </label>
                <input
                    type="number"
                    id="orden"
                    name="orden"
                    value="{{ old('orden', $servicio->orden) }}"
                    min="0"
                    step="1"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('orden') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="0"
                >
                <p class="mt-1 text-xs text-secondary">Menor número se muestra primero.</p>
                @error('orden')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Icono --}}
            <div class="mb-5">
                <label for="icono" class="label-main mb-1">
                    Ícono
                </label>
                <select
                    id="icono"
                    name="icono"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('icono') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                    <option value="sparkles" @selected(old('icono', $servicio->icono ?? 'sparkles') === 'sparkles')>Sparkles</option>
                    @foreach($iconos as $opcion)
                        @if($opcion !== 'sparkles')
                            <option value="{{ $opcion }}" @selected(old('icono', $servicio->icono) === $opcion)>{{ ucfirst($opcion) }}</option>
                        @endif
                    @endforeach
                </select>
                @error('icono')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Imagen --}}
            <div class="mb-5">
                <label for="imagen" class="label-main mb-1">
                    Imagen
                </label>
                @if($servicio->imagen)
                    <div class="mb-3">
                        <img src="{{ $servicio->imagen }}" alt="Imagen actual de {{ $servicio->nombre }}"
                             class="w-32 h-24 object-cover rounded-lg border border-main">
                    </div>
                @endif
                <input
                    type="file"
                    id="imagen"
                    name="imagen"
                    accept="image/jpeg,image/png,image/webp"
                    class="w-full text-sm text-secondary file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-400 dark:hover:file:bg-blue-900/50 transition-colors
                           {{ $errors->has('imagen') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                <p class="mt-1 text-xs text-secondary">JPG, PNG o WEBP. Máximo 2 MB. Si no eliges una, se conserva la actual.</p>
                @error('imagen')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Activo --}}
            <div class="mb-6">
                <label for="activo" class="flex items-center gap-2 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        id="activo"
                        name="activo"
                        value="1"
                        @checked(old('activo', $servicio->activo))
                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    >
                    <span class="text-sm text-primary">Publicado en la web</span>
                </label>
                <p class="mt-1 text-xs text-secondary">Si está marcado, aparece en la página pública.</p>
                @error('activo')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    aria-label="Guardar cambios del servicio"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar cambios
                </button>
                <a href="{{ route('servicios.index') }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</div>
@endsection
