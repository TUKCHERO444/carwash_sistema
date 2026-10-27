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
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Devolver productos</h1>
            <p class="mt-1 text-sm text-secondary">Venta {{ $venta->correlativo }} · {{ $venta->created_at->format('d/m/Y') }} · Total S/ {{ number_format($venta->total, 2) }}</p>
        </div>
        <a href="{{ route('ventas.show', $venta) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a la venta
        </a>
    </div>

    {{-- Form container --}}
    <div class="bg-surface rounded-lg border border-main p-6">
        <form id="form-devolucion" action="{{ route('ventas.devoluciones.store', $venta) }}" method="POST" novalidate>
            @csrf

            @error('lineas')
                <div role="alert" class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-400 text-sm text-red-600">
                    {{ $message }}
                </div>
            @enderror

            {{-- ── Líneas de la venta ── --}}
            <h2 class="text-sm font-semibold text-primary mb-3">Productos de la venta — cantidad a devolver</h2>
            <div class="mb-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Producto</th>
                            <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Vendida</th>
                            <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Devuelta</th>
                            <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Disponible</th>
                            <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Precio unit.</th>
                            <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">A devolver</th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface divide-y divide-main">
                        @foreach($lineas as $i => $linea)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-4 py-8 text-sm text-primary">{{ $linea['producto_nombre'] }}</td>
                                <td class="px-4 py-8 whitespace-nowrap text-sm text-secondary">{{ $linea['vendida'] }}</td>
                                <td class="px-4 py-8 whitespace-nowrap text-sm text-secondary">{{ $linea['devuelta'] }}</td>
                                <td class="px-4 py-8 whitespace-nowrap text-sm text-secondary">{{ $linea['disponible'] }}</td>
                                <td class="px-4 py-8 whitespace-nowrap text-sm text-secondary">S/ {{ number_format($linea['precio_unitario'], 2) }}</td>
                                <td class="px-4 py-8">
                                    <input type="hidden" name="lineas[{{ $i }}][detalle_venta_id]" value="{{ $linea['detalle_venta_id'] }}">
                                    <input
                                        type="number"
                                        name="lineas[{{ $i }}][cantidad]"
                                        class="cantidad-devolver w-24 border rounded px-2 py-1 text-sm input-main"
                                        min="0"
                                        max="{{ $linea['disponible'] }}"
                                        value="{{ old("lineas.$i.cantidad", 0) }}"
                                        data-precio="{{ $linea['precio_unitario'] }}"
                                        data-disponible="{{ $linea['disponible'] }}"
                                        {{ $linea['disponible'] <= 0 ? 'disabled' : '' }}
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- ── Tipo de operación ── --}}
            <div class="mb-6">
                <span class="label-main mb-2 block">Tipo de operación <span class="text-red-500">*</span></span>
                <div class="flex flex-wrap gap-2">
                    <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer transition-colors tipo-option {{ old('tipo', 'devolucion') === 'devolucion' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-main hover:border-gray-400' }}">
                        <input type="radio" name="tipo" value="devolucion" class="hidden tipo-radio" {{ old('tipo', 'devolucion') === 'devolucion' ? 'checked' : '' }}>
                        <span class="text-sm font-medium {{ old('tipo', 'devolucion') === 'devolucion' ? 'text-blue-700 dark:text-blue-400' : 'text-secondary' }}">Devolución</span>
                    </label>
                    <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer transition-colors tipo-option {{ old('tipo') === 'cambio' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-main hover:border-gray-400' }}">
                        <input type="radio" name="tipo" value="cambio" class="hidden tipo-radio" {{ old('tipo') === 'cambio' ? 'checked' : '' }}>
                        <span class="text-sm font-medium {{ old('tipo') === 'cambio' ? 'text-blue-700 dark:text-blue-400' : 'text-secondary' }}">Cambio por otro producto</span>
                    </label>
                </div>
                @error('tipo')
                    <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Producto sustituto (solo cambio) ── --}}
            <div id="bloque-cambio" class="mb-6 hidden">
                <h2 class="text-sm font-semibold text-primary mb-3">Producto sustituto</h2>
                <div class="relative max-w-md">
                    <label for="buscar-sustituto" class="label-main mb-1">Buscar producto</label>
                    <input
                        type="text"
                        id="buscar-sustituto"
                        autocomplete="off"
                        placeholder="Escribe el nombre del producto..."
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main"
                    >
                    <div id="resultados-sustituto"
                         class="hidden absolute z-10 w-full mt-1 bg-surface border border-main rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    </div>
                </div>
                <div id="sustituto-seleccionado" class="{{ $sustituto ? '' : 'hidden' }} mt-3 max-w-md p-4 border border-main rounded-lg">
                    <input type="hidden" id="producto_nuevo_id" name="producto_nuevo_id" value="{{ old('producto_nuevo_id') }}">
                    <p class="text-sm text-primary font-medium" id="sustituto-nombre">{{ $sustituto['nombre'] ?? '' }}</p>
                    <p class="text-xs text-secondary mt-1">Precio: S/ <span id="sustituto-precio">{{ $sustituto ? number_format($sustituto['precio_venta'], 2) : '0.00' }}</span> · Stock: <span id="sustituto-stock">{{ $sustituto['stock'] ?? 0 }}</span></p>
                    <div class="mt-3">
                        <label for="cantidad_nueva" class="label-main mb-1">Cantidad</label>
                        <input type="number" id="cantidad_nueva" name="cantidad_nueva" min="1" value="{{ old('cantidad_nueva', 1) }}"
                               class="w-24 border rounded px-2 py-1 text-sm input-main">
                    </div>
                </div>
                @error('producto_nuevo_id')
                    <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
                @error('cantidad_nueva')
                    <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Motivo ── --}}
            <div class="mb-6 max-w-md">
                <label for="motivo" class="label-main mb-1">Motivo <span class="text-red-500">*</span></label>
                <textarea
                    id="motivo"
                    name="motivo"
                    rows="3"
                    maxlength="500"
                    placeholder="Ej. Producto defectuoso, talla incorrecta..."
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main {{ $errors->has('motivo') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >{{ old('motivo') }}</textarea>
                @error('motivo')
                    <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Total a devolver (informativo; el servidor lo recalcula) ── --}}
            <div class="mb-6 max-w-sm">
                <label for="total-devuelto" class="label-main mb-1">Total a devolver</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-secondary">S/</span>
                    <input type="text" id="total-devuelto" readonly value="0.00"
                           class="w-full pl-8 pr-3 py-2 border rounded-lg text-sm input-main bg-slate-50 dark:bg-slate-800/50">
                </div>
            </div>

            {{-- ── Submit ── --}}
            <div class="flex items-center gap-3">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                    Registrar devolución
                </button>
                <a href="{{ route('ventas.show', $venta) }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</div>

@vite('resources/js/devoluciones/create.js')
@if(session('error_caja'))
<x-modal id="modal-caja-cerrada" title="Caja Cerrada" open>
    <div class="px-6 pb-4 sm:pb-6">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:h-10 sm:w-10">
                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <p class="text-sm text-secondary">No puedes registrar devoluciones porque no hay ninguna sesión de caja activa. Debes iniciar la caja primero.</p>
        </div>
    </div>
    <x-slot:footer>
        <a href="{{ route('caja.index') }}" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:text-sm">
            Ir a Caja
        </a>
        <a href="{{ route('ventas.show', $venta) }}" class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-main shadow-sm px-4 py-2 bg-surface text-base font-medium text-secondary hover:bg-slate-50 dark:hover:bg-slate-700 sm:text-sm">
            Cancelar
        </a>
    </x-slot:footer>
</x-modal>
@endif

@endsection
