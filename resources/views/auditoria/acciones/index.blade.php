@extends('layouts.app')

@section('content')
<div class="p-6">

    @php
        $showLinks = [
            'servicios' => 'servicios.show',
            'vehiculos' => 'vehiculos.show',
            'clientes' => 'clientes.show',
            'automotores' => 'automotores.show',
            'ventas' => 'ventas.show',
            'cambio_aceite' => 'cambio-aceite.show',
            'lavados' => 'lavados.show',
        ];

        $accionColor = [
            'crear' => 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400',
            'actualizar' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400',
            'eliminar' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
            'confirmar' => 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-800 dark:text-indigo-400',
            'inicio de sesión' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-400',
            'cierre de sesión' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-400',
        ];
        $accionColorDefault = 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark';
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Auditoría de acciones</h1>
            <p class="text-sm text-secondary mt-1">Registro de toda la actividad CRUD del sistema y de sesiones.</p>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('auditoria.acciones.index') }}" class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <div>
            <label for="modulo" class="block text-xs font-medium text-secondary mb-1">Módulo</label>
            <select name="modulo" id="modulo" class="w-full rounded-lg border border-main bg-surface text-sm px-3 py-2">
                <option value="">Todos</option>
                @foreach($modulos as $valor => $label)
                    <option value="{{ $valor }}" @selected(($filtros['modulo'] ?? '') === $valor)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="accion" class="block text-xs font-medium text-secondary mb-1">Acción</label>
            <select name="accion" id="accion" class="w-full rounded-lg border border-main bg-surface text-sm px-3 py-2">
                <option value="">Todas</option>
                @foreach($acciones as $accion)
                    <option value="{{ $accion }}" @selected(($filtros['accion'] ?? '') === $accion)>{{ $accion }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="usuario_id" class="block text-xs font-medium text-secondary mb-1">Id de registro</label>
            <input type="text" name="registro_id" id="registro_id" value="{{ $filtros['registro_id'] ?? '' }}"
                   placeholder="Id del registro afectado"
                   class="w-full rounded-lg border border-main bg-surface text-sm px-3 py-2">
        </div>

        <div>
            <label for="desde" class="block text-xs font-medium text-secondary mb-1">Desde</label>
            <input type="date" name="desde" id="desde" value="{{ $filtros['desde'] ?? '' }}"
                   class="w-full rounded-lg border border-main bg-surface text-sm px-3 py-2">
        </div>

        <div>
            <label for="hasta" class="block text-xs font-medium text-secondary mb-1">Hasta</label>
            <input type="date" name="hasta" id="hasta" value="{{ $filtros['hasta'] ?? '' }}"
                   class="w-full rounded-lg border border-main bg-surface text-sm px-3 py-2">
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                Filtrar
            </button>
            <a href="{{ route('auditoria.acciones.index') }}"
               class="px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                Limpiar
            </a>
        </div>
    </form>

    {{-- Table or empty state --}}
    @if($registros->isEmpty())
        <div class="text-center py-12 text-gray-500 text-sm">
            No hay acciones registradas.
        </div>
    @else
        <div class="bg-surface rounded-lg border border-main overflow-x-auto transition-colors duration-300">
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Fecha / hora</th>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Módulo</th>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Acción</th>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Registro</th>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Usuario</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @foreach($registros as $reg)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-secondary">
                                {{ $reg->fecha_movimiento?->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-4 text-sm text-primary font-medium">
                                {{ $modulos[$reg->modulo] ?? $reg->modulo }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $accionColor[$reg->accion] ?? $accionColorDefault }}">
                                    {{ $reg->accion }}
                                </span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-mono text-secondary">
                                @php($routeShow = $showLinks[$reg->modulo] ?? null)
                                @if($routeShow && \Illuminate\Support\Facades\Route::has($routeShow) && $reg->auditable)
                                    <a href="{{ route($routeShow, $reg->auditable) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        #{{ $reg->auditable_id }}
                                    </a>
                                @else
                                    #{{ $reg->auditable_id }}
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-secondary">{{ $reg->usuario?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $registros->links() }}
        </div>
    @endif

</div>
@endsection
