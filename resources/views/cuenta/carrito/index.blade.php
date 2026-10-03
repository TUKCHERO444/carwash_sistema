@extends('layouts.publica')

@section('titulo', 'Mi carrito — Carwash El Chinito')

@section('descripcion', 'Revisa tu lista de productos, ajusta cantidades y descarga el detalle.')

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-10">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-primary text-2xl font-semibold">Mi carrito</h1>
            <a href="{{ route('publica.productos') }}" class="text-sm font-medium text-sky-600 hover:underline">Seguir comprando</a>
        </div>

        @if (session('success'))
            <p role="status" class="bg-surface border-main mb-4 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('success') }}</p>
        @endif

        <p role="status" data-carrito-toast class="bg-surface border-main mb-4 hidden rounded-lg border px-3 py-2 text-sm"></p>

        @if ($items->isEmpty())
            <p class="bg-surface border-main text-secondary rounded-2xl border p-8 text-center text-sm">
                Tu carrito está vacío. Explora el catálogo y agrega productos con el botón +.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="bg-surface border-main w-full overflow-hidden rounded-2xl border text-left text-sm">
                    <thead>
                        <tr class="border-main border-b">
                            <th class="text-secondary px-4 py-6 font-semibold">Producto</th>
                            <th class="text-secondary px-4 py-6 font-semibold">Precio</th>
                            <th class="text-secondary px-4 py-6 font-semibold">Cantidad</th>
                            <th class="text-secondary px-4 py-6 font-semibold">Subtotal</th>
                            <th class="px-4 py-6"><span class="sr-only">Quitar</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-main divide-y">
                        @foreach ($items as $item)
                            <tr>
                                <td class="px-4 py-8">
                                    <p class="text-primary font-semibold">{{ $item->producto->nombre ?? 'Producto no disponible' }}</p>
                                    <p class="text-secondary text-xs">Stock actual: {{ $item->producto?->stock ?? 0 }}</p>
                                </td>
                                <td class="text-secondary px-4 py-8">S/ {{ number_format((float) $item->precio_unitario, 2, '.', '') }}</td>
                                <td class="px-4 py-8">
                                    <form action="{{ route('cuenta.carrito.actualizar', ['item' => $item]) }}" method="POST" data-carrito class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <label for="cantidad-{{ $item->id }}" class="sr-only">Cantidad</label>
                                        <input type="number" id="cantidad-{{ $item->id }}" name="cantidad" value="{{ $item->cantidad }}" min="1" max="{{ max(1, (int) ($item->producto?->stock ?? 1)) }}"
                                               class="input-main w-20 rounded-lg border px-2 py-1.5 text-center text-sm">
                                        <button type="submit" class="text-sm font-medium text-sky-600 hover:underline">Actualizar</button>
                                    </form>
                                    @error('cantidad')
                                        <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="text-primary px-4 py-8 font-semibold">S/ {{ number_format((float) $item->subtotal, 2, '.', '') }}</td>
                                <td class="px-4 py-8">
                                    <form action="{{ route('cuenta.carrito.quitar', ['item' => $item]) }}" method="POST" data-carrito>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-main border-t">
                            <td colspan="3" class="text-secondary px-4 py-6 text-right font-semibold">Total</td>
                            <td class="text-primary px-4 py-6 font-bold">S/ {{ number_format((float) $total, 2, '.', '') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('cuenta.carrito.pdf') }}"
                   class="rounded-lg bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                    Descargar PDF
                </a>
                @if (config('services.whatsapp.pedidos'))
                    <a href="{{ route('cuenta.carrito.whatsapp') }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700">
                        <svg aria-hidden="true" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.2 14.2c-.22.62-1.28 1.19-1.78 1.23-.48.05-1.09.22-3.65-.76-3.08-1.17-5.03-4.18-5.18-4.37-.15-.19-1.23-1.64-1.23-3.13 0-1.49.78-2.22 1.06-2.53.27-.3.6-.38.8-.38h.58c.19 0 .44-.07.68.52.25.6.85 2.07.92 2.22.08.15.13.33.02.53-.1.2-.15.33-.3.51-.15.19-.32.42-.45.56-.15.15-.31.32-.13.62.17.3.78 1.29 1.67 2.09 1.15 1.02 2.11 1.34 2.41 1.49.3.15.48.13.65-.08.18-.2.74-.86.94-1.16.2-.3.4-.25.67-.15.28.1 1.78.84 2.09.99.3.15.51.22.58.35.08.12.08.72-.14 1.34z"/>
                        </svg>
                        Enviar por WhatsApp
                    </a>
                @endif
                <form action="{{ route('cuenta.carrito.vaciar') }}" method="POST" data-carrito>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="border-main text-secondary rounded-lg border px-5 py-2.5 text-sm font-semibold hover:underline">
                        Vaciar carrito
                    </button>
                </form>
            </div>
            <p class="text-secondary mt-3 text-xs">Cotización referencial: no reserva stock ni genera venta. El stock se confirma en el local.</p>
        @endif
    </div>

    @vite(['resources/js/cuenta/carrito.js'])
@endsection
