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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Crear cliente</h1>
        <a href="{{ route('clientes.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </a>
    </div>

    {{-- Form --}}
    <div class="bg-surface rounded-lg border border-main p-6 max-w-lg">
        <form id="form-cliente" action="{{ route('clientes.store') }}" method="POST" novalidate>
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
                        maxlength="8"
                        required
                        autocomplete="off"
                        inputmode="numeric"
                        data-filter="digits"
                        data-length="8"
                        data-validate-length="8"
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

            {{-- Nombres --}}
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
                    required
                    data-filter="letters"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('nombre') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Nombres del cliente"
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
                    required
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
                    required
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

            {{-- Teléfono --}}
            <div class="mb-5">
                <label for="telefono" class="label-main mb-1">
                    Teléfono <span class="text-text-secondary-dark text-xs font-normal">(Opcional)</span>
                </label>
                <input
                    type="text"
                    id="telefono"
                    name="telefono"
                    value="{{ old('telefono') }}"
                    maxlength="9"
                    autocomplete="off"
                    inputmode="numeric"
                    data-filter="digits"
                    data-length="9"
                    data-validate-length="9"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('telefono') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="987654321"
                >
                <p class="mt-1 text-xs text-secondary">Exactamente 9 dígitos, solo números.</p>
                @error('telefono')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    aria-label="Guardar nuevo cliente"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar
                </button>
                <a href="{{ route('clientes.index') }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</div>
@vite('resources/js/clientes/validate.js')
@endsection
