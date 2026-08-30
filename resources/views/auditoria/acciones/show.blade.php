@extends('layouts.app')

@section('content')
<div class="p-6">

    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('auditoria.acciones.index') }}"
               class="text-sm text-blue-600 dark:text-blue-400 hover:underline mb-2 inline-block">← Volver al listado</a>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Detalle de acción</h1>
        </div>
    </div>

    <div class="bg-surface rounded-lg border border-main p-6 transition-colors duration-300">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-xs font-medium text-secondary uppercase tracking-wider mb-1">Fecha / hora</dt>
                <dd class="text-primary">{{ $registroAuditoria->fecha_movimiento?->format('d/m/Y H:i:s') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-secondary uppercase tracking-wider mb-1">Usuario</dt>
                <dd class="text-primary">{{ $registroAuditoria->usuario?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-secondary uppercase tracking-wider mb-1">Módulo</dt>
                <dd class="text-primary font-medium">{{ $registroAuditoria->modulo }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-secondary uppercase tracking-wider mb-1">Acción</dt>
                <dd class="text-primary font-medium">{{ $registroAuditoria->accion }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-secondary uppercase tracking-wider mb-1">Registro afectado</dt>
                <dd class="text-primary font-mono">#{{ $registroAuditoria->auditable_id }}
                    <span class="text-secondary">({{ class_basename($registroAuditoria->auditable_type) }})</span>
                </dd>
            </div>
        </dl>
    </div>

    {{-- Detalle de cambios --}}
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-surface rounded-lg border border-main p-6 transition-colors duration-300">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-text-primary-dark mb-4">Valores anteriores</h2>
            @if(empty($registroAuditoria->datos_antes))
                <p class="text-sm text-secondary">Sin datos (creación de registro o no aplica).</p>
            @else
                <table class="min-w-full divide-y divide-main text-sm">
                    <tbody class="divide-y divide-main">
                        @foreach($registroAuditoria->datos_antes as $campo => $valor)
                            <tr>
                                <td class="py-2 pr-4 font-mono text-xs text-secondary">{{ $campo }}</td>
                                <td class="py-2 text-primary break-all">{{ is_scalar($valor) ? $valor : json_encode($valor) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="bg-surface rounded-lg border border-main p-6 transition-colors duration-300">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-text-primary-dark mb-4">Valores posteriores</h2>
            @if(empty($registroAuditoria->datos_despues))
                <p class="text-sm text-secondary">Sin datos (eliminación de registro o no aplica).</p>
            @else
                <table class="min-w-full divide-y divide-main text-sm">
                    <tbody class="divide-y divide-main">
                        @foreach($registroAuditoria->datos_despues as $campo => $valor)
                            <tr>
                                <td class="py-2 pr-4 font-mono text-xs text-secondary">{{ $campo }}</td>
                                <td class="py-2 text-primary break-all">{{ is_scalar($valor) ? $valor : json_encode($valor) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

</div>
@endsection
