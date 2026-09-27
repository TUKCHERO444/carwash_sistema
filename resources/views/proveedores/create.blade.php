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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Crear Proveedor</h1>
        <a href="{{ route('proveedores.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </a>
    </div>

    {{-- Form --}}
    <div class="bg-surface rounded-lg border border-main p-6 max-w-lg">
        <form id="form-proveedor" action="{{ route('proveedores.store') }}" method="POST" novalidate>
            @csrf

            {{-- RUC --}}
            <div class="mb-5">
                <label for="ruc" class="label-main mb-1">
                    RUC <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="ruc"
                    name="ruc"
                    value="{{ old('ruc') }}"
                    inputmode="numeric"
                    maxlength="11"
                    data-length="11"
                    data-filter="digits"
                    data-validate-length="11"
                    required
                    autocomplete="off"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('ruc') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="20123456789"
                >
                <p class="mt-1 text-xs text-secondary">Exactamente 11 dígitos, sin letras ni guiones.</p>
                @error('ruc')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Razón social --}}
            <div class="mb-5">
                <label for="razon_social" class="label-main mb-1">
                    Razón Social <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="razon_social"
                    name="razon_social"
                    value="{{ old('razon_social') }}"
                    maxlength="150"
                    required
                    autocomplete="off"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('razon_social') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Nombre de la empresa proveedora"
                >
                <p class="mt-1 text-xs text-secondary">Máximo 150 caracteres.</p>
                @error('razon_social')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Dirección --}}
            <div class="mb-5">
                <label for="direccion" class="label-main mb-1">
                    Dirección <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="direccion"
                    name="direccion"
                    value="{{ old('direccion') }}"
                    maxlength="200"
                    autocomplete="off"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('direccion') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Av. Javier Prado Este 4200, Surco"
                >
                <p class="mt-1 text-xs text-secondary">Máximo 200 caracteres.</p>
                @error('direccion')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Estado Tributario --}}
            <div class="mb-5">
                <label for="estado_tributario" class="label-main mb-1">
                    Estado Tributario <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="estado_tributario"
                    name="estado_tributario"
                    value="{{ old('estado_tributario') }}"
                    maxlength="100"
                    autocomplete="off"
                    data-filter="letters"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('estado_tributario') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Activo"
                >
                <p class="mt-1 text-xs text-secondary">Solo letras. Es el estado que reporta la consulta de RUC.</p>
                @error('estado_tributario')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Condición --}}
            <div class="mb-5">
                <label for="condicion" class="label-main mb-1">
                    Condición <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="condicion"
                    name="condicion"
                    value="{{ old('condicion') }}"
                    maxlength="100"
                    autocomplete="off"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('condicion') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Nuevo Contributor"
                >
                <p class="mt-1 text-xs text-secondary">Máximo 100 caracteres.</p>
                @error('condicion')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Estado --}}
            <div class="mb-6">
                <label for="estado" class="label-main mb-1">
                    Estado <span class="text-red-500">*</span>
                </label>
                <select
                    id="estado"
                    name="estado"
                    required
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('estado') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                    <option value="1" @selected(old('estado', '1') === '1')>1 — Activo</option>
                    <option value="0" @selected(old('estado') === '0')>0 — Inactivo</option>
                </select>
                <p class="mt-1 text-xs text-secondary">Un solo dígito: 1 activo, 0 inactivo.</p>
                @error('estado')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Buttons --}}
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    aria-label="Guardar nuevo proveedor"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar
                </button>
                <a href="{{ route('proveedores.index') }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</div>
@vite('resources/js/proveedores/validate.js')
@endsection
