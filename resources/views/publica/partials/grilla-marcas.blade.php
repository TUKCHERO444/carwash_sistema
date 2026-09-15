{{--
    Tabla de marcas (vista pública): tabla junta de logos de las marcas que
    desde el panel se seleccionaron para display, en el orden de la curaduría.
    La tabla crece automáticamente con la cantidad de marcas seleccionadas.
    Cada celda carga solo la foto de la marca en un tamaño y resolución
    estable (Cloudinary optimiza la imagen); si la marca no tiene foto se
    muestra el wordmark como respaldo.

    Props:
      - $marcas : Collection de App\Models\Marca con foto (o sin foto).
--}}
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-px bg-steel-200 border border-steel-200 rounded-2xl overflow-hidden shadow-sm">
    @forelse ($marcas as $marca)
        @php
            $fotoWeb = $marca->foto_url;
            if ($fotoWeb && str_starts_with($fotoWeb, 'https://res.cloudinary.com/')) {
                // Resolución estable: Cloudinary entrega la versión exacta a la
                // medida del display (ancho fijo 240px) en vez del original.
                $fotoWeb = str_replace('/image/upload/', '/image/upload/c_scale,w_240,q_auto/', $fotoWeb);
            }
        @endphp
        <div class="flex items-center justify-center bg-white px-4 py-5 sm:py-6">
            @if ($fotoWeb)
                <img src="{{ $fotoWeb }}"
                     alt="Marca {{ $marca->nombre }}"
                     loading="lazy"
                     decoding="async"
                     class="h-12 sm:h-14 w-auto max-w-full object-contain">
            @else
                <span class="text-sm sm:text-base font-bold tracking-wide text-navy-900 text-center">{{ $marca->nombre }}</span>
            @endif
        </div>
    @empty
        <p class="col-span-full bg-white py-8 text-center text-sm text-steel-500">
            Aún no tenemos marcas registradas.
        </p>
    @endforelse
</div>