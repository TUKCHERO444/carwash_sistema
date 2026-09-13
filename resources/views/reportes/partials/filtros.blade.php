{{-- Barra de filtros por rango de fechas. Recibe $ruta (nombre de ruta) y
     los selects contextuales se inyectan con @push('filtros-reporte'). --}}
<form method="GET" action="{{ route($ruta) }}"
      class="print:hidden flex flex-wrap items-end gap-3 bg-surface rounded-lg border border-main p-4 mb-6">
    <div>
        <label for="desde" class="label-main mb-1 inline-block">Desde</label>
        <input type="date" id="desde" name="desde" value="{{ request('desde') }}"
               class="input-main px-3 py-2 border rounded-lg text-sm">
    </div>
    <div>
        <label for="hasta" class="label-main mb-1 inline-block">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="{{ request('hasta') }}"
               class="input-main px-3 py-2 border rounded-lg text-sm">
    </div>

    @stack('filtros-reporte')

    <button type="submit"
            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
        </svg>
        Filtrar
    </button>
    <a href="{{ route($ruta) }}"
       class="px-4 py-2 text-sm font-medium text-secondary hover:text-primary transition-colors">
        Limpiar
    </a>

    @if($errors->any())
        <p class="w-full text-xs text-red-600">{{ $errors->first() }}</p>
    @endif
</form>