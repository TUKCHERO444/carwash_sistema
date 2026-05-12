# Plan de Implementación: cambio-aceite-confirmacion

## Visión General

Refactorización del módulo de cambio de aceite para adoptar el patrón de dos etapas (registro → confirmación) ya implementado en el módulo de ingresos vehicular. El flujo se divide en: registro simplificado (sin pago) que crea el ticket en estado `pendiente`, y confirmación posterior desde el Panel_Confirmacion donde se completa el pago.

## Tareas

- [x] 1. Migración de base de datos — agregar campo `estado`
  - Crear el archivo de migración `database/migrations/2026_XX_XX_000001_add_estado_to_cambio_aceites_table.php`
  - Agregar columna `estado` de tipo `ENUM('pendiente', 'confirmado')` con `default('pendiente')` después de la columna `fecha`
  - En el método `up()`, después de agregar la columna, ejecutar `DB::table('cambio_aceites')->update(['estado' => 'confirmado'])` para preservar los registros históricos
  - Implementar `down()` con `$table->dropColumn('estado')`
  - _Requisitos: 9.1, 9.2, 9.3, 9.4_

- [x] 2. Actualización del modelo `CambioAceite`
  - [x] 2.1 Agregar `estado` al array `$fillable` en `app/Models/CambioAceite.php`
    - Añadir `'estado'` al array `$fillable` existente
    - _Requisitos: 1.1_

  - [x] 2.2 Agregar scopes `scopePendientes` y `scopeConfirmados` al modelo
    - Implementar `scopePendientes(Builder $query): Builder` → `where('estado', 'pendiente')`
    - Implementar `scopeConfirmados(Builder $query): Builder` → `where('estado', 'confirmado')`
    - Agregar `use Illuminate\Database\Eloquent\Builder;` al modelo
    - _Requisitos: 2.2, 7.2_

  - [ ]* 2.3 Escribir test de propiedad para filtrado exclusivo por estado (Propiedad 5)
    - **Propiedad 5: Filtrado exclusivo por estado**
    - Crear `tests/Feature/CambioAceite/PropiedadesTest.php` con el test de la Propiedad 5
    - Verificar que `pendientes()` y `confirmados()` son conjuntos disjuntos y exhaustivos
    - Usar 100 iteraciones con datos generados por Faker
    - **Valida: Requisitos 2.2, 7.2**

- [x] 3. Refactorización del controlador `CambioAceiteController`
  - [x] 3.1 Modificar `index()` para servir la Tabla_Pendientes
    - Cambiar la consulta para usar el scope `->pendientes()` en lugar de `->latest()`
    - Cambiar la vista retornada de `cambio-aceite.index` a `cambio-aceite.pendientes`
    - _Requisitos: 2.1, 2.2_

  - [x] 3.2 Modificar `store()` — registro simplificado sin pago
    - Eliminar la validación de caja activa (`$this->cajaService->getCajaActiva()`) al inicio del método
    - Eliminar las reglas de validación de `precio`, `total`, `metodo_pago`, `monto_efectivo`, `monto_yape`, `monto_izipay`
    - Calcular `$precio` en el servidor como `collect($request->productos)->sum(fn($p) => $p['cantidad'] * $p['precio'])`
    - En `CambioAceite::create()`, asignar `'estado' => 'pendiente'`, `'precio' => $precio`, `'total' => $precio`, y eliminar los campos de pago
    - Cambiar la redirección final a `cambio-aceite.index` (Tabla_Pendientes)
    - _Requisitos: 1.2, 1.3, 1.4, 1.6, 1.7, 1.8, 1.9_

  - [ ]* 3.3 Escribir test de propiedad para round-trip de creación (Propiedad 3)
    - **Propiedad 3: Round-trip de creación de ticket pendiente**
    - Agregar el test de la Propiedad 3 en `tests/Feature/CambioAceite/PropiedadesTest.php`
    - Verificar que el registro creado tiene `estado = 'pendiente'`, `precio` correcto y productos persistidos
    - **Valida: Requisitos 1.2, 1.7, 1.8**

  - [ ]* 3.4 Escribir test de propiedad para invariante de stock al crear (Propiedad 4)
    - **Propiedad 4: Invariante de stock al crear un ticket pendiente**
    - Agregar el test de la Propiedad 4 en `tests/Feature/CambioAceite/PropiedadesTest.php`
    - Verificar que `stock_final = stock_inicial - cantidad` tras `store()`
    - **Valida: Requisito 1.9**

  - [x] 3.5 Modificar `destroy()` para redirigir al Panel_Confirmacion en caso de error
    - Cambiar el `catch` para redirigir a `cambio-aceite.confirmar` en lugar de `cambio-aceite.index`
    - Mantener la redirección de éxito a `cambio-aceite.index`
    - _Requisitos: 6.3, 6.5_

  - [ ]* 3.6 Escribir test de propiedad para invariante de stock al eliminar (Propiedad 9)
    - **Propiedad 9: Invariante de stock al eliminar un ticket**
    - Agregar el test de la Propiedad 9 en `tests/Feature/CambioAceite/PropiedadesTest.php`
    - Verificar que `stock_final = stock_inicial` tras `destroy()`
    - **Valida: Requisito 6.2**

  - [x] 3.7 Implementar método `confirmados()` — Tabla_Confirmados
    - Consultar `CambioAceite::with(['cliente', 'trabajador'])->confirmados()->latest()->paginate(15)`
    - Retornar la vista `cambio-aceite.confirmados`
    - _Requisitos: 7.1, 7.2, 7.5_

  - [x] 3.8 Implementar método `confirmar()` — Panel_Confirmacion
    - Si `$cambioAceite->estado === 'confirmado'`, redirigir a `cambio-aceite.confirmados` con mensaje `info`
    - Cargar relaciones `['cliente', 'trabajador', 'productos']`
    - Preparar `$productosData` mapeando el pivot con `id`, `nombre`, `precio`, `cantidad`, `total`
    - Preparar `$montosData` con `efectivo`, `yape`, `izipay`
    - Obtener `$trabajadores` activos
    - Retornar la vista `cambio-aceite.confirmar`
    - _Requisitos: 3.1, 3.2, 3.3, 3.7_

  - [x] 3.9 Implementar método `procesarConfirmacion()` — Confirmar pago
    - Validar caja activa; si no existe, retornar `back()->with('error_caja', true)`
    - Validar todos los campos incluyendo `precio`, `total`, `metodo_pago` y productos
    - Dentro de `DB::transaction()`: restaurar stock anterior, actualizar el `CambioAceite` con `estado = 'confirmado'` y `caja_id`, sincronizar `cambio_productos` con `sync()`, decrementar stock nuevo
    - Redirigir a `cambio-aceite.index` con mensaje de éxito
    - _Requisitos: 4.1, 4.2, 4.3, 4.4, 4.6, 4.7, 4.8_

  - [ ]* 3.10 Escribir test de propiedad para sin caja activa no confirma (Propiedad 7)
    - **Propiedad 7: Sin caja activa, `procesarConfirmacion` no cambia el estado**
    - Agregar el test de la Propiedad 7 en `tests/Feature/CambioAceite/PropiedadesTest.php`
    - Verificar que el estado permanece `pendiente` cuando no hay caja activa
    - **Valida: Requisito 4.7**

  - [x] 3.11 Implementar método `actualizarTicket()` — Actualizar sin confirmar
    - Validar campos sin incluir `precio`, `total`, `metodo_pago` ni caja
    - Dentro de `DB::transaction()`: restaurar stock anterior, recalcular `$precio` en servidor, actualizar el `CambioAceite` sin cambiar `estado`, sincronizar `cambio_productos`, decrementar stock nuevo
    - Redirigir a `cambio-aceite.confirmar` del mismo ticket con mensaje de éxito
    - _Requisitos: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8_

  - [ ]* 3.12 Escribir test de propiedad para invariante de stock al actualizar (Propiedad 8)
    - **Propiedad 8: Invariante de stock al actualizar un ticket**
    - Agregar el test de la Propiedad 8 en `tests/Feature/CambioAceite/PropiedadesTest.php`
    - Verificar que `stock_final = stock_inicial - cantidad_nueva` tras `actualizarTicket()`
    - **Valida: Requisito 5.8**

- [x] 4. Checkpoint — Verificar controlador y modelo
  - Ejecutar `php artisan migrate` para aplicar la migración del campo `estado`
  - Ejecutar `php artisan test --filter=CambioAceite` para verificar que los tests existentes siguen pasando
  - Asegurarse de que no hay errores de compilación en el controlador y el modelo

- [x] 5. Actualización de rutas en `routes/web.php`
  - Registrar las rutas nuevas **antes** del `Route::resource('cambio-aceite', ...)` y antes de la ruta de ticket, para evitar conflictos con Route Model Binding
  - Agregar `GET /cambio-aceite/confirmados` → `confirmados` → `cambio-aceite.confirmados`
  - Agregar `GET /cambio-aceite/{cambioAceite}/confirmar` → `confirmar` → `cambio-aceite.confirmar`
  - Agregar `POST /cambio-aceite/{cambioAceite}/confirmar` → `procesarConfirmacion` → `cambio-aceite.procesarConfirmacion`
  - Agregar `PUT /cambio-aceite/{cambioAceite}/actualizar-ticket` → `actualizarTicket` → `cambio-aceite.actualizarTicket`
  - Verificar que la ruta Ajax `buscar-productos` sigue antes del resource
  - _Requisitos: 2.1, 3.1, 4.1, 5.1, 7.1_

- [x] 6. Modificación de `resources/js/cambio-aceite/create.js` — simplificar
  - Eliminar los imports de `initMetodoPago` y `validarMixto` de `./shared.js`
  - Eliminar el bloque de inicialización de método de pago (`initMetodoPago`, listeners de `monto_efectivo`, `monto_yape`, `monto_izipay`, `mixto:revalidar`)
  - Eliminar el listener del toggle de descuento manual (`toggle-descuento-manual`)
  - Mantener: `initBusquedaProductos`, `renderTablaProductos`, `recalcularTotales`, `sincronizarHiddens`, `initFotoPreview`, toggle de descuento por porcentaje
  - El campo `precio` visible se mantiene como referencia informativa (readonly) para que el operario vea el subtotal
  - _Requisitos: 1.6_

- [x] 7. Modificación de `resources/views/cambio-aceite/create.blade.php` — simplificar
  - Eliminar la sección completa de "Método de pago" (radio buttons de efectivo/yape/izipay/mixto y bloque mixto)
  - Eliminar los campos `total`, `precio` (o convertir `precio` a campo informativo readonly sin `name`)
  - Eliminar los campos hidden de `metodo_pago`, `monto_efectivo`, `monto_yape`, `monto_izipay`
  - Cambiar el texto del botón de submit a "Guardar ticket pendiente"
  - Cambiar el `@vite` al final para apuntar a `resources/js/cambio-aceite/create.js` (ya apunta, verificar)
  - _Requisitos: 1.3, 1.4, 1.6_

- [x] 8. Creación de `resources/views/cambio-aceite/pendientes.blade.php` — Tabla_Pendientes
  - Crear la vista extendiendo el layout principal del proyecto
  - Mostrar tabla con columnas: Fecha, Placa / Nombre del cliente, Trabajador asignado
  - Agregar botón "Abrir ticket" por fila que enlaza a `route('cambio-aceite.confirmar', $cambioAceite)`
  - Agregar en el header: botón "Nuevo cambio de aceite" → `cambio-aceite.create` y botón "Listado de cambios culminados" → `cambio-aceite.confirmados`
  - Mostrar mensaje "No hay cambios de aceite pendientes." cuando la colección esté vacía
  - Incluir paginación con `$cambioAceites->links()`
  - _Requisitos: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_

- [x] 9. Creación de `resources/views/cambio-aceite/confirmados.blade.php` — Tabla_Confirmados
  - Crear la vista basándose en el `index.blade.php` actual del módulo
  - Mantener todas las columnas y acciones existentes: Foto, Fecha, Cliente, Trabajador, Precio, Total, Pago, Acciones (ver detalle, ticket, editar, eliminar)
  - Agregar botón "Volver a pendientes" en el header que enlaza a `cambio-aceite.index`
  - Incluir paginación de 15 registros con `$cambioAceites->links()`
  - _Requisitos: 7.1, 7.2, 7.3, 7.4, 7.5_

- [x] 10. Creación de `resources/views/cambio-aceite/confirmar.blade.php` — Panel_Confirmacion
  - Crear la vista extendiendo el layout principal, tomando como referencia `resources/views/ingresos/confirmar.blade.php`
  - Agregar resumen superior con placa del vehículo y lista de nombres de productos del ticket
  - Crear formulario `#form-cambio-aceite` con `action="{{ route('cambio-aceite.procesarConfirmacion', $cambioAceite) }}"` y `method="POST"`
  - Incluir todos los campos: `placa`, `nombre`, `dni`, `fecha`, `descripcion`, `foto` (con preview de foto actual `#foto-current`), `trabajador_id`
  - Incluir sección de búsqueda de productos con `#buscar-producto` y `#resultados-busqueda`
  - Incluir tabla de productos `#tbody-detalle` con columnas: nombre, cantidad (editable), precio unitario, subtotal
  - Incluir sección de totales: `#precio` (readonly), toggle descuento %, `#total`, toggle descuento manual
  - Incluir sección de método de pago: radio buttons `.metodo-pago-option` / `.metodo-pago-radio`, bloque mixto `#bloque-mixto`
  - Incluir modal de advertencia de caja cerrada (activado por `@if(session('error_caja'))`)
  - Agregar botones: "Confirmar cambio de aceite" (submit del form), "Actualizar ticket" (`onclick="submitActualizar()"`), "Eliminar ticket" (`onclick="confirmarEliminacion()"`)
  - Crear formulario oculto `#form-eliminar` con `action="{{ route('cambio-aceite.destroy', $cambioAceite) }}"` y `@method('DELETE')`
  - Exponer variables globales en `<script>`: `window.productosConfirmar`, `window.confirmarMetodoPago`, `window.confirmarMontos`, `window._confirmarUpdateUrl`
  - Agregar `@vite('resources/js/cambio-aceite/confirmar.js')` al final
  - _Requisitos: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 4.5, 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7_

- [x] 11. Checkpoint — Verificar vistas y rutas
  - Navegar manualmente (o con tests de feature) a `/cambio-aceite` y verificar que muestra la Tabla_Pendientes
  - Verificar que el formulario de creación ya no muestra campos de pago
  - Verificar que `/cambio-aceite/confirmados` carga sin errores
  - Ejecutar `php artisan route:list | grep cambio-aceite` para confirmar que todas las rutas están registradas correctamente

- [x] 12. Creación de `resources/js/cambio-aceite/confirmar.js` — módulo JS del Panel_Confirmacion
  - Crear el archivo `resources/js/cambio-aceite/confirmar.js` tomando como referencia `resources/js/ingresos/confirmar.js`
  - Importar desde `./shared.js`: `initBusquedaProductos`, `renderTablaProductos`, `recalcularTotales`, `sincronizarHiddens`, `initFotoPreview`, `initMetodoPago`, `validarMixto`
  - Leer datos iniciales desde variables globales: `window.productosConfirmar`, `window.confirmarMetodoPago`, `window.confirmarMontos`
  - Inicializar `items` mapeando `productosConfirmar` con `producto_id`, `nombre`, `precio`, `cantidad`, `total`
  - Implementar `window.submitActualizar`: cambia `form.action` a `window._confirmarUpdateUrl`, agrega `_method=PUT` y hace submit
  - Implementar `window.confirmarEliminacion`: pide confirmación y hace submit de `#form-eliminar`
  - Implementar `actualizarCantidad(idx, val)`: actualiza `items[idx]`, re-renderiza tabla, recalcula totales, sincroniza hiddens
  - Implementar `eliminarItem(idx)`: elimina de `items`, re-renderiza, recalcula, sincroniza
  - Implementar `onAgregarProducto(producto)`: sin duplicados, agrega a `items` con cantidad 1, re-renderiza, recalcula, sincroniza
  - En `DOMContentLoaded`: renderizar tabla inicial, sincronizar hiddens, inicializar búsqueda, preview de foto (con `'foto-current'`), método de pago, restaurar método y montos guardados, listeners de descuento/porcentaje/mixto
  - NO recalcular totales en la carga inicial — preservar `precio`/`total` del servidor
  - Registrar el entry point en `vite.config.js` o `resources/js/app.js` si es necesario
  - _Requisitos: 3.3, 4.5, 5.5, 5.6, 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7_

- [x] 13. Checkpoint — Verificar flujo completo end-to-end con tests
  - Ejecutar `php artisan test --filter=CambioAceite` para verificar todos los tests de feature
  - Verificar que el flujo completo funciona: crear ticket → ver en pendientes → abrir panel → actualizar → confirmar → ver en confirmados
  - Verificar que eliminar un ticket desde el panel restaura el stock y redirige a pendientes

- [x] 14. Tests de propiedades JS (fast-check) para funciones puras de `shared.js`
  - [x] 14.1 Crear `tests/js/cambio-aceite/shared.property.test.js` con configuración de Vitest + fast-check
    - Instalar `fast-check` si no está disponible: `npm install --save-dev fast-check`
    - Configurar el archivo de test importando `calcularTotal` desde `resources/js/cambio-aceite/shared.js`
    - _Requisitos: 1.7_

  - [ ]* 14.2 Escribir test de Propiedad 1: `calcularPrecio` es la suma de líneas
    - **Propiedad 1: Cálculo de precio como suma de líneas**
    - Generar arrays de productos con `cantidad` e `precio` aleatorios usando `fc.array` + `fc.record`
    - Verificar que `sum(items.map(i => i.total))` coincide con la suma esperada (tolerancia 0.001)
    - Mínimo 100 iteraciones (`numRuns: 100`)
    - **Valida: Requisito 1.7**

  - [ ]* 14.3 Escribir test de Propiedad 2: `calcularTotal` nunca supera el precio base
    - **Propiedad 2: El total con descuento nunca supera el precio base**
    - Generar arrays de items con `total` aleatorio y porcentaje `pct` en `[0, 100]`
    - Verificar que `calcularTotal(items, pct) <= precio_base + 0.001`
    - Mínimo 100 iteraciones
    - **Valida: Requisitos 1.7, 4.1**

- [x] 15. Tests de ejemplo (Pest PHP — Feature Tests)
  - [ ]* 15.1 Crear `tests/Feature/CambioAceite/StoreTest.php`
    - Test: `store()` sin caja activa crea el ticket correctamente (estado `pendiente`)
    - Test: `store()` con campos requeridos faltantes retorna errores de validación
    - Test: el formulario de registro no acepta campos de pago (`metodo_pago`, `total`)
    - Test: `store()` redirige a `cambio-aceite.index` tras éxito
    - _Requisitos: 1.2, 1.3, 1.5, 1.6_

  - [ ]* 15.2 Crear `tests/Feature/CambioAceite/ConfirmarTest.php`
    - Test: `confirmar()` de un ticket ya confirmado redirige a `cambio-aceite.confirmados`
    - Test: `procesarConfirmacion()` sin caja activa retorna `error_caja` y no cambia el estado
    - Test: `procesarConfirmacion()` con `total <= 0` retorna error de validación
    - Test: `procesarConfirmacion()` exitoso cambia estado a `confirmado` y redirige a `cambio-aceite.index`
    - _Requisitos: 3.7, 4.1, 4.2, 4.3, 4.4, 4.7_

  - [ ]* 15.3 Crear `tests/Feature/CambioAceite/ActualizarTicketTest.php`
    - Test: `actualizarTicket()` sin `trabajador_id` retorna error de validación
    - Test: `actualizarTicket()` sin productos retorna error de validación
    - Test: `actualizarTicket()` exitoso mantiene estado `pendiente` y redirige al panel
    - _Requisitos: 5.2, 5.3, 5.4_

  - [ ]* 15.4 Crear `tests/Feature/CambioAceite/DestroyTest.php`
    - Test: `destroy()` con foto elimina el archivo del storage
    - Test: `destroy()` exitoso redirige a `cambio-aceite.index` con mensaje de éxito
    - _Requisitos: 6.3, 6.4_

  - [ ]* 15.5 Crear `tests/Feature/CambioAceite/TablasPendientesConfirmadosTest.php`
    - Test: `GET /cambio-aceite` muestra solo tickets pendientes
    - Test: `GET /cambio-aceite/confirmados` muestra solo tickets confirmados
    - Test: la Tabla_Pendientes muestra mensaje vacío cuando no hay pendientes
    - _Requisitos: 2.2, 2.7, 7.2_

- [x] 16. Checkpoint final — Todos los tests pasan
  - Ejecutar `php artisan test` para verificar que toda la suite de tests pasa
  - Ejecutar `npx vitest --run tests/js/cambio-aceite/` para verificar los tests de propiedades JS
  - Resolver cualquier error o regresión antes de dar la feature por completada

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- El orden de las tareas respeta las dependencias: migración → modelo → controlador → rutas → JS → vistas → tests
- Las rutas nuevas en `routes/web.php` deben registrarse **antes** del `Route::resource` para evitar que Laravel interprete `confirmados` o `confirmar` como parámetros `{cambioAceite}`
- El módulo de ingresos (`IngresoController`, `resources/js/ingresos/confirmar.js`, `resources/views/ingresos/confirmar.blade.php`) sirve como referencia directa para la implementación
- Los tests de propiedades PHP usan `pestphp/pest` con Faker; los tests JS usan `fast-check` con Vitest
- Cada propiedad PBT tiene su número de referencia del documento de diseño para trazabilidad
