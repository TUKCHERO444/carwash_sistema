# Plan de Implementación: ingresos-confirmacion

## Visión General

Dividir el flujo de registro de ingresos en dos etapas: **registro** (estado `pendiente`) y **confirmación** (estado `confirmado`). Se agrega el campo `estado` a la tabla `ingresos`, se reorganizan las vistas y rutas del módulo, y se introduce un Panel de Confirmación dedicado para completar el pago.

## Tareas

- [x] 1. Migración y modelo: agregar campo `estado` a la tabla `ingresos`
  - Crear el archivo de migración `add_estado_to_ingresos_table.php` con `$table->enum('estado', ['pendiente', 'confirmado'])->default('pendiente')->after('fecha')`
  - En el `up()`, después de agregar la columna, ejecutar `DB::table('ingresos')->update(['estado' => 'confirmado'])` para preservar datos históricos
  - Implementar el `down()` con `$table->dropColumn('estado')`
  - Agregar `'estado'` al array `$fillable` del modelo `Ingreso`
  - Agregar los scopes `scopePendientes` y `scopeConfirmados` al modelo `Ingreso`
  - _Requisitos: 1.1, 8.1, 8.2, 8.3, 8.4_

  - [ ]* 1.1 Escribir tests de migración (smoke tests)
    - Verificar que la migración crea el campo `estado` con los valores `pendiente` y `confirmado`
    - Verificar que los ingresos existentes tienen `estado = 'confirmado'` tras ejecutar la migración
    - Verificar que el valor por defecto para nuevos registros es `pendiente`
    - Verificar que el `down()` elimina el campo sin errores
    - _Requisitos: 8.1, 8.2, 8.3, 8.4_

- [x] 2. Actualizar `IngresoController::store()` y el formulario de creación
  - Modificar `IngresoController::store()` para eliminar la validación de campos de pago (`precio`, `total`, `metodo_pago`, `monto_*`) y calcular `precio` en el servidor (suma del precio del vehículo más los servicios seleccionados)
  - Guardar el ingreso con `estado = 'pendiente'` y redirigir a `ingresos.index` (Tabla_Pendientes) en lugar de `ingresos.show`
  - Modificar `resources/views/ingresos/create.blade.php` para eliminar los campos de pago (`metodo_pago`, `total`, `precio`, `monto_efectivo`, `monto_yape`, `monto_izipay`) del formulario HTML y del bloque JavaScript
  - Mantener el cálculo de precio en el frontend solo como indicador visual (sin enviarlo al servidor)
  - _Requisitos: 1.2, 1.3, 1.4, 1.5, 1.6, 5.6_

  - [ ]* 2.1 Escribir test de propiedad: el registro siempre crea ingresos en estado pendiente
    - **Propiedad 1: El registro siempre crea ingresos en estado pendiente**
    - Para cualquier conjunto válido de datos de ingreso, `store()` debe crear el ingreso con `estado = 'pendiente'`
    - Ejecutar mínimo 100 iteraciones con datos generados por Faker
    - **Valida: Requisito 1.2**

  - [ ]* 2.2 Escribir test de propiedad: la validación rechaza campos requeridos ausentes
    - **Propiedad 2: La validación rechaza ingresos con campos requeridos ausentes**
    - Para cualquier solicitud con al menos un campo requerido ausente (`vehiculo_id`, `placa`, `fecha`, `trabajador_id`), la validación debe fallar y no crear ningún ingreso
    - Ejecutar mínimo 100 iteraciones
    - **Valida: Requisitos 1.3, 1.5**

  - [ ]* 2.3 Escribir test de propiedad: el precio calculado es la suma del vehículo más los servicios
    - **Propiedad 9: El precio calculado es la suma del vehículo más los servicios**
    - Para cualquier combinación de vehículo y lista de servicios, el campo `precio` almacenado debe ser igual a `precio_vehiculo + suma(precios_servicios)`
    - Ejecutar mínimo 100 iteraciones
    - **Valida: Requisito 5.6**

- [x] 3. Checkpoint — Verificar migración y creación de ingresos pendientes
  - Asegurarse de que todos los tests pasan. Consultar al usuario si surgen dudas.

- [x] 4. Crear rutas nuevas y reordenar el archivo de rutas
  - En `routes/web.php`, registrar la ruta `GET /ingresos/confirmados` con nombre `ingresos.confirmados` **antes** del `Route::resource('ingresos', ...)` para evitar conflictos de parámetros
  - Registrar `GET /ingresos/{ingreso}/confirmar` con nombre `ingresos.confirmar`
  - Registrar `POST /ingresos/{ingreso}/confirmar` con nombre `ingresos.procesarConfirmacion`
  - _Requisitos: 2.1, 3.1, 4.1, 7.1_

- [x] 5. Implementar `IngresoController::index()` — Tabla de Pendientes
  - Modificar `index()` para filtrar con `scopePendientes()`, ordenar por fecha descendente y paginar a 15 registros
  - Cambiar la vista retornada a `ingresos.pendientes`
  - _Requisitos: 2.1, 2.2_

  - [ ]* 5.1 Escribir test de propiedad: la tabla de pendientes solo muestra ingresos pendientes
    - **Propiedad 3: La tabla de pendientes solo muestra ingresos pendientes, ordenados por fecha descendente**
    - Para cualquier mezcla aleatoria de ingresos pendientes y confirmados, `GET /ingresos` debe retornar únicamente los pendientes, ordenados del más reciente al más antiguo
    - Ejecutar mínimo 100 iteraciones
    - **Valida: Requisito 2.2**

- [x] 6. Crear la vista `resources/views/ingresos/pendientes.blade.php` — Tabla de Pendientes
  - Crear la vista con columnas: fecha, placa del cliente, nombre del cliente (si existe), tipo de vehículo y trabajadores asignados
  - Agregar botón "Nuevo ingreso" que enlaza a `ingresos.create`
  - Agregar botón "Listado de ingresos culminados" en la parte superior derecha que enlaza a `ingresos.confirmados`
  - Agregar botón "Abrir ticket de ingreso" en cada fila que enlaza a `ingresos.confirmar`
  - Mostrar mensaje de estado vacío si no hay ingresos pendientes
  - _Requisitos: 2.1, 2.3, 2.4, 2.5, 2.6, 2.7_

- [x] 7. Implementar `IngresoController::confirmados()` — Tabla de Confirmados
  - Crear el método `confirmados()` que filtra con `scopeConfirmados()`, ordena por fecha descendente y pagina a 15 registros
  - Retornar la vista `ingresos.confirmados`
  - _Requisitos: 7.1, 7.2, 7.5_

  - [ ]* 7.1 Escribir test de propiedad: la tabla de confirmados solo muestra ingresos confirmados
    - **Propiedad 8: La tabla de confirmados solo muestra ingresos confirmados, ordenados por fecha descendente**
    - Para cualquier mezcla aleatoria de ingresos, `GET /ingresos/confirmados` debe retornar únicamente los confirmados, ordenados del más reciente al más antiguo
    - Ejecutar mínimo 100 iteraciones
    - **Valida: Requisito 7.2**

- [x] 8. Crear la vista `resources/views/ingresos/confirmados.blade.php` — Tabla de Confirmados
  - Crear la vista con todas las columnas y acciones de la `ingresos/index.blade.php` actual (fecha, cliente, vehículo, trabajadores, precio, total, pago, acciones: ver detalle, ticket, editar, eliminar)
  - Agregar botón "Volver a pendientes" que enlaza a `ingresos.index`
  - Aplicar paginación de 15 registros
  - _Requisitos: 7.3, 7.4, 7.5_

- [x] 9. Checkpoint — Verificar tablas de pendientes y confirmados
  - Asegurarse de que todos los tests pasan. Consultar al usuario si surgen dudas.

- [x] 10. Implementar `IngresoController::confirmar()` — mostrar Panel de Confirmación
  - Crear el método `confirmar(Ingreso $ingreso)` que carga las relaciones necesarias (`cliente`, `vehiculo`, `trabajadores`, `servicios`)
  - Si el ingreso ya tiene `estado = 'confirmado'`, redirigir a `ingresos.confirmados` con un mensaje informativo
  - Retornar la vista `ingresos.confirmar` con los datos del ingreso, vehículos y trabajadores disponibles
  - _Requisitos: 3.1, 3.2, 3.3_

- [x] 11. Crear la vista `resources/views/ingresos/confirmar.blade.php` — Panel de Confirmación
  - Mostrar en la parte superior la placa del vehículo y la lista de servicios asignados al ingreso
  - Incluir el formulario completo de edición (vehículo, placa, nombre, dni, fecha, foto, trabajador, búsqueda de servicios, tabla de servicios) reutilizando la lógica JS de `edit.blade.php`
  - Incluir los campos de pago: precio (readonly), descuento por porcentaje, descuento manual, total, método de pago y campos de pago mixto
  - Agregar botón "Confirmar ingreso" que hace `POST /ingresos/{id}/confirmar`
  - Agregar botón "Actualizar ingreso" que hace `PUT /ingresos/{id}` (reutiliza la ruta `update` existente)
  - Agregar botón "Eliminar ingreso" con confirmación JS que hace `DELETE /ingresos/{id}`
  - _Requisitos: 3.2, 3.3, 3.4, 3.5, 3.6_

- [x] 12. Implementar `IngresoController::procesarConfirmacion()` — confirmar pago
  - Crear el método `procesarConfirmacion(Request $request, Ingreso $ingreso)` con la validación completa de pago definida en el diseño
  - Envolver la lógica en `DB::transaction()`: actualizar cliente, foto, servicios, trabajador, datos de pago y `estado = 'confirmado'`
  - Redirigir a `ingresos.index` (Tabla_Pendientes) con mensaje de éxito al confirmar
  - En caso de error de validación o excepción, redirigir de vuelta con `withInput()` y mensaje de error
  - _Requisitos: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

  - [ ]* 12.1 Escribir test de propiedad: la confirmación transiciona el estado y almacena datos de pago
    - **Propiedad 4: La confirmación transiciona el estado y almacena los datos de pago**
    - Para cualquier ingreso pendiente y cualquier conjunto válido de datos de pago (`total > 0`, `metodo_pago` válido), `procesarConfirmacion` debe dejar el ingreso con `estado = 'confirmado'` y los datos de pago almacenados
    - Ejecutar mínimo 100 iteraciones
    - **Valida: Requisitos 4.1, 4.2**

  - [ ]* 12.2 Escribir test de propiedad: la validación rechaza totales inválidos
    - **Propiedad 5: La validación de confirmación rechaza totales inválidos**
    - Para cualquier ingreso pendiente, si `total <= 0`, la confirmación debe ser rechazada y el estado debe permanecer `pendiente`
    - Ejecutar mínimo 100 iteraciones
    - **Valida: Requisito 4.4**

- [x] 13. Actualizar `IngresoController::update()` para preservar el estado pendiente
  - Modificar `update()` para que no modifique el campo `estado` al actualizar un ingreso
  - Redirigir a `ingresos.confirmar` (Panel de Confirmación) en lugar de `ingresos.show` cuando el ingreso tiene `estado = 'pendiente'`
  - _Requisitos: 5.1, 5.2, 5.3, 5.4, 5.5_

  - [ ]* 13.1 Escribir test de propiedad: la actualización preserva el estado pendiente
    - **Propiedad 6: La actualización preserva el estado pendiente**
    - Para cualquier ingreso pendiente y cualquier conjunto válido de datos de actualización, `update()` debe dejar el estado como `pendiente`
    - Ejecutar mínimo 100 iteraciones
    - **Valida: Requisito 5.1**

- [x] 14. Actualizar `IngresoController::destroy()` para redirigir correctamente
  - Modificar `destroy()` para redirigir a `ingresos.index` (Tabla_Pendientes) con mensaje de éxito
  - Verificar que la eliminación de la foto y los registros relacionados (`ingreso_trabajadores`, `detalle_servicios`) funciona correctamente mediante las relaciones con `onDelete('cascade')` o eliminación explícita
  - _Requisitos: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [ ]* 14.1 Escribir test de propiedad: la eliminación remueve el ingreso y sus registros relacionados
    - **Propiedad 7: La eliminación remueve el ingreso y todos sus registros relacionados**
    - Para cualquier ingreso con registros en `ingreso_trabajadores` y `detalle_servicios`, `destroy()` debe eliminar el ingreso y todos sus registros relacionados
    - Ejecutar mínimo 100 iteraciones
    - **Valida: Requisito 6.2**

- [x] 15. Crear o actualizar `IngresoFactory` para soportar estados
  - Verificar si existe `database/factories/IngresoFactory.php`; si no existe, crearlo con los campos necesarios
  - Agregar los estados `pendiente()` y `confirmado()` como métodos de factory para facilitar los tests de propiedades
  - _Requisitos: (soporte para testing de propiedades 1–9)_

- [ ] 16. Checkpoint final — Asegurarse de que todos los tests pasan
  - Ejecutar la suite completa de tests con `php artisan test`
  - Asegurarse de que todos los tests pasan. Consultar al usuario si surgen dudas.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia requisitos específicos para trazabilidad
- Los checkpoints garantizan validación incremental
- Los tests de propiedades validan invariantes universales con mínimo 100 iteraciones usando Faker
- Los tests de ejemplo validan flujos específicos y casos borde concretos
- La ruta `GET /ingresos/confirmados` **debe** registrarse antes del resource para evitar que Laravel interprete `confirmados` como un parámetro `{ingreso}`
- El método `update()` existente se reutiliza desde el Panel de Confirmación para actualizar sin confirmar
