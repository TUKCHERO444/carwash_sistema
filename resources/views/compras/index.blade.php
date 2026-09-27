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

    {{-- Header + filters --}}
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Compras</h1>
            <a href="{{ route('compras.create') }}"
               aria-label="Crear compra"
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nueva compra
            </a>
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ route('compras.index') }}" class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
                <label for="estado" class="text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Estado:</label>
                <select name="estado" id="estado"
                        class="px-3 py-2 text-sm border border-main rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                        aria-label="Filtrar por estado">
                    <option value="">Todos</option>
                    <option value="borrador" {{ request('estado') === 'borrador' ? 'selected' : '' }}>Borrador</option>
                    <option value="recibida" {{ request('estado') === 'recibida' ? 'selected' : '' }}>Recibida</option>
                    <option value="anulada" {{ request('estado') === 'anulada' ? 'selected' : '' }}>Anulada</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <label for="proveedor_id" class="text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Proveedor:</label>
                <select name="proveedor_id" id="proveedor_id"
                        class="px-3 py-2 text-sm border border-main rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                        aria-label="Filtrar por proveedor">
                    <option value="">Todos</option>
                    @foreach($proveedores as $id => $nombre)
                        <option value="{{ $id }}" {{ request('proveedor_id') == $id ? 'selected' : '' }}>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    {{-- Table or empty state --}}
    @if($compras->isEmpty())
        <div class="text-center py-12 text-gray-500 text-sm">
            No hay compras registradas.
        </div>
    @else
        <div class="bg-surface rounded-lg border border-main overflow-x-auto transition-colors duration-300">
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Correlativo
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Proveedor
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Fecha
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Tipo Doc.
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Nº Documento
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Estado
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Subtotal
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Total
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @foreach($compras as $compra)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            {{-- Correlativo --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">
                                {{ $compra->correlativo ?? '<span class="text-gray-400 italic">—</span>' }}
                            </td>

                            {{-- Proveedor --}}
                            <td class="px-6 py-8 text-sm text-primary font-medium">
                                {{ $compra->proveedor->razon_social ?? '—' }}
                            </td>

                            {{-- Fecha --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                                {{ $compra->fecha->format('d/m/Y') }}
                            </td>

                            {{-- Tipo documento --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                                {{ $compra->tipo_documento ?? '—' }}
                            </td>

                            {{-- Número documento --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                                {{ $compra->numero_documento ?? '—' }}
                            </td>

                            {{-- Estado --}}
                            <td class="px-6 py-8 whitespace-nowrap">
                                @php
                                    $badge = match($compra->estado) {
                                        'recibida' => ['Recibida', 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'],
                                        'anulada' => ['Anulada', 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'],
                                        default => ['Borrador', 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'],
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge[1] }}">
                                    {{ $badge[0] }}
                                </span>
                            </td>

                            {{-- Subtotal --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">
                                S/ {{ number_format($compra->subtotal, 2) }}
                            </td>

                            {{-- Total --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary font-medium font-mono">
                                S/ {{ number_format($compra->total, 2) }}
                            </td>

                            {{-- Acciones --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm flex items-center gap-2">
                                <a href="{{ route('compras.show', $compra) }}"
                                   aria-label="Ver compra {{ $compra->correlativo ?? '#'.$compra->id }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-xs font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Ver
                                </a>

                                @can('acceso-compras')
                                @if($compra->esBorrador())
                                <a href="{{ route('compras.edit', $compra) }}"
                                   aria-label="Editar compra {{ $compra->correlativo ?? '#'.$compra->id }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs font-medium rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Editar
                                </a>

                                <form method="POST" action="{{ route('compras.destroy', $compra) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            aria-label="Eliminar compra {{ $compra->correlativo ?? '#'.$compra->id }}"
                                            data-confirm="¿Estás seguro de eliminar este borrador de compra?"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs font-medium rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Eliminar
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('compras.anular', $compra) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                            aria-label="Anular compra {{ $compra->correlativo ?? '#'.$compra->id }}"
                                            data-confirm="¿Anular esta compra? Quedará en estado Anulada y no se podrá editar."
                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-xs font-medium rounded-lg hover:bg-yellow-200 dark:hover:bg-yellow-900/50 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Anular
                                    </button>
                                </form>
                                @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $compras->links() }}
        </div>
    @endif

</div>
@endsection