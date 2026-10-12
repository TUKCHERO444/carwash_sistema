{{-- Bloque de método de pago reutilizable (ventas, lavados, cambio de aceite).
     Parámetros: $metodosPago (Collection de MetodoPago activos),
     $metodoActual (código o 'mixto'; por defecto old('metodo_pago', 'efectivo')),
     $montos (array codigo => monto; cada campo prioriza old("monto_<codigo>")). --}}
@php
    $metodoActual = $metodoActual ?? old('metodo_pago', 'efectivo');
    $montos = $montos ?? [];
@endphp
<div>
    <label class="label-main mb-2">
        Método de pago <span class="text-red-500">*</span>
    </label>
    <div class="grid grid-cols-2 gap-2">
        @foreach($metodosPago as $metodo)
            <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer transition-colors
                          metodo-pago-option {{ $metodoActual === $metodo->codigo ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-main hover:border-gray-400' }}">
                <input type="radio" name="metodo_pago" value="{{ $metodo->codigo }}"
                       class="hidden metodo-pago-radio"
                       {{ $metodoActual === $metodo->codigo ? 'checked' : '' }}>
                <span class="text-sm font-medium {{ $metodoActual === $metodo->codigo ? 'text-blue-700 dark:text-blue-400' : 'text-secondary' }}">
                    {{ $metodo->nombre }}
                </span>
            </label>
        @endforeach
        <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer transition-colors
                      metodo-pago-option {{ $metodoActual === 'mixto' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-main hover:border-gray-400' }}">
            <input type="radio" name="metodo_pago" value="mixto"
                   class="hidden metodo-pago-radio"
                   {{ $metodoActual === 'mixto' ? 'checked' : '' }}>
            <span class="text-sm font-medium {{ $metodoActual === 'mixto' ? 'text-blue-700 dark:text-blue-400' : 'text-secondary' }}">
                Mixto
            </span>
        </label>
    </div>
    @error('metodo_pago')
        <p id="metodo-pago-error" role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
    @error('pagos')
        <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

{{-- Campos mixto --}}
<div id="bloque-mixto" class="hidden space-y-3">
    <p class="text-xs text-secondary">Ingresa los montos por método (al menos uno). La suma debe igualar el total.</p>
    <div id="alerta-mixto" class="hidden px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-800 text-amber-800 dark:text-amber-400 text-xs">
        La suma de los montos (<span id="suma-mixto-display">0.00</span>) no coincide con el total (<span id="total-mixto-display">0.00</span>).
    </div>
    @foreach($metodosPago as $metodo)
        <div>
            <label for="monto_{{ $metodo->codigo }}" class="block text-xs font-medium text-secondary mb-1">{{ $metodo->nombre }} (S/)</label>
            <input type="number" id="monto_{{ $metodo->codigo }}" name="monto_{{ $metodo->codigo }}" step="0.01" min="0"
                   data-monto-metodo="{{ $metodo->codigo }}"
                   value="{{ old("monto_{$metodo->codigo}", $montos[$metodo->codigo] ?? '') }}" placeholder="0.00"
                   class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main"
            >
        </div>
    @endforeach
</div>
