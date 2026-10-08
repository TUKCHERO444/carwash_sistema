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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Clientes</h1>
        <a href="{{ route('clientes.create') }}"
           aria-label="Crear cliente"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Crear cliente
        </a>
    </div>

    {{-- Table or empty state --}}
    @if($clientes->isEmpty())
        <div class="text-center py-12 text-gray-500 text-sm">
            No hay clientes registrados.
        </div>
    @else
        <div class="bg-surface rounded-lg border border-main overflow-x-auto transition-colors duration-300">
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            DNI
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Nombre
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @foreach($clientes as $cliente)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">
                                {{ $cliente->dni }}
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">
                                {{ $cliente->nombre_completo }}
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm flex items-center gap-2">
                                <a href="{{ route('clientes.edit', $cliente) }}"
                                   aria-label="Editar cliente {{ $cliente->nombre_completo }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-xs font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Editar
                                </a>

                                <button type="button"
                                        aria-label="Ver lavados del cliente {{ $cliente->nombre_completo }}"
                                        data-cliente-lavados="{{ $cliente->id }}"
                                        data-url="{{ route('clientes.resumenLavados', $cliente) }}"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-400 text-xs font-medium rounded-lg hover:bg-sky-200 dark:hover:bg-sky-900/50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M4 7h16M4 7a2 2 0 012-2h12a2 2 0 012 2m0 0v10a2 2 0 01-2 2H6a2 2 0 01-2-2V7zm6 4h8m-8 4h5"/>
                                    </svg>
                                    Lavados
                                </button>

                                <form method="POST" action="{{ route('clientes.destroy', $cliente) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            aria-label="Eliminar cliente {{ $cliente->nombre_completo }}"
                                            data-confirm="¿Estás seguro de que deseas eliminar este cliente?"
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
            {{ $clientes->links() }}
        </div>
    @endif

    {{-- Modal de frecuencia de lavados (contenido vía Ajax, sin recargar) --}}
    <x-modal id="modal-lavados-cliente" title="Lavados del cliente">
        <div class="px-6 pb-4 sm:pb-6">
            <p id="lavados-cargando" class="text-sm text-secondary">Cargando resumen…</p>
            <p id="lavados-error" class="hidden text-sm text-red-600 dark:text-red-400">No se pudo cargar el resumen. Intente nuevamente.</p>
            <div id="lavados-contenido" class="hidden">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Cliente</dt>
                        <dd id="lavados-nombre" class="mt-1 text-sm text-primary font-medium"></dd>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">DNI</p>
                        <p id="lavados-dni" class="mt-1 text-sm text-primary"></p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Teléfono</p>
                        <p id="lavados-telefono" class="mt-1 text-sm text-primary"></p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Total de lavados confirmados</p>
                        <p id="lavados-total" class="mt-1 text-2xl font-bold text-primary"></p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Último lavado</p>
                        <p id="lavados-ultimo" class="mt-1 text-sm text-primary"></p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Vehículos</p>
                        <ul id="lavados-vehiculos" class="mt-1 text-sm text-primary list-disc list-inside"></ul>
                    </div>
                </dl>
            </div>
        </div>
        <x-slot:footer>
            <button type="button" data-modal-close class="w-full sm:w-auto inline-flex justify-center rounded-md border border-main shadow-sm px-4 py-2 bg-surface text-base font-medium text-secondary hover:bg-slate-50 dark:hover:bg-slate-700 sm:text-sm">
                Cerrar
            </button>
        </x-slot:footer>
    </x-modal>

</div>
@vite('resources/js/clientes/index.js')
@endsection
