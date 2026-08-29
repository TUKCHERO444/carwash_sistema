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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Editar automotor</h1>
        <a href="{{ route('automotores.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </a>
    </div>

    {{-- Form --}}
    <div class="bg-surface rounded-lg border border-main p-6 max-w-lg">
        <form id="form-automotor" action="{{ route('automotores.update', $automotor) }}" method="POST" novalidate>
            @csrf
            @method('PUT')

            {{-- Placa --}}
            <div class="mb-5">
                <label for="placa" class="label-main mb-1">
                    Placa <span class="text-red-500">*</span>
                </label>
                <div class="flex gap-2">
                    <input
                        type="text"
                        id="placa"
                        name="placa"
                        value="{{ old('placa', $automotor->placa) }}"
                        maxlength="7"
                        required
                        autocomplete="off"
                        data-filter="alphanumeric"
                        data-length="7"
                        class="flex-1 px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main uppercase
                               {{ $errors->has('placa') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                        placeholder="ABC1234"
                    >
                    <button
                        type="button"
                        id="btn-consultar-placa"
                        aria-label="Consultar placa"
                        class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                        </svg>
                        <span id="label-consultar-placa">Consultar placa</span>
                    </button>
                </div>
                <p class="mt-1 text-xs text-secondary">Letras y números. Máximo 7 caracteres.</p>
                @error('placa')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Cliente --}}
            <div class="mb-5">
                <label for="cliente_id" class="label-main mb-1">
                    Cliente <span class="text-red-500">*</span>
                </label>
                <select
                    id="cliente_id"
                    name="cliente_id"
                    required
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('cliente_id') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                    <option value="">Seleccione un cliente...</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" {{ old('cliente_id', $automotor->cliente_id) == $cliente->id ? 'selected' : '' }}>
                            {{ $cliente->nombre_completo }}
                        </option>
                    @endforeach
                </select>
                @error('cliente_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Marca --}}
            <div class="mb-5">
                <label for="marca" class="label-main mb-1">
                    Marca <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="marca"
                    name="marca"
                    value="{{ old('marca', $automotor->marca) }}"
                    maxlength="100"
                    autocomplete="off"
                    data-filter="alphanumeric"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('marca') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Marca del vehículo"
                >
                <p class="mt-1 text-xs text-secondary">Solo letras y números. Máximo 100 caracteres.</p>
                @error('marca')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Modelo --}}
            <div class="mb-5">
                <label for="modelo" class="label-main mb-1">
                    Modelo <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="modelo"
                    name="modelo"
                    value="{{ old('modelo', $automotor->modelo) }}"
                    maxlength="100"
                    autocomplete="off"
                    data-filter="alphanumeric"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('modelo') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Modelo del vehículo"
                >
                <p class="mt-1 text-xs text-secondary">Solo letras y números. Máximo 100 caracteres.</p>
                @error('modelo')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Serie --}}
            <div class="mb-5">
                <label for="serie" class="label-main mb-1">
                    Serie <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="serie"
                    name="serie"
                    value="{{ old('serie', $automotor->serie) }}"
                    maxlength="100"
                    autocomplete="off"
                    data-filter="alphanumeric"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('serie') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="N° de serie o chasis"
                >
                <p class="mt-1 text-xs text-secondary">Máximo 100 caracteres.</p>
                @error('serie')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Color --}}
            <div class="mb-5">
                <label for="color" class="label-main mb-1">
                    Color <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="color"
                    name="color"
                    value="{{ old('color', $automotor->color) }}"
                    maxlength="50"
                    autocomplete="off"
                    data-filter="letters"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('color') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Color del vehículo"
                >
                <p class="mt-1 text-xs text-secondary">Solo letras. Máximo 50 caracteres.</p>
                @error('color')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Motor --}}
            <div class="mb-6">
                <label for="motor" class="label-main mb-1">
                    Motor <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="motor"
                    name="motor"
                    value="{{ old('motor', $automotor->motor) }}"
                    maxlength="100"
                    autocomplete="off"
                    data-filter="alphanumeric"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('motor') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="N° de motor"
                >
                <p class="mt-1 text-xs text-secondary">Máximo 100 caracteres.</p>
                @error('motor')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Vin --}}
            <div class="mb-6">
                <label for="vin" class="label-main mb-1">
                    Vin <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="vin"
                    name="vin"
                    value="{{ old('vin', $automotor->vin) }}"
                    maxlength="100"
                    autocomplete="off"
                    data-filter="alphanumeric"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('vin') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="N° de VIN del vehículo"
                >
                <p class="mt-1 text-xs text-secondary">Máximo 100 caracteres.</p>
                @error('vin')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    aria-label="Guardar cambios del automotor"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar cambios
                </button>
                <a href="{{ route('automotores.index') }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</div>
@vite('resources/js/automotores/validate.js')
@endsection
