# Plan de Implementación: cambio-aceite

## Visión General

Implementación incremental del módulo de Cambio de Aceite para el taller mecánico. El plan sigue el orden natural de dependencias: primero las migraciones de ajuste, luego los modelos, el controlador, las rutas, las vistas y finalmente la integración en el layout. Cada tarea construye sobre la anterior y termina con el módulo completamente integrado y funcional.

## Tareas

- [x] 1. Migraciones de ajuste de base de datos
  - [x] 1.1 Crear migración `add_precio_total_descripcion_user_id_to_cambio_aceites_table`
    - Crear el archivo de migración con `php artisan make:migration add_precio_total_descripcion_user_id_to_cambio_aceites_table --table=cambio_aceites`
    - En `up()`: añadir `precio` (decimal 10,2, default 0) después de `fecha`, `total` (decimal 10,2, default 0) después de `precio`, `descripcion` (text, nullable) después de `total`, y `user_id` (foreignId con constrained a `users`) después de `descripcion`
    - En `down()`: `dropForeign(['user_id'])` y `dropColumn(['precio', 'total', 'descripcion', 'user_id'])`
    - _Requisitos: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

  - [x] 1.2 Crear migración `add_precio_total_to_cambio_productos_table`
    - Crear el archivo de migración con `php artisan make:migration add_precio_total_to_cambio_productos_table --table=cambio_productos`
    - En `up()`: añadir `precio` (decimal 10,2, NOT NULL) después de `cantidad`, y `total` (decimal 10,2, NOT NULL) después de `precio`
    - En `down()`: `dropColumn(['precio', 'total'])`
    - _Requisitos: 2.1, 2.2, 2.3, 2.4_

  - [ ]* 1.3 Escribir tests de integración para las migraciones
    - Verificar que ambas migraciones se ejecutan sin errores (`artisan migrate`)
    - Verificar que el esquema final de `cambio_aceites` contiene las columnas `precio`, `total`, `descripcion` y `user_id`
    - Verificar que el esquema final de `cambio_productos` contiene las columnas `precio` y `total`
    - Verificar que ambas migraciones se revierten sin errores (`artisan migrate:rollback`)
    - _Requisitos: 1.6, 2.4_

- [x] 2. Actualización de modelos Eloquent
  - [x] 2.1 Actualizar el modelo `CambioAceite`
    - Añadir `precio`, `total`, `descripcion` y `user_id` al array `$fillable` (junto a los campos existentes `cliente_id`, `trabajador_id`, `fecha`)
    - Añadir casts `'fecha' => 'date'`, `'precio' => 'decimal:2'` y `'total' => 'decimal:2'`
    - Añadir relación `cliente(): BelongsTo` hacia el modelo `Cliente` vía `cliente_id`
    - Añadir relación `trabajador(): BelongsTo` hacia el modelo `Trabajador` vía `trabajador_id`
    - Añadir relación `user(): BelongsTo` hacia el modelo `User` vía `user_id`
    - Añadir relación `productos(): BelongsToMany` hacia el modelo `Producto` a través de la tabla `cambio_productos`, con `withPivot('cantidad', 'precio', 'total')` y `withTimestamps()`
    - _Requisitos: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

  - [x] 2.2 Actualizar el modelo `CambioProducto`
    - Confirmar que `$fillable` contiene `['cambio_aceite_id', 'producto_id', 'cantidad', 'precio', 'total']`
    - Añadir casts `'cantidad' => 'integer'`, `'precio' => 'decimal:2'` y `'total' => 'decimal:2'`
    - Añadir relación `cambioAceite(): BelongsTo` hacia el modelo `CambioAceite`
    - Añadir relación `producto(): BelongsTo` hacia el modelo `Producto`
    - _Requisitos: 4.1, 4.2, 4.3, 4.4_

  - [ ]* 2.3 Escribir test de propiedad para round-trip de persistencia del cambio de aceite
    - **Propiedad: Round-trip de persistencia del cambio de aceite**
    - Generar con Faker: `cliente_id`, `trabajador_id`, `user_id`, `fecha`, `precio`, `total` aleatorios válidos; conjunto no vacío de productos con `cantidad`, `precio` y `total` por línea
    - Crear el `CambioAceite` con `CambioAceite::create()`, luego persistir cada línea con `CambioProducto::create()`
    - Recuperar con `CambioAceite::with(['cliente','trabajador','user','productos'])->find($id)`
    - Verificar que `cliente_id`, `trabajador_id`, `precio`, `total`, `user_id` coinciden exactamente
    - Verificar que `$cambioAceite->productos->pluck('id')->sort()` === `collect($productosIds)->sort()`
    - Verificar que los valores de pivote `cantidad`, `precio` y `total` coinciden por línea
    - Ejecutar 100 iteraciones
    - **Valida: Requisitos 9.1, 9.2**

  - [ ]* 2.4 Escribir test de propiedad para upsert de cliente por placa
    - **Propiedad: Upsert de cliente por placa**
    - Generar con Faker: placa aleatoria de 6-7 caracteres alfanuméricos
    - Caso A (cliente no existe): verificar que `Cliente::where('placa', $placa)->count()` pasa de 0 a 1 tras `firstOrCreate`
    - Caso B (cliente ya existe): verificar que `Cliente::where('placa', $placa)->count()` sigue siendo 1 tras un segundo `firstOrCreate` con la misma placa
    - Ejecutar 100 iteraciones alternando ambos casos
    - **Valida: Requisitos 9.9, 9.10**

- [x] 3. Checkpoint — Verificar modelos y migraciones
  - Ejecutar `php artisan migrate:fresh` y confirmar que no hay errores
  - Ejecutar los tests de la tarea 2 y confirmar que pasan
  - Preguntar al usuario si hay dudas antes de continuar con el controlador

- [x] 4. Implementar `CambioAceiteController`
  - [x] 4.1 Crear el archivo `app/Http/Controllers/CambioAceiteController.php` con la estructura base
    - Declarar el namespace, imports y la clase extendiendo `Controller`
    - Declarar los 9 métodos públicos con sus firmas: `index`, `create`, `store`, `buscarProductos`, `show`, `edit`, `update`, `destroy`, `ticket`
    - _Requisitos: 5.1, 6.1, 9.1, 10.1, 11.1, 12.1, 13.1, 14.2, 14.3_

  - [x] 4.2 Implementar `index()`
    - Cargar `CambioAceite::with(['cliente', 'trabajador'])->latest()->paginate(15)`
    - Retornar `view('cambio-aceite.index', compact('cambioAceites'))`
    - _Requisitos: 5.1, 5.2_

  - [x] 4.3 Implementar `create()`
    - Cargar `Trabajador::where('estado', true)->get()`
    - Retornar `view('cambio-aceite.create', compact('trabajadores'))`
    - _Requisitos: 6.1, 7.1_

  - [x] 4.4 Implementar `buscarProductos(Request $request)`
    - Obtener el parámetro `q` del request (default `''`)
    - Consultar `Producto::where('activo', true)->where('nombre', 'like', '%'.$q.'%')->select('id', 'nombre', 'precio_venta', 'stock')->limit(10)->get()`
    - Retornar `response()->json($productos)`
    - _Requisitos: 8.1, 14.2_

  - [x] 4.5 Implementar `store()` con validación y transacción
    - Definir las reglas de validación completas: `placa` (required, string, max:7), `nombre` (nullable, string, max:100), `dni` (nullable, string, max:8), `trabajador_id` (required, integer, exists:trabajadores,id), `fecha` (required, date), `descripcion` (nullable, string, max:1000), `precio` (required, numeric, min:0), `total` (required, numeric, gt:0), `productos` (required, array, min:1), `productos.*.producto_id` (required, integer, exists:productos,id), `productos.*.cantidad` (required, integer, min:1), `productos.*.precio` (required, numeric, gt:0), `productos.*.total` (required, numeric, min:0)
    - Añadir mensajes personalizados: `productos.required` y `productos.min` → "Debe agregar al menos un producto al cambio de aceite.", `trabajador_id.required` → "Debe asignar un trabajador al cambio de aceite."
    - Dentro de `DB::transaction`: ejecutar `Cliente::firstOrCreate(['placa' => $request->placa], [...])`, `CambioAceite::create([..., 'user_id' => auth()->id()])`, y un `foreach` de `$request->productos` creando cada `CambioProducto`
    - Envolver en `try/catch(\Throwable)`: en éxito redirigir a `cambio-aceite.show` con flash `success`; en error redirigir con `back()->withInput()` y flash `error`
    - _Requisitos: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9, 9.10_

  - [x] 4.6 Implementar `show()` y `ticket()`
    - En `show()`: cargar el cambioAceite con todas sus relaciones (`cliente`, `trabajador`, `user`, `productos`) y retornar `view('cambio-aceite.show', compact('cambioAceite'))`
    - En `ticket()`: igual que `show()` pero retornar `view('cambio-aceite.ticket', compact('cambioAceite'))`
    - _Requisitos: 11.1, 11.2, 12.1, 12.2_

  - [x] 4.7 Implementar `edit()` y `update()`
    - En `edit()`: cargar el cambioAceite con relaciones (`cliente`, `trabajador`, `productos`) + `Trabajador::where('estado', true)->get()`; retornar `view('cambio-aceite.edit', compact('cambioAceite', 'trabajadores'))`
    - En `update()`: mismas reglas de validación que `store()`; dentro de `DB::transaction`: `Cliente::firstOrCreate(...)`, `$cambioAceite->update([...])`, construir `$syncData` como array asociativo `[producto_id => ['cantidad' => ..., 'precio' => ..., 'total' => ...]]` y llamar a `$cambioAceite->productos()->sync($syncData)`; en éxito redirigir a `cambio-aceite.show` con flash `success`
    - _Requisitos: 10.1, 10.2, 10.3, 10.4, 10.5_

  - [x] 4.8 Implementar `destroy()`
    - Llamar a `$cambioAceite->delete()` (cascade elimina `cambio_productos` automáticamente)
    - Envolver en `try/catch(\Throwable)`: en éxito redirigir a `cambio-aceite.index` con flash `success`; en error redirigir con flash `error`
    - _Requisitos: 13.1, 13.2, 13.3, 13.4_

  - [ ]* 4.9 Escribir test de propiedad para atomicidad de la transacción
    - **Propiedad: Atomicidad de la transacción**
    - Registrar el conteo inicial de `CambioAceite::count()` y `CambioProducto::count()`
    - Simular un fallo en la persistencia de `CambioProducto` (p.ej. pasar un `producto_id` inexistente que viole la FK)
    - Verificar que `CambioAceite::count()` no aumentó respecto al conteo inicial
    - Verificar que `CambioProducto::count()` no aumentó respecto al conteo inicial
    - Ejecutar 100 iteraciones con distintos datos de entrada
    - **Valida: Requisito 9.3**

  - [ ]* 4.10 Escribir test de propiedad para sincronización de productos en edición
    - **Propiedad: Sincronización de productos en edición**
    - Crear un cambio de aceite existente con un conjunto inicial de productos
    - Generar con Faker un nuevo conjunto aleatorio de `producto_id`s (subconjunto de los disponibles) con `cantidad`, `precio` y `total` por línea
    - Llamar a `update()` con el nuevo conjunto
    - Verificar que `$cambioAceite->fresh()->productos->pluck('id')->sort()` === `collect($nuevosProductosIds)->sort()`
    - Verificar que los valores de pivote `cantidad`, `precio` y `total` coinciden con los nuevos valores enviados
    - Ejecutar 100 iteraciones
    - **Valida: Requisito 10.4**

- [x] 5. Registrar rutas en `routes/web.php`
  - Añadir el import `use App\Http\Controllers\CambioAceiteController;` al inicio del archivo
  - Añadir dentro del grupo `middleware(['auth'])` existente, en este orden:
    - `Route::get('/cambio-aceite/buscar-productos', [CambioAceiteController::class, 'buscarProductos'])->name('cambio-aceite.buscar-productos');` — **debe ir ANTES del resource**
    - `Route::resource('cambio-aceite', CambioAceiteController::class);`
    - `Route::get('/cambio-aceite/{cambioAceite}/ticket', [CambioAceiteController::class, 'ticket'])->name('cambio-aceite.ticket');`
  - _Requisitos: 14.1, 14.2, 14.3_

  - [ ]* 5.1 Escribir tests de acceso no autenticado a rutas del módulo
    - Verificar que `GET /cambio-aceite` redirige a `/login` cuando no hay sesión activa
    - Verificar que `GET /cambio-aceite/create` redirige a `/login` cuando no hay sesión activa
    - Verificar que `GET /cambio-aceite/1/ticket` redirige a `/login` cuando no hay sesión activa
    - _Requisitos: 14.4_

- [x] 6. Checkpoint — Verificar controlador y rutas
  - Ejecutar `php artisan route:list | grep cambio-aceite` y confirmar que aparecen las 8 rutas resource + la ruta `buscar-productos` + la ruta `ticket`
  - Ejecutar los tests de las tareas 4 y 5 y confirmar que pasan
  - Preguntar al usuario si hay dudas antes de continuar con las vistas

- [x] 7. Crear vistas Blade
  - [x] 7.1 Crear `resources/views/cambio-aceite/index.blade.php`
    - Extender `layouts.app` con `@extends('layouts.app')`
    - Mostrar encabezado con título "Cambio de Aceite" y botón "Nuevo cambio de aceite" enlazando a `cambio-aceite.create`
    - Mostrar flash messages de éxito y error con las clases `bg-green-100 text-green-800 border border-green-200` / `bg-red-100 text-red-800 border border-red-200`
    - Mostrar tabla con columnas: Fecha (`d/m/Y`), Cliente (placa + nombre si existe), Trabajador, Precio, Total, Acciones
    - Mostrar mensaje "No hay cambios de aceite registrados." cuando `$cambioAceites->isEmpty()`
    - Botones por fila: "Ver detalle" (`bg-gray-100 text-gray-700`), "Ticket" (`bg-blue-100 text-blue-700`), "Editar" (`bg-gray-100 text-gray-700`), "Eliminar" (`bg-red-100 text-red-700` con formulario DELETE y diálogo de confirmación `onclick="return confirm(...)"`)
    - Mostrar paginación con `{{ $cambioAceites->links() }}`
    - Aplicar estilos de tabla consistentes: `divide-y divide-gray-200`, contenedor `bg-white rounded-lg border border-gray-200`
    - _Requisitos: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9, 16.1, 16.4, 16.5_

  - [x] 7.2 Crear `resources/views/cambio-aceite/create.blade.php`
    - Extender `layouts.app`; mostrar encabezado con título "Nuevo Cambio de Aceite" y botón "Volver" (`bg-gray-100 text-gray-700`) enlazando a `cambio-aceite.index`
    - Formulario `POST` a `cambio-aceite.store`
    - **Sección cliente**: campo `placa` (obligatorio), `nombre` (opcional), `dni` (opcional) con pre-relleno via `old()`; campo `fecha` (date, obligatorio) con valor por defecto `date('Y-m-d')`; textarea `descripcion` (opcional)
    - **Sección trabajador**: `<select name="trabajador_id">` con trabajadores activos y opción vacía (obligatorio)
    - **Sección búsqueda de productos**: campo de texto con `id="buscar-producto"` y `div#resultados-busqueda` para mostrar resultados Ajax
    - **Tabla de detalle** `table#tabla-detalle` con columnas: Producto, Cantidad, Precio Unit., Subtotal, Eliminar; filas generadas dinámicamente por JS
    - **Sección totales**: campo `precio` (readonly, calculado por JS), toggle "Aplicar descuento por porcentaje" (`id="toggle-descuento"`), campo porcentaje (`id="porcentaje"`, oculto por defecto con `id="campo-porcentaje"`), mensaje de error inline (`id="error-porcentaje"`), campo `total` (editable, inicializado igual a `precio`)
    - Inputs hidden para enviar datos de la tabla: `productos[i][producto_id]`, `productos[i][cantidad]`, `productos[i][precio]`, `productos[i][total]`, más `precio` y `total`
    - **JavaScript embebido**: array `items` en memoria, búsqueda Ajax con debounce 300ms a `/cambio-aceite/buscar-productos?q=`, función `agregarProducto()` con incremento de cantidad si ya existe, `renderTabla()`, `recalcularTotales()` (precio = suma de subtotales; total = precio o precio*(1-pct/100)), `sincronizarHiddens()`, toggle descuento, validación de porcentaje ≤ 100
    - Mostrar errores de validación con `@error` y clases `border-red-400 bg-red-50` / `text-xs text-red-600`
    - Aplicar contenedor `bg-white rounded-lg border border-gray-200 p-6`; campos de ancho completo
    - _Requisitos: 6.1, 6.2, 6.3, 6.4, 6.5, 7.1, 7.2, 7.3, 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9, 8.10, 8.11, 9.5, 9.6, 9.7, 9.8, 16.2, 16.3, 16.6, 16.7_

  - [x] 7.3 Crear `resources/views/cambio-aceite/edit.blade.php`
    - Misma estructura que `create.blade.php` pero con formulario `PUT` a `cambio-aceite.update` usando `@method('PUT')`
    - Pre-seleccionar el trabajador actual (`$cambioAceite->trabajador_id`) en el `<select>`
    - Pre-rellenar placa (`$cambioAceite->cliente->placa`), nombre y DNI del cliente
    - Pre-rellenar `fecha` y `descripcion` con los valores actuales del registro
    - Pre-cargar los productos asignados en el array `items` de JS usando `@json($cambioAceite->productos)` con sus pivotes `cantidad`, `precio` y `total`; llamar a `renderTabla()` y `recalcularTotales()` al cargar la página
    - Pre-rellenar `precio` y `total` con los valores actuales del cambio de aceite
    - _Requisitos: 10.1, 10.2, 10.3_

  - [x] 7.4 Crear `resources/views/cambio-aceite/show.blade.php`
    - Extender `layouts.app`; mostrar encabezado con la fecha del registro y botones "Volver al listado" (`cambio-aceite.index`), "Editar" (`cambio-aceite.edit`), "Generar ticket" (`cambio-aceite.ticket`)
    - Tarjeta de datos: fecha (`d/m/Y`), cliente (placa y nombre si existe), trabajador asignado, descripción (si `$cambioAceite->descripcion` no es null), usuario que registró
    - Tabla de productos con columnas: Nombre del producto, Cantidad, Precio unitario, Subtotal por línea (usando `$producto->pivot->cantidad`, `$producto->pivot->precio`, `$producto->pivot->total`)
    - Sección de totales: si `$cambioAceite->total < $cambioAceite->precio`, mostrar precio original, descuento (`S/ {diferencia}`) y total final; si son iguales, mostrar solo el total
    - _Requisitos: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8_

  - [x] 7.5 Crear `resources/views/cambio-aceite/ticket.blade.php`
    - Vista independiente (puede extender un layout mínimo o ser standalone con `<html>` completo)
    - Mostrar encabezado con nombre del taller (`config('app.name')`)
    - Mostrar: fecha (`d/m/Y`), datos del cliente (placa, nombre si existe, DNI si existe), trabajador responsable
    - Tabla de productos con columnas: Nombre, Cant., P.Unit., Subtotal
    - Sección de totales: precio (inalterable), descuento (si `total != precio`), total final
    - Mostrar descripción si `$cambioAceite->descripcion` no es null
    - Botón "Imprimir" que ejecuta `window.print()`
    - Estilos `@media print` que ocultan navegación, sidebar y botón de imprimir
    - Formato: ancho máximo 400px centrado, tipografía sans-serif, separadores `<hr>` entre secciones
    - _Requisitos: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7, 12.8, 12.9_

  - [ ]* 7.6 Escribir tests de feature para las vistas
    - **Listado vacío**: `GET /cambio-aceite` autenticado sin datos → assertSee('No hay cambios de aceite registrados.')
    - **Columnas del listado**: `GET /cambio-aceite` con un registro → assertSee de Fecha, Cliente, Trabajador, Precio, Total, Acciones
    - **Vista de detalle con descuento**: `GET /cambio-aceite/{id}` con `total < precio` → assertSee del monto de descuento
    - **Vista de detalle sin descuento**: `GET /cambio-aceite/{id}` con `total === precio` → assertDontSee de la sección de descuento
    - **Ticket con descripción**: `GET /cambio-aceite/{id}/ticket` con `descripcion` no null → assertSee del texto de la descripción
    - **Ticket sin descripción**: `GET /cambio-aceite/{id}/ticket` con `descripcion` null → assertDontSee del bloque de descripción
    - **Validación de productos requeridos**: `POST /cambio-aceite` sin `productos` → assertSessionHasErrors('productos') con mensaje correcto
    - **Validación de trabajador requerido**: `POST /cambio-aceite` sin `trabajador_id` → assertSessionHasErrors('trabajador_id') con mensaje correcto
    - **Eliminación en cascada**: `DELETE /cambio-aceite/{id}` → verificar que los registros de `cambio_productos` asociados ya no existen en la base de datos
    - _Requisitos: 5.4, 5.2, 11.4, 11.5, 12.6, 9.8, 7.3, 13.2_

  - [ ]* 7.7 Escribir test de propiedad para paginación del listado
    - **Propiedad: Paginación correcta del listado**
    - Generar con Faker N cambios de aceite (N entre 0 y 50) con datos válidos
    - Hacer `GET /cambio-aceite` autenticado y obtener la respuesta
    - Verificar que la primera página contiene `min(N, 15)` filas de registro
    - Verificar que los registros están ordenados por `created_at` descendente (el más reciente aparece primero)
    - Ejecutar 100 iteraciones con distintos valores de N
    - **Valida: Requisito 5.1**

- [x] 8. Integrar el enlace "Cambio de Aceite" en el layout
  - Modificar `resources/views/layouts/app.blade.php`:
    - Añadir `$cambioAceiteActive = request()->routeIs('cambio-aceite.*');` en el bloque `@php` inicial
    - Añadir enlace "Cambio de Aceite" en el sidebar de escritorio con las clases de estado activo/inactivo del patrón existente y el ícono SVG de la llave de aceite (ver diseño, sección 10)
    - Añadir enlace "Cambio de Aceite" en el bottom nav móvil con las clases `text-blue-600` cuando activo y `text-gray-500` cuando inactivo
  - _Requisitos: 15.1, 15.2, 15.3, 15.4_

  - [ ]* 8.1 Escribir test de integración para el sidebar
    - Verificar que `GET /cambio-aceite` autenticado → la respuesta contiene el enlace "Cambio de Aceite" en el HTML
    - Verificar que `GET /dashboard` autenticado → la respuesta contiene el enlace "Cambio de Aceite" en el HTML (siempre visible)
    - _Requisitos: 15.1, 15.2_

- [x] 9. Checkpoint final — Verificar integración completa
  - Ejecutar `php artisan migrate:fresh` y confirmar que no hay errores
  - Ejecutar todos los tests del módulo (`php artisan test --filter=CambioAceite`) y confirmar que pasan
  - Verificar que `php artisan route:list` muestra las rutas de cambio-aceite correctamente protegidas
  - Preguntar al usuario si hay dudas o ajustes antes de cerrar el módulo

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia los requisitos específicos para trazabilidad completa
- Los tests de propiedad usan el patrón Faker + bucle de 100 iteraciones establecido en el proyecto (ver `LoginPropertyTest.php`)
- Los tests de feature siguen el patrón `RefreshDatabase` + `actingAs()` establecido en el proyecto (ver `LoginTest.php`)
- El controlador sigue el patrón de `VentaController.php` (DB::transaction, try/catch, flash messages)
- Las vistas siguen los patrones visuales de `ingresos/` y `ventas/` (Tailwind CSS, errores inline, flash messages)
- La ruta `buscar-productos` debe registrarse **antes** de `Route::resource` para evitar conflictos con Route Model Binding (patrón idéntico al de `VentaController::buscarProductos`)
- El `sync()` en `update()` usa array asociativo `[producto_id => ['cantidad' => ..., 'precio' => ..., 'total' => ...]]` para sincronizar el pivote enriquecido en una sola operación
- A diferencia del módulo de ingresos (N:N trabajadores), este módulo usa N:1 para el trabajador (campo `trabajador_id` directo en `cambio_aceites`)
