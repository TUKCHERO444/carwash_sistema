{{--
    Muestra los errores de validación cuya clave NO tiene un @error() propio
    en la vista (p. ej. "productos.3.cantidad" o "precio" en el panel de
    confirmación). Sin este bloque esos mensajes quedan invisibles para el
    usuario: el controlador rechaza la operación pero la consola no indica nada.

    Uso (la lista `excluir` son las claves que la vista ya imprime inline):
        @include('partials.errores-validacion', ['excluir' => ['productos', 'placa']])
--}}
@php
    $clavesExcluidas = $excluir ?? [];
    $clavesPendientes = collect($errors->keys())
        ->reject(fn (string $clave) => in_array($clave, $clavesExcluidas, true))
        ->values();
@endphp

@if($clavesPendientes->isNotEmpty())
    <div role="alert" class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-400 text-sm text-red-600 space-y-1">
        @foreach($clavesPendientes as $clave)
            <p data-error-key="{{ $clave }}">{{ $errors->first($clave) }}</p>
        @endforeach
    </div>
@endif
