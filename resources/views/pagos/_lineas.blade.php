@php $resumenPagosView = app(\App\Services\PagoService::class)->resumen($pagable); @endphp
<div>
    <span class="font-medium">{{ $resumenPagosView['etiqueta'] }}</span>
    @if($resumenPagosView['mixto'])
        <ul class="mt-1 space-y-0.5">
            @foreach($resumenPagosView['lineas'] as $lineaPago)
                <li class="text-xs">{{ $lineaPago['nombre'] }}: S/ {{ number_format($lineaPago['monto'], 2) }}</li>
            @endforeach
        </ul>
    @endif
</div>
