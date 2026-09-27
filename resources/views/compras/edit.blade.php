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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Editar Compra</h1>
        <a href="{{ route('compras.show', $compra) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </a>
    </div>

    {{-- Form container --}}
    <div class="bg-surface rounded-lg border border-main p-6">
        <form id="form-compra" action="{{ route('compras.update', $compra) }}" method="POST" novalidate>
            @csrf
            @method('PUT')

            {{-- Validation error for detalle --}}
            @error('detalle')
                <div id="detalle-error" role="alert" class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-400 text-sm text-red-600">
                    {{ $message }}
                </div>
            @enderror

            {{-- ── Proveedor ── --}}
            <div class="mb-5">
                <label for="proveedor_id" class="label-main mb-1">
                    Proveedor <span class="text-red-500">*</span>
                </label>
                <select
                    id="proveedor_id"
                    name="proveedor_id"
                    required
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('proveedor_id') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}">
                    <option value="">Seleccione un proveedor</option>
                    @foreach($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}" {{ $compra->proveedor_id == $proveedor->id ? 'selected' : '' }}>
                            {{ $proveedor->razon_social }} ({{ $proveedor->ruc }})
                        </option>
                    @endforeach
                </select>
                @error('proveedor_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Fecha y documento ── --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                <div>
                    <label for="fecha" class="label-main mb-1">
                        Fecha <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        id="fecha"
                        name="fecha"
                        value="{{ old('fecha', $compra->fecha->format('Y-m-d')) }}"
                        required
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                               {{ $errors->has('fecha') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}">
                    @error('fecha')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="tipo_documento" class="label-main mb-1">
                        Tipo de Documento
                    </label>
                    <select
                        id="tipo_documento"
                        name="tipo_documento"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                               {{ $errors->has('tipo_documento') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}">
                        <option value="">Seleccione...</option>
                        <option value="Factura" {{ old('tipo_documento', $compra->tipo_documento) === 'Factura' ? 'selected' : '' }}>Factura</option>
                        <option value="Boleta" {{ old('tipo_documento', $compra->tipo_documento) === 'Boleta' ? 'selected' : '' }}>Boleta</option>
                        <option value="Ticket" {{ old('tipo_documento', $compra->tipo_documento) === 'Ticket' ? 'selected' : '' }}>Ticket</option>
                        <option value="Nota de venta" {{ old('tipo_documento', $compra->tipo_documento) === 'Nota de venta' ? 'selected' : '' }}>Nota de venta</option>
                        <option value="Guia de remision" {{ old('tipo_documento', $compra->tipo_documento) === 'Guia de remision' ? 'selected' : '' }}>Guía de remisión</option>
                        <option value="Otros" {{ old('tipo_documento', $compra->tipo_documento) === 'Otros' ? 'selected' : '' }}>Otros</option>
                    </select>
                    @error('tipo_documento')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                <div>
                    <label for="numero_documento" class="label-main mb-1">
                        Nº Documento
                    </label>
                    <input
                        type="text"
                        id="numero_documento"
                        name="numero_documento"
                        value="{{ old('numero_documento', $compra->numero_documento) }}"
                        maxlength="50"
                        autocomplete="off"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                               {{ $errors->has('numero_documento') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                        placeholder="F001-000123">
                    @error('numero_documento')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- ── Observaciones ── --}}
            <div class="mb-5">
                <label for="observaciones" class="label-main mb-1">
                    Observaciones <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <textarea
                    id="observaciones"
                    name="observaciones"
                    rows="2"
                    maxlength="500"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('observaciones') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Notas adicionales sobre la compra...">{{ old('observaciones', $compra->observaciones) }}</textarea>
                @error('observaciones')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Detalle de productos (líneas dinámicas) ── --}}
            <div class="mb-6">
                <div class="flex items-center justify-between mb-3">
                    <label class="label-main mb-0">Detalle de productos <span class="text-red-500">*</span></label>
                    <button type="button" id="btn-agregar-linea"
                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs font-medium rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Agregar línea
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table id="tabla-detalle"
                           data-validate-table
                           data-validate-min-rows="1"
                           data-validate-error-id="error-detalle"
                           class="min-w-full divide-y divide-main">
                        <thead class="bg-gray-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                                    Producto
                                </th>
                                <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                                    Cantidad
                                </th>
                                <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                                    Costo Unit.
                                </th>
                                <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                                    Subtotal
                                </th>
                                <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                                    Eliminar
                                </th>
                            </tr>
                        </thead>
                        <tbody id="tbody-detalle" class="bg-surface divide-y divide-main">
                            {{-- Filas renderizadas por JS --}}
                        </tbody>
                    </table>
                    <p id="error-detalle" class="hidden mt-2 text-xs text-red-600 dark:text-red-400"></p>
                </div>
            </div>

            {{-- ── Totales (solo lectura, calculados en JS y servidor) ── --}}
            <div class="mb-6 max-w-sm space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="subtotal" class="label-main mb-1">Subtotal</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-secondary">S/</span>
                            <input
                                type="number"
                                id="subtotal"
                                name="subtotal"
                                step="0.01"
                                readonly
                                class="w-full pl-8 pr-3 py-2 border rounded-lg text-sm focus:outline-none input-main bg-slate-50 dark:bg-slate-800/50"
                                value="0.00">
                        </div>
                    </div>
                    <div>
                        <label for="total" class="label-main mb-1">Total</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-secondary">S/</span>
                            <input
                                type="number"
                                id="total"
                                name="total"
                                step="0.01"
                                readonly
                                class="w-full pl-8 pr-3 py-2 border rounded-lg text-sm focus:outline-none input-main bg-slate-50 dark:bg-slate-800/50 font-medium"
                                value="0.00">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Buttons ── --}}
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    aria-label="Actualizar compra"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Actualizar
                </button>
                <a href="{{ route('compras.show', $compra) }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

    {{-- Data para el JS (productos activos) --}}
    <script>
        window.productos = @json($productos ?? []);
    </script>

</div>
@vite('resources/js/compras/edit.js')
@endsection