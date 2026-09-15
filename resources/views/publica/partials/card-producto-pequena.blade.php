{{--
    Card de producto pequeña (sección "Te recomendamos" de la vista de inicio).
    Props:
      - $producto  : App\Models\Producto con marca cargada.
      - $categoria : (opcional) Categoria; si no se pasa se usa la del producto.
    Muestra la misma información que la card grande en formato compacto
    (diseñada para 6 por fila) y enlaza al detalle público del producto.
--}}
@php
    $agotado = (int) $producto->stock <= 0;
    $categoriaEnlace = $categoria ?? $producto->categoria;
    $rutaDetalle = $categoriaEnlace
        ? route('publica.productos.detalle', ['categoria' => $categoriaEnlace, 'producto' => $producto])
        : route('publica.productos');
@endphp

<article class="group flex flex-col overflow-hidden rounded-xl border border-steel-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
    {{-- Imagen del producto (o placeholder si no tiene foto). --}}
    <a href="{{ $rutaDetalle }}" class="relative aspect-square overflow-hidden bg-navy-800">
        @if ($producto->fotoUrl)
            <img src="{{ $producto->fotoUrl }}" alt="{{ $producto->nombre }}"
                 class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
        @else
            <span class="flex h-full w-full items-center justify-center text-navy-600">
                <svg aria-hidden="true" class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </span>
        @endif

        {{-- Sello de agotado sobre la imagen. --}}
        @if ($agotado)
            <span class="absolute inset-0 flex items-center justify-center bg-navy-900/55">
                <span class="rounded-full bg-white px-3 py-1 text-[10px] font-semibold uppercase tracking-widest text-red-600">Agotado</span>
            </span>
        @endif
    </a>

    {{-- Información de la card. --}}
    <div class="flex flex-1 flex-col gap-1.5 p-3">
        @if ($producto->marca)
            <span class="text-[10px] font-semibold uppercase tracking-widest text-brand-blue-700">{{ mb_strtoupper($producto->marca->nombre) }}</span>
        @endif
        <a href="{{ $rutaDetalle }}" class="text-sm font-semibold leading-snug text-navy-900 line-clamp-2 transition-colors hover:text-brand-blue-700">{{ mayusculas($producto->nombre) }}</a>
        <p class="mt-auto pt-1 text-base font-semibold text-navy-900">
            S/ {{ number_format((float) $producto->precio_venta, 2, '.', '') }}
        </p>
        <p class="text-[11px] font-medium {{ $agotado ? 'text-red-600' : 'text-green-600' }}">
            {{ $agotado ? 'Sin stock disponible' : 'Disponible' }}
        </p>
        <a href="{{ $rutaDetalle }}"
           class="mt-1.5 inline-flex items-center justify-center gap-1 rounded-lg bg-brand-cyan-500 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-navy-900 transition-colors hover:bg-brand-cyan-400">
            VER DETALLE
            <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
            </svg>
        </a>
    </div>
</article>