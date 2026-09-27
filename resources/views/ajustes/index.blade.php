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
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Ajustes de Inventario</h1>
            <a href="{{ route('ajustes.create') }}"
               aria-label="Crear ajuste"
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nuevo ajuste
            </a>
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ route('ajustes.index') }}" class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
                <label for="tipo" class="text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Tipo:</label>
                <select name="tipo" id="tipo"
                        class="px-3 py-2 text-sm border border-main rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                        aria-label="Filtrar por tipo">
                    <option value="">Todos</option>
                    <option value="positivo" {{ request('tipo') === 'positivo' ? 'selected' : '' }}>Positivo</option>
                    <option value="negativo" {{ request('tipo') === 'negativo' ? 'selected' : '' }}>Negativo</option>
                    <option value="merma" {{ request('tipo') === 'merma' ? 'selected' : '' }}>Merma</option>
                    <option value="daño" {{ request('tipo') === 'daño' ? 'selected' : '' }}>Daño</option>
                    <option value="conteo_fisico" {{ request('tipo') === 'conteo_fisico' ? 'selected' : '' }}>Conteo Físico</option>
                </select>
            </div>
        </form>
    </div>

    {{-- Table or empty state --}}
    @if($ajustes->isEmpty())
        <div class="text-center py-12 text-gray-500 text-sm">
            No hay ajustes registrados.
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
                            Tipo
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Motivo
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Usuario
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Fecha
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @foreach($ajustes as $ajuste)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            {{-- Correlativo --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">
                                {{ $ajuste->correlativo }}
                            </td>

                            {{-- Tipo --}}
                            <td class="px-6 py-8 whitespace-nowrap">
                                @php
                                    $badge = match($ajuste->tipo) {
                                        'positivo' => ['Ajuste Positivo', 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'],
                                        'negativo' => ['Ajuste Negativo', 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'],
                                        'merma' => ['Merma', 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'],
                                        'daño' => ['Daño', 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'],
                                        'conteo_fisico' => ['Conteo Físico', 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'],
                                        default => ['—', 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'],
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge[1] }}">
                                    {{ $badge[0] }}
                                </span>
                            </td>

                            {{-- Motivo --}}
                            <td class="px-6 py-8 text-sm text-secondary">
                                {{ $ajuste->motivo ?? '—' }}
                            </td>

                            {{-- Usuario --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                                {{ $ajuste->user->name ?? '—' }}
                            </td>

                            {{-- Fecha --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                                {{ $ajuste->created_at->format('d/m/Y H:i') }}
                            </td>

                            {{-- Acciones --}}
                            <td class="px-6 py-8 whitespace-nowrap text-sm flex items-center gap-2">
                                <a href="{{ route('ajustes.show', $ajuste) }}"
                                   aria-label="Ver ajuste {{ $ajuste->correlativo }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-xs font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $ajustes->links() }}
        </div>
    @endif

</div>
@endsection