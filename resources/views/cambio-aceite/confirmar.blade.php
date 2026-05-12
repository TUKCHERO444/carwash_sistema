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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Confirmar Cambio de Aceite</h1>
        <a href="{{ route('cambio-aceite.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a Pendientes
        </a>
    </div>

    {{-- Resumen superior --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
        <h2 class="text-sm font-semibold text-blue-800 dark:text-blue-300 mb-2">Resumen del Ticket</h2>
        <p class="text-sm text-blue-700 dark:text-blue-400"><strong>Placa:</strong> {{ $cambioAceite->cliente->placa ?? 'N/A' }}</p>
        <div class="text-sm text-blue-700 dark:text-blue-400 mt-1">
            <strong>Productos asignados:</strong>
            @if($cambioAceite->productos->count() > 0)
                <ul class="list-disc list-inside ml-2">
                    @foreach($cambioAceite->productos as $producto)
                        <li>{{ $producto->nombre }}</li>
                    @endforeach
                </ul>
            @else
                Ninguno
            @endif
        </div>
    </div>

    {{-- Form container --}}
    <div class="bg-surface rounded-lg border border-main p-6">
        <form id="form-cambio-aceite" action="{{ route('cambio-aceite.procesarConfirmacion', $cambioAceite) }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf

            {{-- Validation error for productos --}}
            @error('productos')
                <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-400 text-sm text-red-600">
                    {{ $message }}
                </div>
            @enderror

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
                    value="{{ old('placa', $cambioAceite->cliente->placa ?? '') }}"
                    placeholder="Ej: ABC-123"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors input-main {{ $errors->has('placa') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                >
                @error('placa') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- ── Nombre ── --}}
            <div class="mb-5">
                <label for="nombre" class="label-main mb-1">Nombre <span class="text-gray-400 font-normal">(opcional)</span></label>
                <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $cambioAceite->cliente->nombre ?? '') }}" placeholder="Nombre del cliente" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors input-main {{ $errors->has('nombre') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}">
                @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- ── DNI ── --}}
            <div class="mb-5">
                <label for="dni" class="label-main mb-1">DNI <span class="text-gray-400 font-normal">(opcional)</span></label>
                <input type="text" id="dni" name="dni" value="{{ old('dni', $cambioAceite->cliente->dni ?? '') }}" placeholder="DNI del cliente" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors input-main {{ $errors->has('dni') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}">
                @error('dni') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- ── Teléfono ── --}}
            <div class="mb-5">
                <label for="telefono" class="block text-sm font-medium text-gray-700 mb-1">
                    Teléfono <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="telefono"
                    name="telefono"
                    value="{{ old('telefono', $cambioAceite->cliente->telefono ?? '') }}"
                    placeholder="Teléfono del cliente"
                    class="w-full px-3 py-2 border rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors {{ $errors->has('telefono') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}"
                >
                @error('telefono') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- ── Resumen del Cliente ── --}}
            <div id="cliente-summary-container" class="mb-5 hidden">
            </div>

            {{-- ── Fecha ── --}}
            <div class="mb-5">
                <label for="fecha" class="label-main mb-1">Fecha <span class="text-red-500">*</span></label>
                <input type="date" id="fecha" name="fecha" required value="{{ old('fecha', $cambioAceite->fecha ? $cambioAceite->fecha->format('Y-m-d') : date('Y-m-d')) }}" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors input-main {{ $errors->has('fecha') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}">
                @error('fecha') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- ── Descripción ── --}}
            <div class="mb-5">
                <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1">
                    Descripción <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <textarea
                    id="descripcion"
                    name="descripcion"
                    rows="3"
                    placeholder="Observaciones sobre el cambio de aceite..."
                    class="w-full px-3 py-2 border rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors {{ $errors->has('descripcion') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}"
                >{{ old('descripcion', $cambioAceite->descripcion) }}</textarea>
                @error('descripcion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- ── Foto ── --}}
            <div class="mb-5">
                <label for="foto" class="block text-sm font-medium text-gray-700 mb-1">
                    Foto del vehículo <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                @if($cambioAceite->foto)
                    <div class="mb-3">
                        <p class="text-xs text-gray-500 mb-1">Foto actual:</p>
                        <img id="foto-current" src="{{ Storage::url($cambioAceite->foto) }}" alt="Foto actual" class="rounded-lg max-h-48 object-cover border border-gray-200">
                    </div>
                @endif
                <input
                    type="file"
                    id="foto"
                    name="foto"
                    accept="image/*"
                    class="w-full px-3 py-2 border rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors {{ $errors->has('foto') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}"
                >
                @error('foto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <img id="foto-preview" src="" alt="Vista previa" class="hidden mt-3 rounded-lg max-h-48 object-cover border border-gray-200">
            </div>

            {{-- ── Trabajadores ── --}}
            @php $trabajadoresCheck = old('trabajadores_ids', $trabajadoresAsignados ?? []); @endphp
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Trabajadores <span class="text-red-500">*</span>
                    <span class="text-xs font-normal text-gray-400">(al menos 1)</span>
                </label>
                @error('trabajadores_ids')
                    <p class="mb-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach($trabajadores as $trabajador)
                        <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-slate-800/50
                                      {{ in_array($trabajador->id, $trabajadoresCheck) ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30' : 'border-gray-300 dark:border-border-dark' }}
                                      trabajador-option">
                            <input type="checkbox" name="trabajadores_ids[]" value="{{ $trabajador->id }}"
                                   class="trabajador-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                   {{ in_array($trabajador->id, $trabajadoresCheck) ? 'checked' : '' }}>
                            <span class="text-sm text-gray-800 dark:text-text-primary-dark">{{ $trabajador->nombre }}</span>
                        </label>
                    @endforeach
                </div>
                <p id="error-trabajadores" class="hidden mt-1 text-xs text-red-600">Debe seleccionar al menos un trabajador.</p>
            </div>

            {{-- ── Búsqueda de productos ── --}}
            <div class="mb-6">
                <label for="buscar-producto" class="label-main mb-1">Buscar producto</label>
                <div class="relative">
                    <input type="text" id="buscar-producto" autocomplete="off" placeholder="Escribe el nombre del producto..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors input-main">
                    <div id="resultados-busqueda" class="hidden absolute z-10 w-full mt-1 bg-surface border border-main rounded-lg shadow-lg max-h-60 overflow-y-auto"></div>
                </div>
            </div>

            {{-- ── Tabla de productos ── --}}
            <div class="mb-6 overflow-x-auto">
                <table id="tabla-detalle" class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Producto
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Cantidad
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Precio Unit.
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Subtotal
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Eliminar
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detalle" class="bg-surface divide-y divide-main">
                        {{-- Rows rendered by JS --}}
                    </tbody>
                </table>
            </div>

            {{-- ── Sección de totales y pago ── --}}
            <div class="mb-6 max-w-sm space-y-4">

                {{-- Precio --}}
                <div>
                    <label for="precio" class="label-main mb-1">Precio</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 dark:text-text-secondary-dark">S/</span>
                        <input type="number" id="precio" name="precio" step="0.01" readonly value="{{ old('precio', number_format($cambioAceite->precio, 2, '.', '')) }}" class="w-full pl-8 pr-3 py-2 border rounded-lg text-sm input-main bg-gray-50 dark:bg-slate-800/50">
                    </div>
                </div>

                {{-- Toggle descuento por porcentaje --}}
                <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="toggle-descuento" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm text-gray-700">Aplicar descuento por porcentaje</span>
                    </label>
                </div>
                <div id="campo-porcentaje" class="hidden">
                    <label for="porcentaje" class="block text-sm font-medium text-gray-700 mb-1">Porcentaje (%)</label>
                    <input type="number" id="porcentaje" min="0" max="100" step="0.01" placeholder="0.00" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <p id="error-porcentaje" class="hidden mt-1 text-xs text-red-600">No puede superar 100.</p>
                </div>

                {{-- Toggle descuento manual --}}
                <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="toggle-descuento-manual" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm text-gray-700">Aplicar descuento manual</span>
                    </label>
                </div>

                {{-- Total --}}
                <div>
                    <label for="total" class="block text-sm font-medium text-gray-700 mb-1">
                        Total <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500">S/</span>
                        <input
                            type="number"
                            id="total"
                            name="total"
                            step="0.01"
                            readonly
                            value="{{ old('total', number_format($cambioAceite->total, 2, '.', '')) }}"
                            class="w-full pl-8 pr-3 py-2 border rounded-lg text-sm bg-gray-50 focus:outline-none {{ $errors->has('total') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}"
                        >
                    </div>
                    @error('total') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Método de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Método de pago <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto'] as $val => $label)
                            <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer transition-colors metodo-pago-option {{ old('metodo_pago', $cambioAceite->metodo_pago ?? 'efectivo') === $val ? 'border-blue-500 bg-blue-50' : 'border-gray-300' }}">
                                <input
                                    type="radio"
                                    name="metodo_pago"
                                    value="{{ $val }}"
                                    class="hidden metodo-pago-radio"
                                    {{ old('metodo_pago', $cambioAceite->metodo_pago ?? 'efectivo') === $val ? 'checked' : '' }}
                                >
                                <span class="text-sm font-medium {{ old('metodo_pago', $cambioAceite->metodo_pago ?? 'efectivo') === $val ? 'text-blue-700' : 'text-gray-700' }}">
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('metodo_pago') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Bloque mixto --}}
                <div id="bloque-mixto" class="hidden space-y-3">
                    <p class="text-xs text-gray-500">Ingresa los montos. La suma debe igualar el total.</p>
                    <div id="alerta-mixto" class="hidden px-3 py-2 rounded-lg bg-amber-50 border border-amber-300 text-amber-800 text-xs">
                        Suma (<span id="suma-mixto-display">0.00</span>) no coincide con total (<span id="total-mixto-display">0.00</span>).
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Efectivo (S/)</label>
                        <input
                            type="number"
                            id="monto_efectivo"
                            name="monto_efectivo"
                            step="0.01"
                            min="0"
                            value="{{ old('monto_efectivo', $cambioAceite->monto_efectivo) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Yape (S/)</label>
                        <input
                            type="number"
                            id="monto_yape"
                            name="monto_yape"
                            step="0.01"
                            min="0"
                            value="{{ old('monto_yape', $cambioAceite->monto_yape) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Izipay (S/)</label>
                        <input
                            type="number"
                            id="monto_izipay"
                            name="monto_izipay"
                            step="0.01"
                            min="0"
                            value="{{ old('monto_izipay', $cambioAceite->monto_izipay) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        >
                    </div>
                </div>
            </div>

            {{-- ── Acciones ── --}}
            <div class="flex flex-wrap items-center gap-3 mt-6 pt-6 border-t border-gray-200">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Confirmar cambio de aceite
                </button>
                <button
                    type="button"
                    onclick="submitActualizar()"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Actualizar ticket
                </button>
                <button
                    type="button"
                    onclick="confirmarEliminacion()"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors ml-auto"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Eliminar ticket
                </button>
            </div>
        </form>

        <form id="form-eliminar" action="{{ route('cambio-aceite.destroy', $cambioAceite) }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>

{{-- Variables globales para el módulo JS --}}
<script>
window.productosConfirmar  = @json($productosData);
window.confirmarMetodoPago = @json($cambioAceite->metodo_pago ?? 'efectivo');
window.confirmarMontos     = @json($montosData);
window._confirmarUpdateUrl = "{{ route('cambio-aceite.actualizarTicket', $cambioAceite) }}";
</script>
@vite('resources/js/cambio-aceite/confirmar.js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-cambio-aceite');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        const checked = form.querySelectorAll('.trabajador-checkbox:checked');
        const errMsg  = document.getElementById('error-trabajadores');
        if (checked.length === 0) { e.preventDefault(); if (errMsg) errMsg.classList.remove('hidden'); }
        else { if (errMsg) errMsg.classList.add('hidden'); }
    });
    form.querySelectorAll('.trabajador-checkbox').forEach(function(cb) {
        cb.addEventListener('change', function() {
            const lbl = cb.closest('.trabajador-option');
            lbl.classList.toggle('border-blue-500', cb.checked);
            lbl.classList.toggle('bg-blue-50', cb.checked);
            lbl.classList.toggle('border-gray-300', !cb.checked);
        });
    });
});
</script>

{{-- Modal de caja cerrada --}}
@if(session('error_caja'))
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-caja-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="relative inline-block align-bottom bg-surface rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md w-full">
            <div class="bg-surface px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-primary" id="modal-caja-title">Caja Cerrada</h3>
                        <div class="mt-2">
                            <p class="text-sm text-secondary">No puedes registrar cobros porque no hay ninguna sesión de caja activa. Debes iniciar la caja primero.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-slate-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <a href="{{ route('caja.index') }}"
                   class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">
                    Ir a Caja
                </a>
                <a href="{{ route('cambio-aceite.index') }}"
                   class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancelar
                </a>
            </div>
        </div>
    </div>
</div>
@endif

@endsection
