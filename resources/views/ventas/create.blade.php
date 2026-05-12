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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Nueva venta</h1>
        <a href="{{ route('ventas.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </a>
    </div>

    {{-- Form container --}}
    <div class="bg-surface rounded-lg border border-main p-6">
        <form id="form-venta" action="{{ route('ventas.store') }}" method="POST" novalidate>
            @csrf

            {{-- Validation error for productos --}}
            @error('productos')
                <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-400 text-sm text-red-600">
                    {{ $message }}
                </div>
            @enderror

            {{-- ── Search section ── --}}
            <div class="mb-6">
                <label for="buscar-producto" class="label-main mb-1">
                    Buscar producto
                </label>
                <div class="relative">
                    <input
                        type="text"
                        id="buscar-producto"
                        autocomplete="off"
                        placeholder="Escribe el nombre del producto..."
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main"
                    >
                    <div id="resultados-busqueda"
                         class="hidden absolute z-10 w-full mt-1 bg-surface border border-main rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    </div>
                </div>
            </div>

            {{-- ── Detail table ── --}}
            <div class="mb-6 overflow-x-auto">
                <table id="tabla-detalle" class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Producto
                            </th>
                            <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Cantidad
                            </th>
                            <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Precio Unit.
                            </th>
                            <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Subtotal
                            </th>
                            <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Eliminar
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detalle" class="bg-surface divide-y divide-main">
                        {{-- Rows rendered by JS --}}
                    </tbody>
                </table>
            </div>

            {{-- ── Totals section ── --}}
            <div class="mb-6 max-w-sm space-y-4">

                {{-- Subtotal (read-only) --}}
                <div>
                    <label for="subtotal" class="label-main mb-1">
                        Subtotal
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-secondary">S/</span>
                        <input
                            type="number"
                            id="subtotal"
                            name="subtotal"
                            step="0.01"
                            readonly
                            class="w-full pl-8 pr-3 py-2 border rounded-lg text-sm focus:outline-none input-main bg-slate-50 dark:bg-slate-800/50"
                            value="0.00"
                        >
                    </div>
                </div>

                {{-- Discount toggle --}}
                <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            id="toggle-descuento"
                            class="w-4 h-4 text-blue-600 border-main rounded focus:ring-blue-500 dark:bg-slate-800"
                        >
                        <span class="text-sm text-secondary">Aplicar descuento por porcentaje</span>
                    </label>
                </div>

                {{-- Percentage field (hidden by default) --}}
                <div id="campo-porcentaje" class="hidden">
                    <label for="porcentaje" class="label-main mb-1">
                        Porcentaje de descuento (%)
                    </label>
                    <input
                        type="number"
                        id="porcentaje"
                        min="0"
                        max="100"
                        step="0.01"
                        placeholder="0.00"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main"
                    >
                    <p id="error-porcentaje" class="hidden mt-1 text-xs text-red-600">El porcentaje no puede superar 100.</p>
                </div>

                {{-- Descuento manual --}}
                <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="toggle-descuento-manual"
                               class="w-4 h-4 text-blue-600 border-main rounded focus:ring-blue-500 dark:bg-slate-800">
                        <span class="text-sm text-secondary">Aplicar descuento manual</span>
                    </label>
                </div>

                {{-- Total (editable) --}}
                <div>
                    <label for="total" class="label-main mb-1">
                        Total <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-secondary">S/</span>
                        <input
                            type="number"
                            id="total"
                            name="total"
                            step="0.01"
                            readonly
                            class="w-full pl-8 pr-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main bg-slate-50 dark:bg-slate-800/50
                                   {{ $errors->has('total') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                            value="{{ old('total', '0.00') }}"
                        >
                    </div>
                    @error('total')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- ── Método de pago ── --}}
                <div>
                    <label class="label-main mb-2">
                        Método de pago <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto'] as $val => $label)
                            <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer transition-colors
                                          metodo-pago-option {{ old('metodo_pago', 'efectivo') === $val ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-main hover:border-gray-400' }}">
                                <input type="radio" name="metodo_pago" value="{{ $val }}"
                                       class="hidden metodo-pago-radio"
                                       {{ old('metodo_pago', 'efectivo') === $val ? 'checked' : '' }}>
                                <span class="text-sm font-medium {{ old('metodo_pago', 'efectivo') === $val ? 'text-blue-700 dark:text-blue-400' : 'text-secondary' }}">
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('metodo_pago')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Campos mixto --}}
                <div id="bloque-mixto" class="hidden space-y-3">
                    <p class="text-xs text-secondary">Ingresa los montos por método (al menos uno). La suma debe igualar el total.</p>
                    <div id="alerta-mixto" class="hidden px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-800 text-amber-800 dark:text-amber-400 text-xs">
                        La suma de los montos (<span id="suma-mixto-display">0.00</span>) no coincide con el total (<span id="total-mixto-display">0.00</span>).
                    </div>
                    <div>
                        <label for="monto_efectivo" class="block text-xs font-medium text-secondary mb-1">Efectivo (S/)</label>
                        <input type="number" id="monto_efectivo" name="monto_efectivo" step="0.01" min="0"
                               value="{{ old('monto_efectivo') }}" placeholder="0.00"
                               class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 input-main">
                    </div>
                    <div>
                        <label for="monto_yape" class="block text-xs font-medium text-secondary mb-1">Yape (S/)</label>
                        <input type="number" id="monto_yape" name="monto_yape" step="0.01" min="0"
                               value="{{ old('monto_yape') }}" placeholder="0.00"
                               class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 input-main">
                    </div>
                    <div>
                        <label for="monto_izipay" class="block text-xs font-medium text-secondary mb-1">Izipay (S/)</label>
                        <input type="number" id="monto_izipay" name="monto_izipay" step="0.01" min="0"
                               value="{{ old('monto_izipay') }}" placeholder="0.00"
                               class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 input-main">
                    </div>
                </div>

            </div>

            {{-- ── Observation field ── --}}
            <div class="mb-6">
                <label for="observacion" class="label-main mb-1">
                    Observación <span class="text-text-secondary-dark font-normal">(opcional)</span>
                </label>
                <textarea
                    id="observacion"
                    name="observacion"
                    rows="3"
                    placeholder="Notas adicionales sobre la venta..."
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('observacion') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >{{ old('observacion') }}</textarea>
                @error('observacion')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Submit button ── --}}
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    aria-label="Registrar venta"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Registrar venta
                </button>
                <a href="{{ route('ventas.index') }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</div>

@vite('resources/js/ventas/create.js')
@if(session('error_caja'))
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="relative inline-block align-bottom bg-surface rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md w-full border border-main">
            <div class="bg-surface px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-primary" id="modal-title">Caja Cerrada</h3>
                        <div class="mt-2">
                            <p class="text-sm text-secondary">No puedes registrar cobros porque no hay ninguna sesión de caja activa. Debes iniciar la caja primero.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 dark:bg-slate-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-main">
                <a href="{{ route('caja.index') }}" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">
                    Ir a Caja
                </a>
                <a href="{{ route('ventas.index') }}" class="mt-3 w-full inline-flex justify-center rounded-md border border-main shadow-sm px-4 py-2 bg-surface text-base font-medium text-secondary hover:bg-slate-50 dark:hover:bg-slate-700 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancelar
                </a>
            </div>
        </div>
    </div>
</div>
@endif

@endsection
