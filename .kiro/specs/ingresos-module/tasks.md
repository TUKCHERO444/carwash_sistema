# Plan de Implementación: ingresos-module

## Visión General

Implementación incremental del módulo de Ingresos para el taller mecánico. El plan sigue el orden natural de dependencias: primero la base de datos, luego los modelos, el controlador, las rutas, las vistas y finalmente la integración en el layout. Cada tarea construye sobre la anterior y termina con el módulo completamente integrado y funcional.

## Tareas

- [x] 1. Migraciones de ajuste de base de datos
  - [x] 1.1 Crear migración `make_clientes_nombre_dni_nullable`
    - Crear el archivo de migración con `php artisan make:migration make_clientes_nombre_dni_nullable --table=clientes`
    - En `up()`: llamar a `$table->dropUnique(['dni'])`, luego `->nullable()->change()` en `nombre` y `dni`
    - En `down()`: revertir `nombre` y `dni` a `nullable(false)` y restaurar `$table->unique('dni')`
    - _Requisitos: 1.1, 1.2, 1.3, 1.4_

  - [x] 1.2 Crear migración `add_precio_total_user_id_to_ingresos_table`
    - Crear el archivo de migración con `php artisan make:migration add_precio_total_user_id_to_ingresos_table --table=ingresos`
    - En `up()`: añadir `precio` (decimal 10,2, default 0), `total` (decimal 10,2, default 0) y `user_id` (foreignId con constrained a `users`) después de `fecha`
    - En `down()`: `dropForeign(['user_id'])` y `dropColumn(['precio', 'total', 'user_id'])`
    - _Requisitos: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_

  - [ ]* 1.3 Escribir tests de integración para las migraciones
    - Verificar que ambas migraciones se ejecutan sin errores (`artisan migrate`)
    - Verificar que el esquema final de `clientes` tiene `nombre` y `dni` nullable
    - Verificar que el esquema final de `ingresos` contiene las columnas `precio`, `total` y `user_id`
    - Verificar que ambas migraciones se revierten sin errores (`artisan migrate:rollback`)
    - _Requisitos: 1.4, 2.6_

- [x] 2. Actualización de modelos Eloquent
  - [x] 2.1 Actualizar el modelo `Ingreso`
    - Añadir `precio`, `total` y `user_id` al array `$fillable`
    - Añadir casts `'precio' => 'decimal:2'` y `'total' => 'decimal:2'`
    - Añadir relación `user(): BelongsTo` hacia el modelo `User` vía `user_id`
    - Verificar que las relaciones `cliente()`, `vehiculo()`, `trabajadores()` y `servicios()` ya existen y son correctas
    - _Requisitos: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7_

  - [x] 2.2 Actualizar el modelo `Cliente`
    - Confirmar que `$fillable` contiene `['dni', 'nombre', 'placa']`
    - Añadir casts `'dni' => 'string'` y `'nombre' => 'string'` (reflejan nullable en BD)
    - Confirmar que la relación `ingresos(): HasMany` ya existe
    - _Requisitos: 4.1, 4.2, 4.3_

  - [ ]* 2.3 Escribir test de propiedad para round-trip de persistencia del ingreso
    - **Propiedad 3: Round-trip de persistencia del ingreso**
    - Generar con Faker: `cliente_id`, `vehiculo_id`, `user_id`, `fecha`, `precio`, `total` aleatorios válidos; conjunto no vacío de `trabajador_id`s; conjunto (posiblemente vacío) de `servicio_id`s
    - Crear el `Ingreso` con `Ingreso::create()`, hacer `sync()` de trabajadores y servicios
    - Recuperar con `Ingreso::with(['cliente','vehiculo','user','trabajadores','servicios'])->find($id)`
    - Verificar que `cliente_id`, `vehiculo_id`, `precio`, `total`, `user_id` coinciden exactamente
    - Verificar que `$ingreso->trabajadores->pluck('id')->sort()` === `collect($trabajadoresIds)->sort()`
    - Verificar que `$ingreso->servicios->pluck('id')->sort()` === `collect($serviciosIds)->sort()`
    - Ejecutar 100 iteraciones
    - **Valida: Requisitos 10.1, 10.2, 10.3**

  - [ ]* 2.4 Escribir test de propiedad para upsert de cliente por placa
    - **Propiedad 5: Upsert de cliente por placa**
    - Generar con Faker: placa aleatoria de 6-7 caracteres alfanuméricos
    - Caso A (cliente no existe): verificar que `Cliente::where('placa', $placa)->count()` pasa de 0 a 1 tras `firstOrCreate`
    - Caso B (cliente ya existe): verificar que `Cliente::where('placa', $placa)->count()` sigue siendo 1 tras un segundo `firstOrCreate` con la misma placa
    - Ejecutar 100 iteraciones alternando ambos casos
    - **Valida: Requisitos 10.10, 10.11**

- [x] 3. Checkpoint — Verificar modelos y migraciones
  - Ejecutar `php artisan migrate:fresh` y confirmar que no hay errores
  - Ejecutar los tests de la tarea 2 y confirmar que pasan
  - Preguntar al usuario si hay dudas antes de continuar con el controlador

- [x] 4. Implementar `IngresoController`
  - [x] 4.1 Crear el archivo `app/Http/Controllers/IngresoController.php` con la estructura base
    - Declarar el namespace, imports y la clase extendiendo `Controller`
    - Declarar los 8 métodos públicos con sus firmas: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `ticket`
    - _Requisitos: 5.1, 6.1, 10.1, 11.1, 12.1, 13.1, 14.1, 15.2, 15.3_

  - [x] 4.2 Implementar `index()`
    - Cargar `Ingreso::with(['cliente', 'vehiculo', 'trabajadores'])->latest()->paginate(15)`
    - Retornar `view('ingresos.index', compact('ingresos'))`
    - _Requisitos: 5.1, 5.2_

  - [x] 4.3 Implementar `create()`
    - Cargar `Vehiculo::all()`, `Trabajador::where('estado', true)->get()`, `Servicio::all()`
    - Retornar `view('ingresos.create', compact('vehiculos', 'trabajadores', 'servicios'))`
    - _Requisitos: 6.1, 6.2, 8.1, 9.1_

  - [x] 4.4 Implementar `store()` con validación y transacción
    - Definir las reglas de validación completas (ver diseño: `vehiculo_id`, `placa`, `nombre`, `dni`, `fecha`, `foto`, `trabajadores`, `servicios`, `precio`, `total`) con sus mensajes personalizados
    - Dentro de `DB::transaction`: ejecutar `Cliente::firstOrCreate(['placa' => $request->placa], [...])`, `Storage::put('public/ingresos', ...)` si hay foto, `Ingreso::create([..., 'user_id' => auth()->id()])`, `sync` de trabajadores y servicios
    - Envolver en `try/catch(\Throwable)`: en éxito redirigir a `ingresos.show` con flash `success`; en error redirigir con `back()->withInput()` y flash `error`
    - _Requisitos: 6.3, 6.4, 6.5, 7.3, 7.4, 8.3, 8.4, 9.2, 9.3, 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 10.9, 10.10, 10.11_

  - [x] 4.5 Implementar `show()` y `ticket()`
    - En `show()`: cargar el ingreso con todas sus relaciones (`cliente`, `vehiculo`, `user`, `trabajadores`, `servicios`) y retornar `view('ingresos.show', compact('ingreso'))`
    - En `ticket()`: igual que `show()` pero retornar `view('ingresos.ticket', compact('ingreso'))`
    - _Requisitos: 12.1, 12.2, 12.3, 12.4, 13.1_

  - [x] 4.6 Implementar `edit()` y `update()`
    - En `edit()`: cargar el ingreso con relaciones + `Vehiculo::all()`, `Trabajador::where('estado', true)->get()`, `Servicio::all()`; retornar `view('ingresos.edit', compact('ingreso', 'vehiculos', 'trabajadores', 'servicios'))`
    - En `update()`: mismas reglas de validación que `store()`; dentro de `DB::transaction`: si hay nueva foto eliminar la anterior con `Storage::delete($ingreso->foto)` y almacenar la nueva, actualizar el ingreso con `$ingreso->update([...])`, `sync` de trabajadores y servicios; en éxito redirigir a `ingresos.show` con flash `success`
    - _Requisitos: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8_

  - [x] 4.7 Implementar `destroy()`
    - Si `$ingreso->foto` no es null, llamar a `Storage::delete($ingreso->foto)`
    - Llamar a `$ingreso->delete()` (cascade elimina pivotes automáticamente)
    - Envolver en `try/catch(\Throwable)`: en éxito redirigir a `ingresos.index` con flash `success`; en error redirigir con flash `error`
    - _Requisitos: 14.1, 14.2, 14.3, 14.4, 14.5_

  - [ ]* 4.8 Escribir test de propiedad para atomicidad de la transacción
    - **Propiedad 4: Atomicidad de la transacción**
    - Registrar el conteo inicial de `Ingreso::count()` e `IngresoTrabajador::count()`
    - Simular un fallo en el `sync` de trabajadores (p.ej. pasar un `trabajador_id` inexistente que viole la FK)
    - Verificar que `Ingreso::count()` no aumentó respecto al conteo inicial
    - Verificar que `IngresoTrabajador::count()` no aumentó respecto al conteo inicial
    - Ejecutar 100 iteraciones con distintos datos de entrada
    - **Valida: Requisito 10.4**

  - [ ]* 4.9 Escribir test de propiedad para sincronización de relaciones en edición
    - **Propiedad 6: Sincronización de relaciones en edición**
    - Crear un ingreso existente con un conjunto inicial de trabajadores y servicios
    - Generar con Faker un nuevo conjunto aleatorio de `trabajador_id`s (subconjunto de los disponibles) y `servicio_id`s
    - Llamar a `update()` con el nuevo conjunto
    - Verificar que `$ingreso->fresh()->trabajadores->pluck('id')->sort()` === `collect($nuevosTrabajadores)->sort()`
    - Verificar que `$ingreso->fresh()->servicios->pluck('id')->sort()` === `collect($nuevosServicios)->sort()`
    - Ejecutar 100 iteraciones
    - **Valida: Requisito 11.5**

- [x] 5. Registrar rutas en `routes/web.php`
  - Añadir dentro del grupo `middleware(['auth'])` existente:
    - `Route::resource('ingresos', IngresoController::class);`
    - `Route::get('ingresos/{ingreso}/ticket', [IngresoController::class, 'ticket'])->name('ingresos.ticket');`
  - Añadir el import `use App\Http\Controllers\IngresoController;` al inicio del archivo
  - _Requisitos: 15.1, 15.2, 15.3_

  - [ ]* 5.1 Escribir tests de acceso no autenticado a rutas del módulo
    - Verificar que `GET /ingresos` redirige a `/login` cuando no hay sesión activa
    - Verificar que `GET /ingresos/create` redirige a `/login` cuando no hay sesión activa
    - Verificar que `GET /ingresos/1/ticket` redirige a `/login` cuando no hay sesión activa
    - _Requisitos: 15.4_

- [x] 6. Checkpoint — Verificar controlador y rutas
  - Ejecutar `php artisan route:list | grep ingresos` y confirmar que aparecen las 8 rutas resource + la ruta ticket
  - Ejecutar los tests de las tareas 4 y 5 y confirmar que pasan
  - Preguntar al usuario si hay dudas antes de continuar con las vistas

- [x] 7. Crear vistas Blade
  - [x] 7.1 Crear `resources/views/ingresos/index.blade.php`
    - Extender `layouts.app` con `@extends('layouts.app')`
    - Mostrar encabezado con título "Ingresos" y botón "Nuevo ingreso" enlazando a `ingresos.create`
    - Mostrar flash messages de éxito y error con las clases `bg-green-100 text-green-800` / `bg-red-100 text-red-800`
    - Mostrar tabla con columnas: Fecha (`d/m/Y`), Cliente (placa + nombre si existe), Vehículo, Trabajadores (lista separada por comas), Precio, Total, Acciones
    - Mostrar mensaje "No hay ingresos registrados." cuando `$ingresos->isEmpty()`
    - Botones por fila: "Ver detalle" (`bg-gray-100 text-gray-700`), "Ticket" (`bg-blue-100 text-blue-700`), "Editar" (`bg-gray-100 text-gray-700`), "Eliminar" (con formulario DELETE y diálogo de confirmación `onclick="return confirm(...)"`)
    - Mostrar paginación con `{{ $ingresos->links() }}`
    - Aplicar estilos de tabla consistentes: `divide-y divide-gray-200`, contenedor `bg-white rounded-lg border border-gray-200`
    - _Requisitos: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9, 17.1, 17.4, 17.5_

  - [x] 7.2 Crear `resources/views/ingresos/create.blade.php`
    - Extender `layouts.app`; mostrar encabezado con título "Nuevo Ingreso" y botón "Volver" (`bg-gray-100 text-gray-700`) enlazando a `ingresos.index`
    - Formulario `POST` a `ingresos.store` con `enctype="multipart/form-data"`
    - Campo `vehiculo_id`: `<select>` con nombre, precio base y opción vacía; al cambiar actualiza `precioBase` en JS
    - Campos `placa` (obligatorio), `nombre` (opcional), `dni` (opcional) con pre-relleno via `old()`
    - Campo `fecha` (date, obligatorio) con valor por defecto `date('Y-m-d')`
    - Campo `foto` (file, opcional): acepta `image/*`; listener `FileReader` para mostrar preview
    - Lista de checkboxes de trabajadores activos (obligatorio al menos uno)
    - Lista de checkboxes de servicios con precio; al marcar/desmarcar recalcula `precio`
    - Campos `precio` (readonly, calculado por JS) y `total` (editable, inicializado igual a `precio`)
    - Toggle "Aplicar descuento por porcentaje": muestra campo numérico; calcula `total = precio * (1 - pct/100)`; valida que `pct <= 100`; muestra error inline si supera 100
    - Mostrar errores de validación con `@error` y clases `border-red-400 bg-red-50` / `text-xs text-red-600`
    - Aplicar contenedor `bg-white rounded-lg border border-gray-200 p-6`; campos de ancho completo
    - _Requisitos: 6.1, 6.2, 6.3, 6.4, 6.5, 7.1, 7.2, 8.1, 8.2, 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9, 9.10, 17.2, 17.3, 17.6, 17.7_

  - [x] 7.3 Crear `resources/views/ingresos/edit.blade.php`
    - Misma estructura que `create.blade.php` pero con formulario `PUT` a `ingresos.update`
    - Pre-seleccionar el vehículo actual (`$ingreso->vehiculo_id`)
    - Pre-rellenar placa (`$ingreso->cliente->placa`), nombre y DNI del cliente
    - Pre-seleccionar los trabajadores actualmente asignados (`$ingreso->trabajadores->pluck('id')`)
    - Pre-seleccionar los servicios actualmente asignados (`$ingreso->servicios->pluck('id')`)
    - Mostrar la foto actual si existe (`$ingreso->foto`) con opción de reemplazarla
    - Pre-rellenar `precio` y `total` con los valores actuales del ingreso
    - _Requisitos: 11.1, 11.2, 11.3, 11.4, 11.7_

  - [x] 7.4 Crear `resources/views/ingresos/show.blade.php`
    - Extender `layouts.app`; mostrar encabezado con título "Detalle del Ingreso"
    - Mostrar: fecha (`d/m/Y`), cliente (placa + nombre si existe), vehículo (nombre + precio base), foto (si `$ingreso->foto` no es null), usuario que registró
    - Mostrar lista de trabajadores asignados
    - Mostrar tabla de servicios con columnas Nombre y Precio
    - Sección de totales: si `$ingreso->total < $ingreso->precio`, mostrar precio original, descuento (`S/ {diferencia}`) y total final; si son iguales, mostrar solo el total
    - Botones: "Generar ticket" (enlaza a `ingresos.ticket`), "Editar" (enlaza a `ingresos.edit`), "Volver al listado" (enlaza a `ingresos.index`)
    - _Requisitos: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7, 12.8, 12.9_

  - [x] 7.5 Crear `resources/views/ingresos/ticket.blade.php`
    - Vista independiente (puede extender un layout mínimo o ser standalone con `<html>` completo)
    - Mostrar encabezado con nombre del taller (`config('app.name')`)
    - Mostrar: fecha (`d/m/Y`), datos del cliente (placa, nombre si existe, DNI si existe)
    - Mostrar vehículo con precio base
    - Mostrar lista de trabajadores asignados
    - Mostrar tabla de servicios con nombre y precio
    - Sección de totales: precio base del vehículo, suma de servicios, precio total (inalterable); si hay descuento mostrar monto y total final; si no hay descuento mostrar solo el total
    - Mostrar foto del vehículo si `$ingreso->foto` no es null
    - Botón "Imprimir" que ejecuta `window.print()`
    - Estilos `@media print` que ocultan navegación y botón de imprimir
    - Formato: ancho máximo 400px centrado, tipografía sans-serif, separadores `<hr>` entre secciones
    - _Requisitos: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7, 13.8, 13.9, 13.10_

  - [ ]* 7.6 Escribir tests de feature para las vistas
    - **Listado vacío**: `GET /ingresos` autenticado sin datos → assertSee('No hay ingresos registrados.')
    - **Columnas del listado**: `GET /ingresos` con un ingreso → assertSee de Fecha, Cliente, Vehículo, Trabajadores, Precio, Total, Acciones
    - **Vista de detalle con descuento**: `GET /ingresos/{id}` con `total < precio` → assertSee del monto de descuento
    - **Vista de detalle sin descuento**: `GET /ingresos/{id}` con `total === precio` → assertDontSee de la sección de descuento
    - **Ticket con foto**: `GET /ingresos/{id}/ticket` con `foto` no null → assertSee de la URL de la foto
    - **Ticket sin foto**: `GET /ingresos/{id}/ticket` con `foto` null → assertDontSee de la etiqueta `<img` de foto
    - **Validación de trabajadores requeridos**: `POST /ingresos` sin `trabajadores` → assertSessionHasErrors('trabajadores') con mensaje correcto
    - **Validación de foto inválida**: `POST /ingresos` con archivo no imagen → assertSessionHasErrors('foto')
    - **Eliminación con foto**: `DELETE /ingresos/{id}` con foto → verificar que `Storage::assertMissing` del archivo eliminado
    - _Requisitos: 5.4, 5.2, 12.5, 12.6, 13.7, 8.4, 7.5, 14.3_

  - [ ]* 7.7 Escribir test de propiedad para paginación del listado
    - **Propiedad 7: Paginación correcta del listado**
    - Generar con Faker N ingresos (N entre 0 y 50) con datos válidos
    - Hacer `GET /ingresos` autenticado y obtener la respuesta
    - Verificar que la primera página contiene `min(N, 15)` filas de ingreso
    - Verificar que los registros están ordenados por `created_at` descendente (el más reciente aparece primero)
    - Ejecutar 100 iteraciones con distintos valores de N
    - **Valida: Requisito 5.1**

- [x] 8. Integrar el enlace "Ingresos" en el layout
  - Modificar `resources/views/layouts/app.blade.php`:
    - Añadir `$ingresosActive = request()->routeIs('ingresos.*');` en el bloque `@php` inicial
    - Añadir enlace "Ingresos" en el sidebar de escritorio (después del enlace "Ventas"), con las clases de estado activo/inactivo del patrón existente y un ícono SVG apropiado (p.ej. ícono de llave inglesa o clipboard)
    - Añadir enlace "Ingresos" en el bottom nav móvil (después del enlace "Ventas"), con las clases `text-blue-600` cuando activo y `text-gray-500` cuando inactivo
  - _Requisitos: 16.1, 16.2, 16.3, 16.4_

  - [ ]* 8.1 Escribir test de integración para el sidebar
    - Verificar que `GET /ingresos` autenticado → la respuesta contiene el enlace "Ingresos" en el HTML
    - Verificar que `GET /dashboard` autenticado → la respuesta contiene el enlace "Ingresos" en el HTML (siempre visible)
    - _Requisitos: 16.1, 16.2_

- [x] 9. Checkpoint final — Verificar integración completa
  - Ejecutar `php artisan migrate:fresh` y confirmar que no hay errores
  - Ejecutar todos los tests del módulo (`php artisan test --filter=Ingreso`) y confirmar que pasan
  - Verificar que `php artisan route:list` muestra las rutas de ingresos correctamente protegidas
  - Preguntar al usuario si hay dudas o ajustes antes de cerrar el módulo

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia los requisitos específicos para trazabilidad completa
- Los tests de propiedad usan el patrón Faker + bucle de 100 iteraciones establecido en el proyecto (ver `LoginPropertyTest.php`)
- Los tests de feature siguen el patrón `RefreshDatabase` + `actingAs()` establecido en el proyecto (ver `LoginTest.php`)
- El controlador sigue el patrón de `VentaController.php` (DB::transaction, try/catch, flash messages)
- Las vistas siguen los patrones visuales de `ventas/` (Tailwind CSS, errores inline, flash messages)
- La ruta `ticket` debe registrarse **antes** de `Route::resource` para evitar conflictos con Route Model Binding (patrón de `VentaController::buscarProductos`)
