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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Nuevo Ajuste de Inventario</h1>
        <a href="{{ route('ajustes.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </a>
    </div>

    {{-- Form --}}
    <div class="bg-surface rounded-lg border border-main p-6 max-w-3xl">
        <form id="form-ajuste" action="{{ route('ajustes.store') }}" method="POST" novalidate>
            @csrf

            {{-- Tipo de ajuste --}}
            <div class="mb-5">
                <label for="tipo" class="label-main mb-1">
                    Tipo de Ajuste <span class="text-red-500">*</span>
                </label>
                <select
                    id="tipo"
                    name="tipo"
                    required
                    onchange="actualizarCamposPorTipo(this.value)"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('tipo') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}">
                    <option value="">Seleccione un tipo</option>
                    <option value="positivo" {{ old('tipo') === 'positivo' ? 'selected' : '' }}>Positivo (entrada)</option>
                    <option value="negativo" {{ old('tipo') === 'negativo' ? 'selected' : '' }}>Negativo (salida)</option>
                    <option value="merma" {{ old('tipo') === 'merma' ? 'selected' : '' }}>Merma</option>
                    <option value="daño" {{ old('tipo') === 'daño' ? 'selected' : '' }}>Daño</option>
                    <option value="conteo_fisico" {{ old('tipo') === 'conteo_fisico' ? 'selected' : '' }}>Conteo Físico</option>
                </select>
                @error('tipo')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Motivo (condicional) --}}
            <div id="campo-motivo" class="mb-5 hidden">
                <label for="motivo" class="label-main mb-1">
                    Motivo <span class="text-red-500">*</span>
                </label>
                <textarea
                    id="motivo"
                    name="motivo"
                    rows="2"
                    maxlength="500"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('motivo') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Explique el motivo del ajuste (obligatorio para merma, daño, conteo físico)"></textarea>
                @error('motivo')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <p id="ayuda-motivo" class="mt-1 text-xs text-secondary hidden">
                    Obligatorio para merma, daño y conteo físico. Mínimo 10 caracteres.
                </p>
            </div>

            {{-- Observaciones --}}
            <div class="mb-5">
                <label for="observaciones" class="label-main mb-1">
                    Observaciones <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <textarea
                    id="observaciones"
                    name="observaciones"
                    rows="2"
                    maxlength="500"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors input-main
                           {{ $errors->has('observaciones') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}"
                    placeholder="Notas adicionales...">{{ old('observaciones') }}</textarea>
                @error('observaciones')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Detalle de productos (líneas dinámicas) --}}
            <div class="mb-6">
                <div class="flex items-center justify-between mb-3">
                    <label class="label-main mb-0">Productos <span class="text-red-500">*</span></label>
                    <button type="button" id="btn-agregar-linea"
                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs font-medium rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Agregar línea
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table id="tabla-detalle"
                           data-validate-table
                           data-validate-min-rows="1"
                           data-validate-error-id="error-detalle"
                           class="min-w-full divide-y divide-main">
                        <thead class="bg-gray-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                                    Producto
                                </th>
                                <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                                    Tipo Línea
                                </th>
                                <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                                    Stock Actual
                                </th>
                                <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                                    Cantidad / Conteo
                                </th>
                                <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                                    Eliminar
                                </th>
                            </tr>
                        </thead>
                        <tbody id="tbody-detalle" class="bg-surface divide-y divide-main">
                            {{-- Filas renderizadas por JS --}}
                        </tbody>
                    </table>
                    <p id="error-detalle" class="hidden mt-2 text-xs text-red-600 dark:text-red-400"></p>
                </div>
            </div>

            {{-- Buttons --}}
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    aria-label="Guardar ajuste"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar ajuste
                </button>
                <a href="{{ route('ajustes.index') }}"
                   class="px-5 py-2 text-sm font-medium text-gray-600 dark:text-text-secondary-dark hover:text-gray-900 dark:hover:text-text-primary-dark transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

    {{-- Data para el JS (productos activos) --}}
    <script>
        window.productos = @json($productos ?? []);
        window.tipoAjusteInicial = "{{ old('tipo') }}";
    </script>

</div>
@vite('resources/js/ajustes/create.js')
@endsection