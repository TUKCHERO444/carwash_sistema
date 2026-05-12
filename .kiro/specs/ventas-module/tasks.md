# Plan de implementacion: ventas-module
$content = @'
# Plan de implementacion: ventas-module

## Descripcion general

Implementacion del modulo completo de Ventas en Laravel 11 + Blade + Tailwind CSS. El modulo reemplaza las migraciones y modelos existentes (que usan cliente_id y fecha) por la estructura definitiva con user_id, correlativo, subtotal y timestamps estandar. Incluye un VentaController con endpoint Ajax para busqueda de productos, cuatro vistas Blade y la integracion en el sidebar y bottom nav del layout, siguiendo exactamente los patrones de los modulos productos y trabajadores.

---

## Tareas

- [x] 1. Reemplazar migracion de la tabla ventas
  - Editar database/migrations/2024_01_01_000011_create_ventas_table.php
  - Metodo up(): Schema::create con columnas id, correlativo (string unique), observacion (text nullable), subtotal (decimal 10,2 default 0), total (decimal 10,2 default 0), user_id (foreignId constrained users onDelete restrict), timestamps()
  - Eliminar las columnas cliente_id, fecha e indice fecha del esquema anterior
  - Metodo down(): Schema::dropIfExists ventas
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [ ]* 1.1 Test de smoke — columnas de ventas existen tras migracion
    - Verificar que las columnas correlativo, subtotal, total y user_id existen en la tabla ventas despues de ejecutar la migracion
    - Verificar que las columnas cliente_id y fecha ya no existen
    - **Property 1: Relaciones del modelo Venta**
    - **Validates: Requirements 1.1, 1.3**

- [x] 2. Reemplazar migracion de la tabla detalle_ventas
  - Editar database/migrations/2024_01_01_000012_create_detalle_ventas_table.php
  - Metodo up(): Schema::create con columnas id, venta_id (foreignId constrained ventas onDelete cascade), producto_id (foreignId constrained productos onDelete restrict), cantidad (integer default 1), precio_unitario (decimal 10,2), subtotal (decimal 10,2), timestamps()
  - Eliminar el indice unico compuesto (venta_id, producto_id) del esquema anterior
  - Cambiar onDelete de producto_id de cascade a restrict
  - Metodo down(): Schema::dropIfExists detalle_ventas
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [ ]* 2.1 Test de smoke — columnas de detalle_ventas existen tras migracion
    - Verificar que las columnas venta_id, producto_id, cantidad, precio_unitario y subtotal existen en la tabla detalle_ventas
    - Verificar que ya no existe el indice unico compuesto (venta_id, producto_id)
    - **Property 2: Relaciones del modelo DetalleVenta**
    - **Validates: Requirements 2.1, 2.2, 2.3**

- [x] 3. Reemplazar modelo Venta
  - Editar app/Models/Venta.php
  - Reemplazar fillable: correlativo, observacion, subtotal, total, user_id (eliminar cliente_id y fecha)
  - Reemplazar casts: subtotal como decimal:2, total como decimal:2 (eliminar cast de fecha)
  - Reemplazar relacion cliente() por user(): belongsTo(User::class)
  - Mantener relacion detalles(): hasMany(DetalleVenta::class)
  - Agregar relacion productos(): belongsToMany(Producto::class, detalle_ventas) con withPivot(cantidad, precio_unitario, subtotal) y withTimestamps()
  - Agregar imports: BelongsToMany, User
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

  - [ ]* 3.1 Test de propiedades — relaciones del modelo Venta
    - Crear User + Venta + N DetalleVenta aleatorios (N entre 1 y 5), verificar que venta->user devuelve el User correcto
    - Verificar que venta->detalles->count() es igual a N
    - Verificar que venta->productos contiene los productos con los pivotes cantidad, precio_unitario y subtotal
    - **Property 1: Relaciones del modelo Venta**
    - **Validates: Requirements 3.3, 3.4, 3.5**

- [x] 4. Verificar y completar modelo DetalleVenta
  - Revisar app/Models/DetalleVenta.php
  - Confirmar que fillable incluye: venta_id, producto_id, cantidad, precio_unitario, subtotal
  - Confirmar que casts incluye: cantidad como integer, precio_unitario como decimal:2, subtotal como decimal:2
  - Confirmar que las relaciones venta(): belongsTo(Venta::class) y producto(): belongsTo(Producto::class) existen y son correctas
  - No se requieren cambios si el modelo ya esta correcto segun el diseno
  - _Requirements: 4.1, 4.2, 4.3, 4.4_

  - [ ]* 4.1 Test de propiedades — relaciones del modelo DetalleVenta
    - Crear Venta + Producto + DetalleVenta, verificar que detalle->venta devuelve la Venta asociada
    - Verificar que detalle->producto devuelve el Producto asociado
    - **Property 2: Relaciones del modelo DetalleVenta**
    - **Validates: Requirements 4.3, 4.4**

- [x] 5. Checkpoint — verificar migraciones y modelos
  - Ejecutar php artisan migrate:fresh y confirmar que no hay errores
  - Asegurarse de que los tests de modelos pasan, preguntar al usuario si surgen dudas.

- [x] 6. Crear VentaController
  - Crear app/Http/Controllers/VentaController.php
  - Importar: Venta, DetalleVenta, Producto, DB, Request, JsonResponse, RedirectResponse, View

  - [x] 6.1 Implementar metodo index()
    - Venta::with(user)->latest()->paginate(15) y retornar view ventas.index con compact(ventas)
    - _Requirements: 6.1_

  - [x] 6.2 Implementar metodo create()
    - Retornar view ventas.create
    - _Requirements: 7.1_

  - [x] 6.3 Implementar metodo buscarProductos(Request $request)
    - Obtener parametro q del request (default cadena vacia)
    - Buscar Producto::where(activo, true)->where(nombre, like, %q%)->select(id, nombre, precio_venta, stock)->limit(10)->get()
    - Retornar response()->json($productos)
    - _Requirements: 7.3_

  - [ ]* 6.4 Test de propiedades — busqueda Ajax filtra correctamente
    - Crear productos activos e inactivos con nombres variados, enviar GET /ventas/buscar-productos?q={termino}
    - Verificar que la respuesta JSON contiene solo productos activos cuyo nombre contiene el termino (insensible a mayusculas)
    - Verificar que los campos devueltos son id, nombre, precio_venta y stock, y que el resultado esta limitado a 10
    - **Property 8: Busqueda Ajax devuelve solo productos activos que coinciden con el termino**
    - **Validates: Requirements 7.3**

  - [x] 6.5 Implementar metodo store(Request $request)
    - Validar: observacion (nullable|string|max:500), subtotal (required|numeric|min:0), total (required|numeric|gt:0), productos (required|array|min:1), productos.*.producto_id (required|integer|exists:productos,id), productos.*.cantidad (required|integer|min:1), productos.*.precio_unitario (required|numeric|gt:0), productos.*.subtotal (required|numeric|min:0)
    - Mensaje personalizado para productos.required y productos.min: Debe agregar al menos un producto a la venta.
    - Dentro de DB::transaction(): calcular correlativo con VTA- . str_pad((Venta::max(id) ?? 0) + 1, 4, 0, STR_PAD_LEFT), crear Venta::create([correlativo, observacion, subtotal, total, user_id => auth()->id()]), iterar productos creando DetalleVenta::create([...]) y Producto::where(id)->decrement(stock, cantidad)
    - Redirigir a ventas.show con la venta creada y flash success Venta registrada correctamente.
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8_

  - [ ]* 6.6 Test de propiedades — persistencia transaccional de venta, detalles y reduccion de stock
    - Crear N productos activos con stock conocido, enviar POST /ventas con datos validos aleatorios
    - Verificar que se crea exactamente 1 registro en ventas, N registros en detalle_ventas y que el stock de cada producto se decremento en la cantidad registrada
    - Verificar que el campo inventario de cada producto no se modifico
    - **Property 4: Persistencia transaccional de venta, detalles y reduccion de stock**
    - **Validates: Requirements 9.1, 9.2, 9.3, 9.4, 12.1, 12.2, 12.3**

  - [ ]* 6.7 Test de propiedades — validacion rechaza ventas sin productos o con datos invalidos
    - Enviar POST /ventas sin productos: verificar redireccion al formulario con error Debe agregar al menos un producto a la venta.
    - Enviar POST /ventas con total <= 0: verificar redireccion con errores de validacion
    - Enviar POST /ventas con cantidad < 1 en alguna linea: verificar redireccion con errores de validacion
    - Verificar que en ninguno de los casos se crea ningun registro en ventas ni detalle_ventas
    - **Property 5: Validacion rechaza ventas sin productos o con datos invalidos**
    - **Validates: Requirements 9.6, 9.7, 9.8**

  - [ ]* 6.8 Test de propiedades — generacion de correlativo secuencial y unico
    - Crear N ventas consecutivas (N entre 1 y 10), verificar que cada correlativo tiene el formato VTA-XXXX
    - Verificar que XXXX es el numero con cero-relleno a 4 digitos y que no hay correlativos duplicados
    - **Property 3: Generacion de correlativo secuencial y unico**
    - **Validates: Requirements 5.1, 5.2, 5.3**

  - [x] 6.9 Implementar metodo show(Venta $venta)
    - $venta->load(user, detalles.producto) y retornar view ventas.show con compact(venta)
    - _Requirements: 10.1_

  - [x] 6.10 Implementar metodo ticket(Venta $venta)
    - $venta->load(user, detalles.producto) y retornar view ventas.ticket con compact(venta)
    - _Requirements: 11.1_

  - [x] 6.11 Implementar metodo destroy(Venta $venta)
    - Envolver en try/catch(\Throwable)
    - Dentro de DB::transaction(): iterar $venta->detalles y Producto::where(id)->increment(stock, detalle->cantidad), luego $venta->delete()
    - En exito: redirigir a ventas.index con flash success Venta eliminada y stock restaurado correctamente.
    - En error: redirigir a ventas.index con flash error No se pudo eliminar la venta. Intente nuevamente.
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5_

  - [ ]* 6.12 Test de propiedades — eliminacion transaccional con restauracion de stock
    - Crear una Venta con N lineas de detalle y stock conocido en cada producto
    - Enviar DELETE /ventas/{venta}, verificar que el stock de cada producto se incremento en la cantidad registrada
    - Verificar que la Venta y sus DetalleVenta ya no existen en la base de datos
    - Verificar que la respuesta redirige a ventas.index con el flash de exito
    - **Property 6: Eliminacion transaccional con restauracion de stock**
    - **Validates: Requirements 16.1, 16.2, 16.3, 16.5**

- [x] 7. Checkpoint — verificar controlador
  - Asegurarse de que todos los tests del controlador pasan, preguntar al usuario si surgen dudas.

- [x] 8. Registrar rutas en routes/web.php
  - Agregar use App\Http\Controllers\VentaController; en los imports
  - Agregar un nuevo grupo Route::middleware(auth)->group() independiente (sin restriccion de rol)
  - Dentro del grupo, registrar primero la ruta Ajax: Route::get(/ventas/buscar-productos, [VentaController::class, buscarProductos])->name(ventas.buscar-productos)
  - Luego registrar el resource: Route::resource(ventas, VentaController::class)->only([index, create, store, show, destroy])
  - Luego registrar la ruta del ticket: Route::get(/ventas/{venta}/ticket, [VentaController::class, ticket])->name(ventas.ticket)
  - La ruta Ajax DEBE ir antes del resource para evitar conflictos con Route Model Binding
  - _Requirements: 13.1, 13.2_

  - [ ]* 8.1 Test de propiedades — control de acceso (autenticacion requerida)
    - Para cada ruta del modulo (GET /ventas, GET /ventas/create, POST /ventas, GET /ventas/buscar-productos, GET /ventas/{id}, GET /ventas/{id}/ticket, DELETE /ventas/{id}), enviar peticion sin sesion autenticada
    - Verificar que la respuesta redirige a /login
    - **Property 7: Control de acceso — autenticacion requerida**
    - **Validates: Requirements 13.1, 13.3**

  - [ ]* 8.2 Test de smoke — rutas del modulo registradas correctamente
    - Verificar que las siete rutas del modulo estan registradas en el router de Laravel
    - **Validates: Requirements 13.2**

- [x] 9. Crear vista ventas/index.blade.php
  - Crear resources/views/ventas/index.blade.php extendiendo layouts.app
  - Flash messages de exito/error con clases bg-green-100 text-green-800 border border-green-200 / bg-red-100 text-red-800 border border-red-200
  - Encabezado: titulo Ventas + boton Nueva venta -> route(ventas.create) con clases bg-blue-600 text-white
  - Estado vacio: div class text-center py-12 text-gray-500 text-sm con texto No hay ventas registradas.
  - Tabla dentro de contenedor bg-white rounded-lg border border-gray-200 overflow-hidden con divide-y divide-gray-200
  - Columnas: Correlativo, Fecha (created_at->format(d/m/Y)), Usuario ($venta->user->name), Subtotal (S/ number_format), Total (S/ number_format), Acciones
  - Boton Ver detalle: bg-gray-100 text-gray-700 -> route(ventas.show, $venta)
  - Boton Ticket: bg-blue-100 text-blue-700 -> route(ventas.ticket, $venta)
  - Boton Eliminar: bg-red-100 text-red-700 con onclick confirm() y form DELETE -> route(ventas.destroy, $venta)
  - Paginacion: {{ $ventas->links() }}
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 15.1, 15.4, 15.5_

  - [ ]* 9.1 Test de propiedades — paginacion de 15 en 15 ordenada por fecha descendente
    - Crear N ventas aleatorias (N > 15), hacer GET /ventas
    - Verificar que la primera pagina contiene exactamente min(N, 15) filas de venta
    - Verificar que las ventas estan ordenadas por created_at descendente
    - **Property 9: Paginacion de 15 en 15 ordenada por fecha descendente**
    - **Validates: Requirements 6.1, 6.5**

  - [ ]* 9.2 Test de ejemplo — columnas del listado presentes en HTML
    - Crear al menos una venta, hacer GET /ventas
    - Verificar que el HTML contiene los encabezados: Correlativo, Fecha, Usuario, Subtotal, Total, Acciones
    - **Validates: Requirements 6.2**

  - [ ]* 9.3 Test de ejemplo — boton Nueva venta presente
    - Verificar que el HTML de GET /ventas contiene el enlace a ventas.create
    - **Validates: Requirements 6.3**

  - [ ]* 9.4 Test de ejemplo — estado vacio muestra mensaje correcto
    - Con la tabla vacia, verificar que GET /ventas muestra No hay ventas registradas.
    - **Validates: Requirements 6.4**

  - [ ]* 9.5 Test de ejemplo — botones Ver detalle, Ticket y Eliminar presentes por fila
    - Crear una venta, hacer GET /ventas
    - Verificar que el HTML contiene los enlaces a ventas.show y ventas.ticket y el form DELETE con confirm()
    - **Validates: Requirements 6.6, 6.7, 6.8**

- [x] 10. Crear vista ventas/create.blade.php
  - Crear resources/views/ventas/create.blade.php extendiendo layouts.app
  - Flash messages con los mismos estilos que el listado
  - Encabezado: Nueva venta + boton Volver -> route(ventas.index) con clases bg-gray-100 text-gray-700
  - Contenedor del formulario: bg-white rounded-lg border border-gray-200 p-6
  - Seccion busqueda: campo input id=buscar-producto, div id=resultados-busqueda para el dropdown de resultados Ajax
  - Tabla de detalle: table id=tabla-detalle con columnas Producto, Cantidad, Precio Unit., Subtotal, Eliminar
  - Seccion totales: campo subtotal (readonly, id=subtotal), toggle checkbox id=toggle-descuento con label Aplicar descuento por porcentaje, div id=campo-porcentaje (hidden por defecto) con input numerico id=porcentaje, campo total editable id=total
  - Campo observacion: textarea name=observacion opcional
  - Boton Registrar venta: bg-blue-600 text-white
  - Inputs hidden generados por JS: productos[i][producto_id], productos[i][cantidad], productos[i][precio_unitario], productos[i][subtotal], mas inputs name=subtotal y name=total
  - Errores de validacion con border-red-400 bg-red-50 en el campo y text-xs text-red-600 en el mensaje
  - JavaScript vanilla embebido en bloque script: estado items[], busqueda Ajax con debounce 300ms, funcion agregarProducto() que incrementa cantidad si el producto ya existe, renderTabla(), recalcularTotales() con logica de descuento por porcentaje, sincronizarHiddens(), validacion de porcentaje maximo 100
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9, 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 15.2, 15.3, 15.6_

  - [ ]* 10.1 Test de ejemplo — GET /ventas/create devuelve 200
    - Verificar que la ruta devuelve HTTP 200 para un usuario autenticado
    - **Validates: Requirements 7.1**

  - [ ]* 10.2 Test de propiedades — calculo de descuento por porcentaje
    - Para subtotales S y porcentajes P aleatorios entre 0 y 100, verificar que el total calculado es S * (1 - P / 100) redondeado a 2 decimales
    - Este test puede implementarse como unit test de la logica de calculo o como feature test del endpoint store
    - **Property 12: Calculo de descuento por porcentaje**
    - **Validates: Requirements 8.5, 8.6, 8.7**

- [x] 11. Crear vista ventas/show.blade.php
  - Crear resources/views/ventas/show.blade.php extendiendo layouts.app
  - Flash messages con los mismos estilos
  - Encabezado: correlativo de la venta + boton Volver al listado -> route(ventas.index) con clases bg-gray-100 text-gray-700
  - Tarjeta de datos: correlativo, fecha ($venta->created_at->format(d/m/Y)), usuario ($venta->user->name), observacion (si $venta->observacion no es nulo)
  - Tabla de productos: columnas Nombre del producto, Cantidad, Precio unitario (S/ number_format), Subtotal por linea (S/ number_format)
  - Seccion totales: mostrar subtotal; si $venta->total != $venta->subtotal mostrar Descuento aplicado: S/ {diferencia} y total final; si son iguales mostrar solo el total
  - Boton Generar ticket: enlace a route(ventas.ticket, $venta)
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7_

  - [ ]* 11.1 Test de propiedades — vista show muestra todos los datos de la venta
    - Crear una Venta con datos aleatorios (correlativo, usuario, detalles de productos, subtotal, total con descuento)
    - Hacer GET /ventas/{venta} y verificar que el HTML contiene el correlativo, nombre del usuario, cada producto con cantidad y precio, subtotal, total y el descuento aplicado
    - **Property 10: Las vistas show y ticket muestran todos los datos de la venta**
    - **Validates: Requirements 10.2, 10.3, 10.4, 10.5**

  - [ ]* 11.2 Test de ejemplo — GET /ventas/{venta} devuelve 200
    - Verificar que la ruta devuelve HTTP 200 para un usuario autenticado con una venta existente
    - **Validates: Requirements 10.1**

  - [ ]* 11.3 Test de ejemplo — botones Generar ticket y Volver al listado presentes
    - Verificar que el HTML de GET /ventas/{venta} contiene los enlaces a ventas.ticket y ventas.index
    - **Validates: Requirements 10.6, 10.7**

- [x] 12. Crear vista ventas/ticket.blade.php
  - Crear resources/views/ventas/ticket.blade.php extendiendo layouts.app
  - Ancho maximo 400px centrado (max-w-sm mx-auto)
  - Encabezado: nombre del negocio (config(app.name)), correlativo, fecha y hora ($venta->created_at->format(d/m/Y H:i)), nombre del usuario
  - Tabla de productos: columnas Nombre, Cant., P.Unit., Subtotal
  - Seccion totales: subtotal; si existe descuento mostrar monto del descuento y total final; si no, solo el total
  - Observacion (si $venta->observacion no es nulo)
  - Boton Imprimir: onclick=window.print()
  - Estilos @media print: ocultar nav, aside, boton imprimir; mostrar solo el contenido del ticket
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8_

  - [ ]* 12.1 Test de propiedades — vista ticket muestra todos los datos de la venta
    - Crear una Venta con datos aleatorios, hacer GET /ventas/{venta}/ticket
    - Verificar que el HTML contiene el correlativo, nombre del usuario, cada producto con cantidad y precio, subtotal, total y descuento si aplica
    - **Property 10: Las vistas show y ticket muestran todos los datos de la venta**
    - **Validates: Requirements 11.2, 11.3, 11.4, 11.5**

  - [ ]* 12.2 Test de ejemplo — GET /ventas/{venta}/ticket devuelve 200
    - Verificar que la ruta devuelve HTTP 200 para un usuario autenticado con una venta existente
    - **Validates: Requirements 11.1**

  - [ ]* 12.3 Test de ejemplo — boton Imprimir presente en ticket
    - Verificar que el HTML de GET /ventas/{venta}/ticket contiene el boton con onclick=window.print()
    - **Validates: Requirements 11.6**

- [x] 13. Checkpoint — verificar vistas
  - Asegurarse de que todos los tests de vistas pasan, preguntar al usuario si surgen dudas.

- [x] 14. Integrar en layouts/app.blade.php
  - Editar resources/views/layouts/app.blade.php
  - En el bloque @php: agregar $ventasActive = request()->routeIs(ventas.*);
  - En el sidebar de escritorio (fuera del bloque @if Administrador): agregar enlace directo a Ventas con icono de carrito, clases activas bg-gray-100 text-gray-900 font-semibold cuando $ventasActive es true, clases inactivas text-gray-600 hover:bg-gray-100 hover:text-gray-900
  - En el bottom nav movil (fuera del bloque @if Administrador): agregar enlace directo a Ventas con el mismo icono y texto Ventas, clase activa text-blue-600 cuando $ventasActive es true
  - El enlace de Ventas debe ser un enlace directo (sin grupo desplegable) ya que es un solo punto de entrada
  - _Requirements: 14.1, 14.2, 14.3, 14.4_

  - [ ]* 14.1 Test de propiedades — estado activo del sidebar segun ruta actual
    - Para rutas ventas.*, verificar que el enlace Ventas tiene las clases bg-gray-100 text-gray-900 font-semibold
    - Para rutas fuera de ventas.*, verificar que el enlace no tiene las clases activas
    - **Validates: Requirements 14.3, 14.4**

  - [ ]* 14.2 Test de ejemplo — enlace Ventas presente en sidebar y bottom nav
    - Verificar que el HTML del layout contiene el enlace a ventas.index tanto en el sidebar como en el bottom nav
    - **Validates: Requirements 14.1, 14.2**

- [x] 15. Checkpoint final — verificar integracion completa
  - Asegurarse de que todos los tests pasan, preguntar al usuario si surgen dudas.

---

## Notas

- Las tareas marcadas con * son opcionales y pueden omitirse para un MVP mas rapido.
- Cada tarea referencia los requisitos especificos para trazabilidad.
- Los checkpoints garantizan validacion incremental antes de continuar.
- Los tests de propiedades validan invariantes universales del sistema.
- Los tests de ejemplo validan comportamientos especificos y casos borde.
- Los tests de acceso deben usar actingAs(User::factory()->create()) (sin restriccion de rol).
- Todos los tests de base de datos deben usar el trait RefreshDatabase.
- La ruta Ajax ventas.buscar-productos DEBE registrarse antes del resource en routes/web.php.
- El campo inventario del Producto NO debe modificarse en ninguna operacion del modulo de ventas.
'@
Set-Content -Path ".kiro/specs/ventas-module/tasks.md" -Value $content -Encoding UTF8
Write-Host "done"