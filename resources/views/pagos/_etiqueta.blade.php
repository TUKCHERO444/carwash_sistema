@php
    $lineasPago = $pagable->pagos ?? collect();
    $esMixtoPago = $lineasPago->count() > 1;
    $primeraLinea = $lineasPago->first();
    $etiquetaPago = $esMixtoPago ? 'Mixto' : ($primeraLinea?->metodoPago?->nombre ?? '—');
    $codigoPago = $esMixtoPago ? 'mixto' : ($primeraLinea?->metodoPago?->codigo ?? '');
    $coloresPago = [
        'efectivo' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
        'yape' => 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400',
        'izipay' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
        'mixto' => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400',
    ];
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $coloresPago[$codigoPago] ?? 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-secondary-dark' }}">
    {{ $etiquetaPago }}
</span>
