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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Ingresos</h1>
        <a href="{{ route('ingresos.create') }}"
           aria-label="Nuevo ingreso"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nuevo ingreso
        </a>
    </div>

    {{-- Table or empty state --}}
    @if($ingresos->isEmpty())
        <div class="text-center py-12 text-gray-500 dark:text-text-secondary-dark text-sm">No hay ingresos registrados.</div>
    @else
        <div class="bg-surface rounded-lg border border-main overflow-x-auto transition-colors duration-300">
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Foto
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Fecha
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Cliente
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Vehículo
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Trabajadores
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Precio
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Total
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Pago
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @foreach($ingresos as $ingreso)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            {{-- Foto --}}
                            <td class="px-4 py-6 whitespace-nowrap">
                                @if($ingreso->foto)
                                    <img src="{{ asset('storage/' . $ingreso->foto) }}"
                                         alt="Foto del ingreso"
                                         class="w-10 h-10 object-cover rounded border border-gray-200">
                                @else
                                    <div class="w-10 h-10 rounded border border-gray-200 dark:border-border-dark bg-gray-100 dark:bg-slate-800 flex items-center justify-center transition-colors">
                                        <svg class="w-5 h-5 text-gray-400 dark:text-text-secondary-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-gray-900 dark:text-text-primary-dark">
                                {{ $ingreso->fecha->format('d/m/Y') }}
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-gray-700 dark:text-text-secondary-dark">
                                {{ $ingreso->cliente->placa }}
                                @if($ingreso->cliente->nombre)
                                    — {{ $ingreso->cliente->nombre }}
                                @endif
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-gray-700 dark:text-text-secondary-dark">
                                {{ $ingreso->vehiculo->nombre }}
                            </td>
                            <td class="px-6 py-8 text-sm text-gray-700 dark:text-text-secondary-dark">
                                {{ $ingreso->trabajadores->pluck('nombre')->implode(', ') }}
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-gray-700 dark:text-text-secondary-dark">
                                S/ {{ number_format($ingreso->precio, 2) }}
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-gray-700 dark:text-text-secondary-dark">
                                S/ {{ number_format($ingreso->total, 2) }}
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap">
                                 @php
                                    $colores = [
                                        'efectivo' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                                        'yape'     => 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400',
                                        'izipay'   => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
                                        'mixto'    => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400'
                                    ];
                                    $labels  = ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto'];
                                    $metodo  = $ingreso->metodo_pago ?? 'efectivo';
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $colores[$metodo] ?? 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-secondary-dark' }}">
                                    {{ $labels[$metodo] ?? ucfirst($metodo) }}
                                </span>
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm flex items-center gap-2">
                                {{-- Ver detalle --}}
                                <a href="{{ route('ingresos.show', $ingreso) }}"
                                   aria-label="Ver detalle del ingreso"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-xs font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Ver detalle
                                </a>

                                {{-- Ticket --}}
                                <a href="{{ route('ingresos.ticket', $ingreso) }}"
                                   aria-label="Ticket del ingreso"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs font-medium rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Ticket
                                </a>

                                {{-- Editar --}}
                                <a href="{{ route('ingresos.edit', $ingreso) }}"
                                   aria-label="Editar ingreso"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-xs font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Editar
                                </a>

                                {{-- Eliminar --}}
                                <form method="POST" action="{{ route('ingresos.destroy', $ingreso) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            aria-label="Eliminar ingreso"
                                            onclick="return confirm('¿Está seguro de eliminar este ingreso?')"
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
            {{ $ingresos->links() }}
        </div>
    @endif

</div>
@endsection
