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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Editar Ingreso</h1>
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
        <form id="form-ingreso" action="{{ route('ingresos.update', $ingreso) }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')

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
                            {{ old('vehiculo_id', $ingreso->vehiculo_id) == $vehiculo->id ? 'selected' : '' }}
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
                    value="{{ old('placa', $ingreso->cliente->placa ?? '') }}"
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
                    value="{{ old('nombre', $ingreso->cliente->nombre ?? '') }}"
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
                    value="{{ old('dni', $ingreso->cliente->dni ?? '') }}"
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
                    value="{{ old('telefono', $ingreso->cliente->telefono ?? '') }}"
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
                    value="{{ old('fecha', $ingreso->fecha ? $ingreso->fecha->format('Y-m-d') : date('Y-m-d')) }}"
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

                @if($ingreso->foto)
                    <div class="mb-3">
                        <p class="text-xs text-text-secondary-dark mb-1">Foto actual:</p>
                        <img
                            id="foto-current"
                            src="{{ Storage::url($ingreso->foto) }}"
                            alt="Foto actual del vehículo"
                            class="rounded-lg max-h-48 object-cover border border-main"
                        >
                        <p class="mt-1 text-xs text-text-secondary-dark">Sube una nueva imagen para reemplazarla.</p>
                    </div>
                @endif

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
                <img id="foto-preview" src="" alt="Vista previa" class="hidden mt-3 rounded-lg max-h-48 object-cover border border-main">
            </div>

            {{-- ── Trabajadores ── --}}
            @php
                $trabajadoresAsignados = old('trabajadores_ids', $ingreso->trabajadores->pluck('id')->toArray());
            @endphp
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
                                      {{ in_array($trabajador->id, $trabajadoresAsignados) ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-main' }}
                                      trabajador-option">
                            <input
                                type="checkbox"
                                name="trabajadores_ids[]"
                                value="{{ $trabajador->id }}"
                                class="trabajador-checkbox w-4 h-4 text-blue-600 border-main rounded focus:ring-blue-500 dark:bg-slate-800"
                                {{ in_array($trabajador->id, $trabajadoresAsignados) ? 'checked' : '' }}
                            >
                            <span class="text-sm text-secondary">{{ $trabajador->nombre }}</span>
                        </label>
                    @endforeach
                </div>
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
                <table class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Servicio</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Precio</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Eliminar</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-servicios" class="bg-surface divide-y divide-main">
                        {{-- Rows rendered by JS --}}
                    </tbody>
                </table>
            </div>

            {{-- ── Sección de totales ── --}}
            <div class="mb-6 max-w-sm space-y-4">

                {{-- Precio (readonly) --}}
                <div>
                    <label for="precio" class="label-main mb-1">Precio</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-secondary">S/</span>
                        <input
                            type="number"
                            id="precio"
                            name="precio"
                            step="0.01"
                            readonly
                            value="{{ old('precio', number_format($ingreso->precio, 2, '.', '')) }}"
                            class="w-full pl-8 pr-3 py-2 border rounded-lg text-sm focus:outline-none input-main bg-slate-50 dark:bg-slate-800/50"
                        >
                    </div>
                    @error('precio')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Toggle descuento --}}
                <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="toggle-descuento"
                               class="w-4 h-4 text-blue-600 border-main rounded focus:ring-blue-500 dark:bg-slate-800">
                        <span class="text-sm text-secondary">Aplicar descuento por porcentaje</span>
                    </label>
                </div>

                {{-- Campo porcentaje (oculto por defecto) --}}
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
                            value="{{ old('total', number_format($ingreso->total, 2, '.', '')) }}"
                            class="w-full pl-8 pr-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main bg-slate-50 dark:bg-slate-800/50
                                   {{ $errors->has('total') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
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
                                          metodo-pago-option {{ old('metodo_pago', $ingreso->metodo_pago ?? 'efectivo') === $val ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-main hover:border-gray-400' }}">
                                <input type="radio" name="metodo_pago" value="{{ $val }}"
                                       class="hidden metodo-pago-radio"
                                       {{ old('metodo_pago', $ingreso->metodo_pago ?? 'efectivo') === $val ? 'checked' : '' }}>
                                <span class="text-sm font-medium {{ old('metodo_pago', $ingreso->metodo_pago ?? 'efectivo') === $val ? 'text-blue-700 dark:text-blue-400' : 'text-secondary' }}">
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
                               value="{{ old('monto_efectivo', $ingreso->monto_efectivo) }}" placeholder="0.00"
                               class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 input-main">
                    </div>
                    <div>
                        <label for="monto_yape" class="block text-xs font-medium text-secondary mb-1">Yape (S/)</label>
                        <input type="number" id="monto_yape" name="monto_yape" step="0.01" min="0"
                               value="{{ old('monto_yape', $ingreso->monto_yape) }}" placeholder="0.00"
                               class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 input-main">
                    </div>
                    <div>
                        <label for="monto_izipay" class="block text-xs font-medium text-secondary mb-1">Izipay (S/)</label>
                        <input type="number" id="monto_izipay" name="monto_izipay" step="0.01" min="0"
                               value="{{ old('monto_izipay', $ingreso->monto_izipay) }}" placeholder="0.00"
                               class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 input-main">
                    </div>
                </div>

            </div>

            {{-- ── Submit ── --}}
            <div class="flex items-center gap-3 mt-6">
                <button
                    type="submit"
                    aria-label="Guardar cambios"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar cambios
                </button>
                <a href="{{ route('ingresos.index') }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</div>

<script>
    window.serviciosExistentes = @json($serviciosExistentes);
    window.ingresoMetodoPago   = @json($ingreso->metodo_pago ?? 'efectivo');
    window.ingresoMontos       = @json($ingresoMontos);
</script>
@vite('resources/js/ingresos/edit.js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-ingreso');
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
            lbl.classList.toggle('dark:bg-blue-900/20', cb.checked);
            lbl.classList.toggle('border-main', !cb.checked);
        });
    });
});
</script>
@endsection
