# Plan de Implementación: Modal de Actualización de Stock

## Visión General

Implementar el modal de actualización de stock en la tabla de productos de Laravel. El flujo es: botón en cada fila → modal HTML en Blade → PATCH `/productos/{producto}/stock` → `ProductoController@updateStock` → respuesta JSON → JS actualiza la celda en la tabla sin recargar la página.

Stack: PHP/Laravel, Blade, Tailwind CSS, JavaScript vanilla. Sin dependencias nuevas.

## Tareas

- [x] 1. Registrar la ruta PATCH dedicada para actualización de stock
  - Agregar `Route::patch('productos/{producto}/stock', [ProductoController::class, 'updateStock'])->name('productos.updateStock')` dentro del grupo `middleware(['auth', 'role:Administrador'])` en `routes/web.php`
  - La ruta debe quedar antes del resource `productos` para evitar conflictos de Route Model Binding
  - _Requisitos: 5.1, 5.2_

- [x] 2. Implementar `ProductoController@updateStock`
  - [x] 2.1 Escribir el método `updateStock(Request $request, Producto $producto): JsonResponse`
    - Validar `cantidad_adicional`: requerido, entero, mínimo 1, máximo 9999
    - Calcular `nuevo_stock = $producto->stock + $validated['cantidad_adicional']`
    - Actualizar `stock` e `inventario` dentro de `DB::transaction`
    - Devolver `response()->json(['success' => true, 'nuevo_stock' => $nuevoStock])` en éxito
    - Capturar excepciones de base de datos y devolver HTTP 500 con JSON de error
    - _Requisitos: 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4, 5.3, 5.4_

  - [ ]* 2.2 Escribir test de propiedad P5: el cálculo del nuevo stock es la suma exacta
    - **Propiedad 5: Para S ∈ [0, 10000] y C ∈ [1, 9999] aleatorios, el stock resultante debe ser exactamente S + C**
    - **Valida: Requisitos 3.1**
    - Ejecutar mínimo 100 iteraciones con valores generados aleatoriamente via `rand()`
    - Archivo: `tests/Feature/StockUpdateModalPropertyTest.php`

  - [ ]* 2.3 Escribir test de propiedad P6: stock e inventario son iguales tras la actualización
    - **Propiedad 6: Para S y C válidos aleatorios, `stock == inventario` después de la operación**
    - **Valida: Requisitos 3.2**
    - Ejecutar mínimo 100 iteraciones
    - Archivo: `tests/Feature/StockUpdateModalPropertyTest.php`

  - [ ]* 2.4 Escribir test de propiedad P3: valores no numéricos son rechazados con HTTP 422
    - **Propiedad 3: Para strings no numéricos aleatorios enviados como `cantidad_adicional`, el servidor devuelve HTTP 422**
    - **Valida: Requisitos 2.2**
    - Ejecutar mínimo 100 iteraciones con strings generados aleatoriamente
    - Archivo: `tests/Feature/StockUpdateModalPropertyTest.php`

  - [ ]* 2.5 Escribir test de propiedad P4: valores fuera del rango [1, 9999] son rechazados con HTTP 422
    - **Propiedad 4: Para enteros ≤ 0 y > 9999 aleatorios, el servidor devuelve HTTP 422**
    - **Valida: Requisitos 2.3, 2.4**
    - Ejecutar mínimo 100 iteraciones
    - Archivo: `tests/Feature/StockUpdateModalPropertyTest.php`

  - [ ]* 2.6 Escribir test de propiedad P7: rollback ante error preserva los valores originales
    - **Propiedad 7: Si la transacción falla, `stock` e `inventario` permanecen en sus valores originales S e I**
    - **Valida: Requisitos 3.4**
    - Simular fallo en la transacción (mock de `DB::transaction` o excepción forzada)
    - Ejecutar mínimo 50 iteraciones
    - Archivo: `tests/Feature/StockUpdateModalPropertyTest.php`

  - [ ]* 2.7 Escribir test de propiedad P9: usuarios sin rol Administrador reciben HTTP 403
    - **Propiedad 9: Para cualquier usuario autenticado sin rol `Administrador`, PATCH a `/productos/{producto}/stock` devuelve HTTP 403**
    - **Valida: Requisitos 5.2**
    - Ejecutar mínimo 50 iteraciones con usuarios de distintos roles
    - Archivo: `tests/Feature/StockUpdateModalPropertyTest.php`

  - [ ]* 2.8 Escribir tests de ejemplo (PHPUnit Feature Tests) para `updateStock`
    - Actualización exitosa: verificar JSON `{success: true, nuevo_stock: N}` y valores en BD
    - Campo vacío (Req. 2.1): verificar HTTP 422 con mensaje de campo obligatorio
    - Valor ≤ 0 (Req. 2.3): verificar HTTP 422
    - Valor > 9999 (Req. 2.4): verificar HTTP 422
    - Producto inexistente (Req. 5.3, 5.4): verificar HTTP 404
    - Usuario no autenticado (Req. 5.1): verificar redirect a login
    - Archivo: `tests/Feature/StockUpdateModalTest.php`

- [x] 3. Checkpoint — Verificar que todos los tests del controlador pasan
  - Ejecutar `php artisan test --filter=StockUpdateModal` y confirmar que todos los tests pasan. Consultar al usuario si surge alguna duda.

- [x] 4. Agregar el botón de actualización de stock en la vista `productos/index.blade.php`
  - Agregar atributos `data-stock-btn`, `data-producto-id`, `data-producto-nombre`, `data-producto-stock` y `data-update-url` al botón en cada fila de la tabla
  - El botón debe tener `aria-label="Actualizar stock de {{ $producto->nombre }}"` para accesibilidad
  - Usar estilos Tailwind consistentes con los botones existentes (Editar/Eliminar) en la columna Acciones
  - _Requisitos: 1.1, 1.2_

- [x] 5. Agregar el modal HTML en `productos/index.blade.php`
  - Insertar el `<div id="stock-modal">` único y reutilizable al final del `@section('content')`, antes del cierre `</div>`
  - El modal debe incluir: título, nombre del producto (`#stock-modal-nombre`), stock actual (`#stock-modal-stock-actual`), campo `cantidad_adicional` con `min="1" max="9999" step="1"`, párrafo de error (`#stock-modal-error`), botones Cancelar y Confirmar
  - Aplicar `role="dialog"`, `aria-modal="true"` y `aria-labelledby="stock-modal-title"` para accesibilidad
  - El modal debe iniciar con clase `hidden`
  - _Requisitos: 1.2, 1.3, 1.4, 4.4_

- [x] 6. Crear el módulo JavaScript `resources/js/stock-modal.js`
  - [x] 6.1 Implementar la función `initStockModal()` con apertura del modal
    - Escuchar clics en `[data-stock-btn]` y poblar `#stock-modal-nombre`, `#stock-modal-stock-actual`, `#stock-modal-producto-id` y `#stock-modal-url` con los `data-*` attributes del botón
    - Limpiar el campo `#stock-modal-cantidad` y ocultar `#stock-modal-error` al abrir
    - Remover la clase `hidden` del modal y hacer foco en el campo de cantidad
    - _Requisitos: 1.2, 1.3, 1.4_

  - [x] 6.2 Implementar el cierre del modal
    - Cerrar al hacer clic en el botón `#stock-modal-cancel`
    - Cerrar al hacer clic en el backdrop (el `div` exterior del modal, fuera del panel blanco)
    - Cerrar al presionar la tecla `Escape`
    - El cierre no debe realizar ninguna petición al servidor
    - _Requisitos: 4.4_

  - [x] 6.3 Implementar el envío del formulario con `fetch` PATCH
    - Al hacer clic en `#stock-modal-submit`, leer `cantidad_adicional` del campo y la URL de `#stock-modal-url`
    - Enviar `fetch` con método PATCH, `Content-Type: application/json`, header `X-CSRF-TOKEN` leído del meta tag, y body `{cantidad_adicional: N}`
    - Deshabilitar el botón Confirmar durante el envío para evitar doble submit
    - _Requisitos: 3.1, 3.2, 3.3_

  - [x] 6.4 Manejar la respuesta exitosa del servidor
    - Al recibir `{success: true, nuevo_stock: N}`: cerrar el modal, actualizar la celda de stock en la fila correspondiente de la tabla usando `data-producto-id` para localizar la fila, y mostrar un mensaje flash de confirmación en la página
    - El mensaje flash debe ser consistente con los flash messages existentes en la vista (estilo verde de Tailwind)
    - _Requisitos: 4.1, 4.2, 4.3_

  - [x] 6.5 Manejar errores de validación (HTTP 422) y errores de servidor (4xx/5xx)
    - Para HTTP 422: mostrar el primer mensaje de error de `errors.cantidad_adicional` en `#stock-modal-error` (remover clase `hidden`)
    - Para otros errores (4xx/5xx): mostrar mensaje genérico en `#stock-modal-error`
    - Para error de red (fetch rechazado): mostrar "Error de conexión. Intente nuevamente." en `#stock-modal-error`
    - Re-habilitar el botón Confirmar tras cualquier error
    - _Requisitos: 2.1, 2.2, 2.3, 2.4, 4.2_

- [x] 7. Importar `stock-modal.js` en `resources/js/app.js`
  - Agregar `import './stock-modal';` en `app.js`
  - Verificar que la función `initStockModal()` se llama dentro del listener `DOMContentLoaded` existente o que el módulo lo registra internamente
  - _Requisitos: 1.1_

- [x] 8. Checkpoint final — Verificar integración completa
  - Ejecutar `php artisan test` para confirmar que todos los tests pasan sin regresiones
  - Verificar manualmente que el botón aparece en la tabla, el modal se abre con los datos correctos, la actualización se refleja en la celda sin recargar la página, y los mensajes de error se muestran correctamente en el modal
  - Consultar al usuario si surge alguna duda.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia requisitos específicos para trazabilidad
- Los tests de propiedad usan PHPUnit con generadores manuales (`rand()`); no se introduce ninguna librería PBT externa
- El modal es único y reutilizable para todos los productos (datos cargados via `data-*` attributes)
- No se requieren migraciones nuevas: los campos `stock` e `inventario` ya existen en la tabla `productos`
