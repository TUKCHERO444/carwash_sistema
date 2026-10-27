@if($caja && $puedeRegistrarEgreso)
<x-modal id="modal-registrar-egreso" title="Registrar Egreso Manual" :open="session()->hasOldInput('monto') || session()->hasOldInput('descripcion')">
    <form action="{{ route('caja.egresos.store') }}" method="POST" id="form-registrar-egreso">
        @csrf
        <div class="px-6 pb-4 sm:pb-6 space-y-4">
            <div>
                <label for="monto" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Monto (S/)</label>
                <input type="number" step="0.01" min="0.01" name="monto" id="monto" required value="{{ old('monto') }}" class="mt-1 block w-full border-gray-300 dark:border-border-dark bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="0.00">
            </div>
            <div>
                <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Descripción</label>
                <input type="text" name="descripcion" id="descripcion" required value="{{ old('descripcion') }}" class="mt-1 block w-full border-gray-300 dark:border-border-dark bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Ej. Productos de limpieza">
                <p class="mt-1 text-xs text-gray-500 dark:text-text-secondary-dark">Obligatoria: explica el motivo del gasto. Las compras de mercadería para inventario van en el módulo de Compras, no aquí.</p>
            </div>
            <div>
                <label for="categoria" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Categoría</label>
                <select name="categoria" id="categoria" required class="mt-1 block w-full border-gray-300 dark:border-border-dark bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">Seleccione…</option>
                    @foreach($categoriasGasto as $valor => $etiqueta)
                        <option value="{{ $valor }}" {{ old('categoria') === (string) $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="trabajador_id" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Trabajador <span class="text-xs text-gray-400 font-normal">(solo para Pago a trabajador)</span></label>
                <select name="trabajador_id" id="trabajador_id" class="mt-1 block w-full border-gray-300 dark:border-border-dark bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">Ninguno</option>
                    @foreach($trabajadores as $trabajador)
                        <option value="{{ $trabajador->id }}" {{ (string) old('trabajador_id') === (string) $trabajador->id ? 'selected' : '' }}>{{ $trabajador->nombre_completo }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="tipo_pago" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Tipo de Pago</label>
                <select name="tipo_pago" id="tipo_pago" required class="mt-1 block w-full border-gray-300 dark:border-border-dark bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="efectivo" {{ old('tipo_pago', 'efectivo') === 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                    <option value="yape" {{ old('tipo_pago') === 'yape' ? 'selected' : '' }}>Yape</option>
                </select>
            </div>
        </div>
    </form>
    <x-slot:footer>
        <button type="submit" form="form-registrar-egreso" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:text-sm">
            Registrar Egreso
        </button>
        <button type="button" data-modal-close class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 dark:border-border-dark shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-medium text-gray-700 dark:text-text-primary-dark hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm transition-colors">
            Cancelar
        </button>
    </x-slot:footer>
</x-modal>
@endif
