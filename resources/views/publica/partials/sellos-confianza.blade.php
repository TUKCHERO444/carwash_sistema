{{-- Sellos de confianza (fila de íconos global, encima del footer).
     Mismo patrón visual que el tema de referencia (text-with-icons__title text--strong). --}}
<section class="bg-white-cold pb-16 sm:pb-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @php
                $sellos = [
                    ['titulo' => 'Atención al cliente', 'detalle' => 'Resolvemos todas tus dudas', 'icono' => 'chat'],
                    ['titulo' => 'Calidad garantizada', 'detalle' => 'Productos de primeras marcas', 'icono' => 'check'],
                    ['titulo' => 'Pagos seguros', 'detalle' => 'Efectivo, Yape e Izipay', 'icono' => 'card'],
                    ['titulo' => 'Servicio rápido', 'detalle' => 'Tu auto listo en el día', 'icono' => 'clock'],
                ];
            @endphp
            @foreach ($sellos as $sello)
                <div class="flex items-center gap-4 rounded-2xl bg-white border border-steel-200 p-6 shadow-sm">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-blue-50 text-brand-blue-700">
                        @if ($sello['icono'] === 'chat')
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                        @elseif ($sello['icono'] === 'card')
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h4m-8 3h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        @elseif ($sello['icono'] === 'clock')
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @else
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @endif
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-navy-900">{{ $sello['titulo'] }}</p>
                        <p class="mt-0.5 text-xs text-steel-600">{{ $sello['detalle'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>