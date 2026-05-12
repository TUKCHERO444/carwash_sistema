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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Productos</h1>
        <a href="{{ route('productos.create') }}"
           aria-label="Crear producto"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Crear producto
        </a>
    </div>

    {{-- Table or empty state --}}
    @if($productos->isEmpty())
        <div class="text-center py-12 text-gray-500 dark:text-text-secondary-dark text-sm">
            No hay productos registrados.
        </div>
    @else
        <div class="bg-surface rounded-lg border border-main overflow-x-auto transition-colors duration-300">
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Foto
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Nombre
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Categoría
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Precio Compra
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Precio Venta
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Stock
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Activo
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @foreach($productos as $producto)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            {{-- Foto / miniatura --}}
                            <td class="px-4 py-6 whitespace-nowrap">
                                @if($producto->foto)
                                    <img src="{{ asset('storage/' . $producto->foto) }}"
                                         alt="Foto de {{ $producto->nombre }}"
                                         class="w-10 h-10 object-cover rounded border border-gray-200 dark:border-border-dark">
                                @else
                                    <div class="w-10 h-10 rounded border border-main bg-gray-100 dark:bg-slate-800 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                @endif
                            </td>

                            {{-- Nombre --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">
                                {{ $producto->nombre }}
                            </td>

                            {{-- Categoría --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                                {{ $producto->categoria->nombre ?? '—' }}
                            </td>

                            {{-- Precio Compra --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                                S/ {{ number_format($producto->precio_compra, 2) }}
                            </td>

                            {{-- Precio Venta --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                                S/ {{ number_format($producto->precio_venta, 2) }}
                            </td>

                            {{-- Stock --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-gray-700 dark:text-text-secondary-dark"
                                data-stock-value="{{ $producto->id }}">
                                {{ $producto->stock }}
                            </td>

                            {{-- Activo badge --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm">
                                <span data-producto-id="{{ $producto->id }}"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $producto->activo ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' }}">
                                    {{ $producto->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>

                            {{-- Acciones --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm flex items-center gap-2">
                                <a href="{{ route('productos.edit', $producto) }}"
                                   aria-label="Editar producto {{ $producto->nombre }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-xs font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Editar
                                </a>

                                <button type="button"
                                        data-toggle-status
                                        data-url="{{ route('productos.toggleStatus', $producto) }}"
                                        data-producto-id="{{ $producto->id }}"
                                        data-producto-nombre="{{ $producto->nombre }}"
                                        aria-label="{{ $producto->activo ? 'Inactivar' : 'Activar' }} producto {{ $producto->nombre }}"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                                               {{ $producto->activo
                                                    ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-400 hover:bg-yellow-200 dark:hover:bg-yellow-900/50'
                                                    : 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400 hover:bg-green-200 dark:hover:bg-green-900/50' }}">
                                    {{ $producto->activo ? 'Inactivar' : 'Activar' }}
                                </button>

                                <button
                                    type="button"
                                    data-stock-btn
                                    data-producto-id="{{ $producto->id }}"
                                    data-producto-nombre="{{ $producto->nombre }}"
                                    data-producto-stock="{{ $producto->stock }}"
                                    data-update-url="{{ route('productos.updateStock', $producto) }}"
                                    aria-label="Actualizar stock de {{ $producto->nombre }}"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-400 text-xs font-medium rounded-lg hover:bg-teal-200 dark:hover:bg-teal-900/50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M4 7v10c0 1.1.9 2 2 2h12a2 2 0 002-2V7M4 7h16M4 7l2-3h12l2 3"/>
                                    </svg>
                                    Stock
                                </button>

                                <form method="POST" action="{{ route('productos.destroy', $producto) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            aria-label="Eliminar producto {{ $producto->nombre }}"
                                            onclick="return confirm('¿Estás seguro de que deseas eliminar este producto?')"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs font-medium rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $productos->links() }}
        </div>
    @endif

</div>

@vite('resources/js/productos/index.js')

{{-- Stock Update Modal --}}
<div id="stock-modal" role="dialog" aria-modal="true" aria-labelledby="stock-modal-title"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-surface rounded-lg shadow-xl w-full max-w-sm mx-4 p-6 border border-main transition-colors duration-300">
        <h2 id="stock-modal-title" class="text-lg font-semibold text-primary mb-1">
            Actualizar stock
        </h2>
        <p id="stock-modal-nombre" class="text-sm text-secondary mb-4"></p>

        <div class="mb-4 p-3 bg-gray-50 dark:bg-slate-800/50 rounded-lg">
            <span class="text-xs text-secondary">Stock actual</span>
            <p id="stock-modal-stock-actual" class="text-2xl font-bold text-primary"></p>
        </div>

        <form id="stock-modal-form" novalidate>
            @csrf
            <input type="hidden" id="stock-modal-producto-id" name="producto_id">
            <input type="hidden" id="stock-modal-url" name="_url">

            <label for="stock-modal-cantidad" class="label-main mb-1">
                Cantidad adicional
            </label>
            <input type="number" id="stock-modal-cantidad" name="cantidad_adicional"
                   min="1" max="9999" step="1"
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 input-main"
                   placeholder="Ej: 50">
            <p id="stock-modal-error" role="alert" class="hidden mt-1 text-xs text-red-600"></p>
        </form>

        <div class="flex justify-end gap-2 mt-6">
            <button type="button" id="stock-modal-cancel"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-text-primary-dark bg-gray-100 dark:bg-slate-800 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                Cancelar
            </button>
            <button type="button" id="stock-modal-submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                Confirmar
            </button>
        </div>
    </div>
</div>
@endsection
