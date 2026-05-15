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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Nuevo Ingreso</h1>
        <a href="{{ route('ingresos.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </a>
    </div>

    {{-- Form container --}}
    <div class="bg-surface rounded-lg border border-main p-6">
        <form id="form-ingreso" action="{{ route('ingresos.store') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf

            {{-- Validation error for servicios --}}
            @error('servicios')
                <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-400 text-sm text-red-600">
                    {{ $message }}
                </div>
            @enderror

            {{-- ── Vehículo ── --}}
            <div class="mb-5">
                <label for="vehiculo_id" class="label-main mb-1">
                    Vehículo <span class="text-red-500">*</span>
                </label>
                <select
                    id="vehiculo_id"
                    name="vehiculo_id"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('vehiculo_id') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                    <option value="" data-precio="0">-- Seleccione un vehículo --</option>
                    @foreach($vehiculos as $vehiculo)
                        <option
                            value="{{ $vehiculo->id }}"
                            data-precio="{{ $vehiculo->precio }}"
                            {{ old('vehiculo_id') == $vehiculo->id ? 'selected' : '' }}
                        >
                            {{ $vehiculo->nombre }} — S/ {{ number_format($vehiculo->precio, 2) }}
                        </option>
                    @endforeach
                </select>
                @error('vehiculo_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Placa ── --}}
            <div class="mb-5">
                <label for="placa" class="label-main mb-1">
                    Placa <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="placa"
                    name="placa"
                    required
                    maxlength="7"
                    value="{{ old('placa') }}"
                    placeholder="Ej: ABC-123"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('placa') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                @error('placa')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Nombre ── --}}
            <div class="mb-5">
                <label for="nombre" class="label-main mb-1">
                    Nombre <span class="text-text-secondary-dark font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    value="{{ old('nombre') }}"
                    placeholder="Nombre del cliente"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('nombre') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                @error('nombre')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── DNI ── --}}
            <div class="mb-5">
                <label for="dni" class="label-main mb-1">
                    DNI <span class="text-text-secondary-dark font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="dni"
                    name="dni"
                    value="{{ old('dni') }}"
                    placeholder="DNI del cliente"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('dni') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                @error('dni')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Teléfono ── --}}
            <div class="mb-5">
                <label for="telefono" class="label-main mb-1">
                    Teléfono <span class="text-text-secondary-dark font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="telefono"
                    name="telefono"
                    value="{{ old('telefono') }}"
                    placeholder="Teléfono del cliente"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('telefono') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                @error('telefono')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Resumen del Cliente ── --}}
            <div id="cliente-summary-container" class="mb-5 hidden">
            </div>

            {{-- ── Fecha ── --}}
            <div class="mb-5">
                <label for="fecha" class="label-main mb-1">
                    Fecha <span class="text-red-500">*</span>
                </label>
                <input
                    type="date"
                    id="fecha"
                    name="fecha"
                    required
                    value="{{ old('fecha', date('Y-m-d')) }}"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('fecha') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                @error('fecha')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Foto ── --}}
            <div class="mb-5">
                <label for="foto" class="label-main mb-1">
                    Foto del vehículo <span class="text-text-secondary-dark font-normal">(opcional)</span>
                </label>
                <input
                    type="file"
                    id="foto"
                    name="foto"
                    accept="image/*"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('foto') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                @error('foto')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <img id="foto-preview" src="" alt="Vista previa" class="hidden mt-3 rounded-lg max-h-48 object-cover border border-gray-200">
            </div>

            {{-- ── Trabajadores ── --}}
            <div class="mb-5">
                <label class="label-main mb-2">
                    Trabajadores <span class="text-red-500">*</span>
                    <span class="text-xs font-normal text-text-secondary-dark">(al menos 1)</span>
                </label>
                @error('trabajadores_ids')
                    <p class="mb-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach($trabajadores as $trabajador)
                        <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-slate-800/50
                                      {{ in_array($trabajador->id, old('trabajadores_ids', [])) ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-main' }}
                                      trabajador-option">
                            <input
                                type="checkbox"
                                name="trabajadores_ids[]"
                                value="{{ $trabajador->id }}"
                                data-validate-group="trabajadores"
                                data-validate-min="1"
                                class="trabajador-checkbox w-4 h-4 text-blue-600 border-main rounded focus:ring-blue-500 dark:bg-slate-800"
                                {{ in_array($trabajador->id, old('trabajadores_ids', [])) ? 'checked' : '' }}
                            >
                            <span class="text-sm text-secondary">{{ $trabajador->nombre }}</span>
                        </label>
                    @endforeach
                </div>
                <div id="error-container-trabajadores"></div>
                <p id="error-trabajadores" class="hidden mt-1 text-xs text-red-600">Debe seleccionar al menos un trabajador.</p>
            </div>

            {{-- ── Búsqueda de servicios ── --}}
            <div class="mb-6">
                <label for="buscar-servicio" class="label-main mb-1">
                    Buscar servicio
                </label>
                <div class="relative">
                    <input
                        type="text"
                        id="buscar-servicio"
                        autocomplete="off"
                        placeholder="Escribe el nombre del servicio..."
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main"
                    >
                    <div id="resultados-busqueda"
                         class="hidden absolute z-10 w-full mt-1 bg-surface border border-main rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    </div>
                </div>
            </div>

            {{-- ── Tabla de servicios ── --}}
            <div class="mb-6 overflow-x-auto">
                <table id="tabla-servicios" 
                       data-validate-table 
                       data-validate-min-rows="1" 
                       data-validate-error-id="error-servicios"
                       class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Servicio</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Eliminar</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-servicios" class="bg-surface divide-y divide-main">
                        {{-- Rows rendered by JS --}}
                    </tbody>
                </table>
                <p id="error-servicios" class="hidden mt-2 text-xs text-red-600 dark:text-red-400"></p>
            </div>

            {{-- ── Precio estimado (solo indicador visual) ── --}}
            <div class="mb-6 max-w-sm">
                <label class="label-main mb-1">Precio estimado</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-secondary">S/</span>
                    <input
                        type="text"
                        id="precio-display"
                        readonly
                        value="0.00"
                        class="w-full pl-8 pr-3 py-2 border rounded-lg text-sm focus:outline-none input-main bg-slate-50 dark:bg-slate-800/50"
                    >
                </div>
                <p class="mt-1 text-xs text-text-secondary-dark">Referencial. El precio final se calculará al guardar.</p>
            </div>

            {{-- ── Submit ── --}}
            <div class="flex items-center gap-3 mt-6">
                <button
                    type="submit"
                    aria-label="Registrar ingreso"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Registrar ingreso
                </button>
                <a href="{{ route('ingresos.index') }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</div>

@vite('resources/js/ingresos/create.js')
@endsection
