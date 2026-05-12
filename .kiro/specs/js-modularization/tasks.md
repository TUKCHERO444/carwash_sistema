# Plan de Implementación: Modularización de JavaScript

## Visión General

Extraer el JavaScript embebido de 7 vistas Blade hacia módulos ES dedicados en `resources/js/`, organizados por módulo de negocio, registrados como entry points en Vite. La migración preserva el 100% de la funcionalidad existente.

## Tareas

- [x] 1. Instalar dependencia de testing y configurar Vite con los nuevos entry points
  - Instalar `fast-check` como dependencia de desarrollo: `npm install --save-dev fast-check`
  - Actualizar `vite.config.js` para registrar los 7 nuevos entry points JS (ventas/create.js, ingresos/create.js, ingresos/edit.js, ingresos/confirmar.js, cambio-aceite/create.js, cambio-aceite/edit.js, productos/edit.js) manteniendo los existentes sin cambios
  - Verificar que `npm run build` compila sin errores con los entry points vacíos (crear archivos placeholder temporales si es necesario)
  - _Requirements: 4.1, 4.2, 4.3_

- [x] 2. Crear módulo `productos/edit.js` y migrar su vista
  - [x] 2.1 Crear `resources/js/productos/edit.js` con la lógica de preview de imagen extraída de `productos/edit.blade.php`
    - Envolver la lógica en `document.addEventListener('DOMContentLoaded', ...)` 
    - Exportar la función `initFotoPreview` para permitir testing
    - _Requirements: 2.1, 2.2, 2.3, 3.1, 3.2, 3.3_
  - [x] 2.2 Actualizar `resources/views/productos/edit.blade.php`
    - Eliminar el bloque `<script>` con la lógica de preview
    - Agregar `@vite('resources/js/productos/edit.js')` al final de `@section('content')` antes de `@endsection`
    - _Requirements: 5.1, 5.2, 8.1, 8.3_
  - [ ]* 2.3 Escribir unit test para `productos/edit.js`
    - Verificar que el archivo exporta `initFotoPreview`
    - Verificar que no contiene etiquetas `<script>` ni directivas Blade
    - _Requirements: 7.4_

- [x] 3. Crear módulo `ventas/create.js` y migrar su vista
  - [x] 3.1 Crear `resources/js/ventas/create.js` con toda la lógica extraída de `ventas/create.blade.php`
    - Incluir: búsqueda Ajax con debounce, `agregarProducto`, `renderTabla`, `actualizarCantidad`, `eliminarItem`, `recalcularTotales`, `sincronizarHiddens`, lógica de método de pago y `validarMixto`
    - Exportar las funciones puras de cálculo: `calcularTotal(items, porcentaje)`, `renderTablaHTML(items)`, `sincronizarHiddens(items, form, className, fields)`
    - Envolver la inicialización de event listeners en `document.addEventListener('DOMContentLoaded', ...)`
    - _Requirements: 2.1, 2.2, 2.3, 3.1, 3.2, 3.3, 6.5_
  - [x] 3.2 Actualizar `resources/views/ventas/create.blade.php`
    - Eliminar el bloque `<script>` con toda la lógica JS
    - Agregar `@vite('resources/js/ventas/create.js')` al final de `@section('content')` antes de `@endsection`
    - _Requirements: 5.1, 5.2, 8.1, 8.3_
  - [ ]* 3.3 Escribir property test — Propiedad 1: total sin descuento = suma de subtotales
    - **Property 1: Cálculo de total sin descuento es la suma de subtotales**
    - **Validates: Requirements 7.3**
    - Usar `fast-check` con `fc.array` de items con `subtotal` float, verificar que `calcularTotal(items, 0)` ≈ suma de subtotales (tolerancia 0.001)
    - Mínimo 100 iteraciones
  - [ ]* 3.4 Escribir property test — Propiedad 2: total con descuento por porcentaje
    - **Property 2: Cálculo de total con descuento por porcentaje**
    - **Validates: Requirements 7.3**
    - Usar `fast-check` con `fc.array` de items y `fc.float({ min: 0, max: 100 })` para porcentaje, verificar que `calcularTotal(items, p)` ≈ `suma * (1 - p/100)` (tolerancia 0.001)
    - Mínimo 100 iteraciones

- [x] 4. Checkpoint — Verificar módulos simples
  - Ejecutar `npm run build` y confirmar que `ventas/create.js` y `productos/edit.js` generan archivos en `public/build/`
  - Verificar manualmente que la vista `ventas/create` carga y la búsqueda de productos funciona
  - Asegurarse de que todos los tests pasan, consultar al usuario si surgen dudas

- [x] 5. Crear `ingresos/shared.js` con la lógica compartida del módulo ingresos
  - [x] 5.1 Crear `resources/js/ingresos/shared.js` exportando las funciones comunes identificadas en el diseño
    - Exportar: `initBusquedaServicios({ inputId, resultadosId, onAgregar })`, `renderTablaServicios(items, tbodyId, onEliminar)`, `sincronizarHiddens(items, formId)`, `initFotoPreview(inputId, previewId, currentId)`, `initMetodoPago({ options, radios, bloqueMixto, inputTotal, inputAncla, toggleDescManual })`, `validarMixto(inputTotal, montoIds, alertaId)`, `recalcularTotales(items, vehiculoSelectId, precioId, totalId, toggleDescuentoId, porcentajeId)`
    - Exportar también las funciones puras de cálculo para testing: `calcularPrecio(precioVehiculo, items)`, `calcularTotalConDescuento(precio, porcentaje)`
    - _Requirements: 6.1, 6.2, 6.3_
  - [ ]* 5.2 Escribir property test — Propiedad 3: renderizado de tabla produce exactamente N filas
    - **Property 3: Renderizado de tabla produce exactamente N filas**
    - **Validates: Requirements 7.6**
    - Usar `fast-check` con `fc.array` de items de servicio (N >= 1), verificar que `renderTablaServicios` produce exactamente N `<tr>` en el HTML resultante; para N=0 produce 1 fila con mensaje vacío
    - Mínimo 100 iteraciones
  - [ ]* 5.3 Escribir property test — Propiedad 4: sincronización de hidden inputs refleja el estado actual
    - **Property 4: Sincronización de hidden inputs refleja el estado actual**
    - **Validates: Requirements 7.3, 7.6**
    - Usar `fast-check` con `fc.array` de items de servicio, verificar que tras llamar a `sincronizarHiddens` el formulario contiene exactamente tantos hidden inputs con prefijo `servicios[N]` como items hay, sin duplicados
    - Mínimo 100 iteraciones

- [x] 6. Crear `ingresos/create.js` e importar desde shared
  - [x] 6.1 Crear `resources/js/ingresos/create.js`
    - Importar las funciones necesarias desde `./shared.js`
    - Implementar la lógica específica de create: inicialización con `items = []`, llamada a `actualizarPrecioDisplay` (indicador visual, no `recalcularTotales` completo), inicialización de foto preview sin `currentId`
    - Envolver en `document.addEventListener('DOMContentLoaded', ...)`
    - _Requirements: 2.1, 2.2, 2.3, 3.1, 3.2, 3.3, 6.4_
  - [x] 6.2 Actualizar `resources/views/ingresos/create.blade.php`
    - Eliminar el bloque `<script>` con toda la lógica JS
    - Agregar `@vite('resources/js/ingresos/create.js')` al final de `@section('content')` antes de `@endsection`
    - No requiere bloque de inicialización de datos (no hay datos PHP que pasar)
    - _Requirements: 5.1, 5.2, 8.1, 8.3_
  - [ ]* 6.3 Escribir unit test para `ingresos/create.js`
    - Verificar que importa desde `./shared.js`
    - Verificar que no contiene definiciones duplicadas de funciones ya exportadas por shared
    - _Requirements: 7.1, 7.2_

- [x] 7. Crear `ingresos/edit.js` e importar desde shared
  - [x] 7.1 Crear `resources/js/ingresos/edit.js`
    - Importar las funciones necesarias desde `./shared.js`
    - Leer datos iniciales desde `window.serviciosExistentes ?? []`, `window.ingresoMetodoPago ?? 'efectivo'`, `window.ingresoMontos ?? {}`
    - Inicializar `items` con los servicios existentes, llamar a `renderTablaServicios`, `sincronizarHiddens` y `initMetodoPago` al cargar
    - Inicializar foto preview con `currentId = 'foto-current'`
    - _Requirements: 2.1, 2.2, 2.3, 3.1, 3.2, 3.4, 6.4, 7.5_
  - [x] 7.2 Actualizar `resources/views/ingresos/edit.blade.php`
    - Eliminar el bloque `<script>` con toda la lógica JS
    - Agregar bloque `<script>` de inicialización de datos con `window.serviciosExistentes`, `window.ingresoMetodoPago` y `window.ingresoMontos`
    - Agregar `@vite('resources/js/ingresos/edit.js')` después del bloque de datos
    - _Requirements: 3.4, 3.5, 5.1, 5.2, 5.3, 7.5, 8.1, 8.2_
  - [ ]* 7.3 Escribir unit test para `ingresos/edit.js`
    - Verificar que lee `window.serviciosExistentes ?? []` con fallback seguro
    - Verificar que importa desde `./shared.js`
    - _Requirements: 7.1, 7.5_

- [x] 8. Crear `ingresos/confirmar.js` e importar desde shared
  - [x] 8.1 Crear `resources/js/ingresos/confirmar.js`
    - Importar las funciones necesarias desde `./shared.js`
    - Leer datos iniciales desde `window.serviciosConfirmar ?? []`, `window.confirmarMetodoPago ?? 'efectivo'`, `window.confirmarMontos ?? {}`
    - Implementar las funciones específicas de confirmar: `submitActualizar()` y `confirmarEliminacion()` (expuestas en `window` para los `onclick` del HTML)
    - Inicializar tabla, hiddens y método de pago al cargar
    - _Requirements: 2.1, 2.2, 2.3, 3.1, 3.2, 3.4, 6.4, 7.5_
  - [x] 8.2 Actualizar `resources/views/ingresos/confirmar.blade.php`
    - Eliminar el bloque `<script>` con toda la lógica JS
    - Agregar bloque `<script>` de inicialización de datos con `window.serviciosConfirmar`, `window.confirmarMetodoPago` y `window.confirmarMontos`
    - Agregar `@vite('resources/js/ingresos/confirmar.js')` después del bloque de datos
    - _Requirements: 3.4, 3.5, 5.1, 5.2, 5.3, 7.5, 8.1, 8.2_
  - [ ]* 8.3 Escribir unit test para `ingresos/confirmar.js`
    - Verificar que `submitActualizar` y `confirmarEliminacion` están expuestas en `window`
    - Verificar que importa desde `./shared.js`
    - _Requirements: 7.1_

- [x] 9. Checkpoint — Verificar módulo ingresos completo
  - Ejecutar `npm run build` y confirmar que los 3 entry points de ingresos generan archivos en `public/build/`
  - Verificar manualmente que `ingresos/create`, `ingresos/edit` e `ingresos/confirmar` funcionan correctamente (búsqueda, tabla, totales, método de pago)
  - Asegurarse de que todos los tests pasan, consultar al usuario si surgen dudas

- [x] 10. Crear `cambio-aceite/shared.js` con la lógica compartida del módulo
  - [x] 10.1 Crear `resources/js/cambio-aceite/shared.js` exportando las funciones comunes
    - Exportar: `initBusquedaProductos({ inputId, resultadosId, onAgregar })`, `renderTablaProductos(items, tbodyId, onActualizarCantidad, onEliminar)`, `recalcularTotales(items, precioId, totalId, toggleDescuentoId, porcentajeId)`, `sincronizarHiddens(items, formId, className)`, `initFotoPreview(inputId, previewId, currentId)`, `initMetodoPago({ options, radios, bloqueMixto, inputTotal, inputAncla, toggleDescManual })`, `validarMixto(inputTotal, montoIds, alertaId)`
    - Exportar funciones puras para testing: `calcularTotal(items, porcentaje)`, `renderTablaHTML(items)`
    - _Requirements: 6.1, 6.2, 6.3_
  - [ ]* 10.2 Escribir unit test para `cambio-aceite/shared.js`
    - Verificar que exporta todas las funciones esperadas con `export`
    - Verificar que `calcularTotal` y `renderTablaHTML` son funciones puras exportadas
    - _Requirements: 6.3_

- [x] 11. Crear `cambio-aceite/create.js` e importar desde shared
  - [x] 11.1 Crear `resources/js/cambio-aceite/create.js`
    - Importar las funciones necesarias desde `./shared.js`
    - Inicializar con `items = []`, sin datos previos del servidor
    - Inicializar foto preview sin `currentId`
    - Envolver en `document.addEventListener('DOMContentLoaded', ...)`
    - _Requirements: 2.1, 2.2, 2.3, 3.1, 3.2, 3.3, 6.4_
  - [x] 11.2 Actualizar `resources/views/cambio-aceite/create.blade.php`
    - Eliminar el bloque `<script>` con toda la lógica JS
    - Agregar `@vite('resources/js/cambio-aceite/create.js')` al final de `@section('content')` antes de `@endsection`
    - _Requirements: 5.1, 5.2, 8.1, 8.3_
  - [ ]* 11.3 Escribir unit test para `cambio-aceite/create.js`
    - Verificar que importa desde `./shared.js`
    - Verificar que no contiene definiciones duplicadas de funciones ya en shared
    - _Requirements: 7.1, 7.2_

- [x] 12. Crear `cambio-aceite/edit.js` e importar desde shared
  - [x] 12.1 Crear `resources/js/cambio-aceite/edit.js`
    - Importar las funciones necesarias desde `./shared.js`
    - Leer datos iniciales desde `window.productosExistentes ?? []`
    - Inicializar `items` con los productos existentes (mapeando `pivot.cantidad`, `pivot.precio`, `pivot.total`)
    - Inicializar foto preview con `currentId` (la foto actual del cambio de aceite)
    - _Requirements: 2.1, 2.2, 2.3, 3.1, 3.2, 3.4, 6.4, 7.5_
  - [x] 12.2 Actualizar `resources/views/cambio-aceite/edit.blade.php`
    - Eliminar el bloque `<script>` con toda la lógica JS
    - Agregar bloque `<script>` de inicialización de datos con `window.productosExistentes`, `window.cambioAceiteMetodoPago` y `window.cambioAceiteMontos`
    - Agregar `@vite('resources/js/cambio-aceite/edit.js')` después del bloque de datos
    - _Requirements: 3.4, 3.5, 5.1, 5.2, 5.3, 7.5, 8.1, 8.2_
  - [ ]* 12.3 Escribir unit test para `cambio-aceite/edit.js`
    - Verificar que lee `window.productosExistentes ?? []` con fallback seguro
    - Verificar que importa desde `./shared.js`
    - _Requirements: 7.1, 7.5_

- [x] 13. Checkpoint final — Verificar todos los módulos
  - Ejecutar `npm run build` y confirmar que los 7 entry points generan archivos en `public/build/`
  - Verificar que ninguna vista Blade contiene funciones JS ni event listeners inline (solo el bloque de inicialización de datos donde aplica)
  - Verificar manualmente los flujos de `cambio-aceite/create` y `cambio-aceite/edit` (búsqueda de productos, tabla con cantidades editables, totales, método de pago mixto, foto preview)
  - Asegurarse de que todos los tests pasan, consultar al usuario si surgen dudas

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Los archivos `shared.js` **no** se registran como entry points en Vite; son importados por los módulos de vista
- El patrón de inicialización de datos (`window.*`) es el único `<script>` permitido en las vistas tras la migración
- Las funciones puras exportadas (`calcularTotal`, `renderTablaHTML`, `sincronizarHiddens`) permiten el testing sin DOM
- Cada property test debe incluir el tag: `// Feature: js-modularization, Property N: <descripción>`
