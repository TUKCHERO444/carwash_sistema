# Plan de implementación: producto-crud

## Descripción general

Implementación del módulo CRUD completo para la entidad **Producto** en Laravel 11 + Blade + Tailwind CSS. El módulo añade dos nuevos campos a la tabla existente (`activo`, `foto`), un controlador resource, tres vistas Blade y la integración en el sidebar/bottom nav del layout, siguiendo exactamente los patrones del módulo `trabajadores`.

---

## Tareas

- [x] 1. Migración de alteración — añadir `activo` y `foto` a la tabla `productos`
  - Crear el archivo `database/migrations/YYYY_MM_DD_HHMMSS_add_activo_foto_to_productos_table.php`
  - Método `up()`: `Schema::table('productos', ...)` añadiendo `tinyInteger('activo')->default(1)->after('inventario')` y `string('foto')->nullable()->after('activo')`
  - Método `down()`: `$table->dropColumn(['activo', 'foto'])`
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [ ]* 1.1 Test de smoke — columnas existen tras migración
    - Verificar que las columnas `activo` y `foto` existen en la tabla `productos` después de ejecutar la migración
    - Verificar que los registros previos no se modifican (count y valores de columnas preexistentes intactos)
    - **Property 1: Preservación de datos en migración**
    - **Validates: Requirements 1.3**

- [x] 2. Actualizar modelo `Producto`
  - Editar `app/Models/Producto.php`
  - Añadir `'activo'` y `'foto'` al array `$fillable`
  - Añadir `'activo' => 'boolean'` al array `$casts` (mantener los casts existentes)
  - No modificar las relaciones `cambioAceites()` ni `ventas()`
  - _Requirements: 1.1, 1.2_

- [x] 3. Actualizar `ProductoSeeder`
  - Editar `database/seeders/ProductoSeeder.php`
  - Añadir `'activo' => 1` y `'foto' => null` a cada llamada `Producto::create([...])`
  - _Requirements: 2.1, 2.2, 2.3_

  - [ ]* 3.1 Test de smoke — seeder no lanza excepciones
    - Ejecutar el seeder en un entorno de test y verificar que no lanza excepciones de integridad de datos
    - **Property 2: Seeder asigna activo=1 y foto=null en todos los registros**
    - **Validates: Requirements 2.1, 2.2, 2.3**

- [x] 4. Crear `ProductoController`
  - Crear `app/Http/Controllers/ProductoController.php` siguiendo el patrón de `TrabajadorController`
  - Importar `Storage` de `Illuminate\Support\Facades\Storage`

  - [x] 4.1 Implementar método `index()`
    - `$productos = Producto::paginate(15)` y retornar `view('productos.index', compact('productos'))`
    - _Requirements: 3.1_

  - [x] 4.2 Implementar método `create()`
    - Retornar `view('productos.create')`
    - _Requirements: 4.1_

  - [x] 4.3 Implementar método `store(Request $request)`
    - Validar: `nombre` (required|string|max:150), `precio_compra` (required|numeric|gt:0), `precio_venta` (required|numeric|gt:0), `stock` (required|integer|min:0), `inventario` (required|integer|min:0), `activo` (nullable|boolean), `foto` (nullable|image|mimes:jpg,jpeg,png,webp|max:2048)
    - Lógica de imagen: si `$request->hasFile('foto')` → `$request->file('foto')->store('images/productos', 'public')`; si no → `null`
    - Usar `$request->boolean('activo', true)` para el campo `activo`
    - Crear el registro con `Producto::create([...])` y redirigir a `productos.index` con flash `'Producto creado correctamente.'`
    - _Requirements: 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 4.10, 4.11, 4.13_

  - [x] 4.4 Implementar método `edit(Producto $producto)`
    - Retornar `view('productos.edit', compact('producto'))` usando Route Model Binding
    - _Requirements: 5.1_

  - [x] 4.5 Implementar método `update(Request $request, Producto $producto)`
    - Aplicar las mismas reglas de validación que en `store`
    - Si hay imagen nueva: eliminar la anterior con `Storage::disk('public')->delete($producto->foto)` (si existe) y almacenar la nueva
    - Si no hay imagen nueva: hacer `unset($data['foto'])` para conservar la ruta anterior
    - Usar `$request->boolean('activo')` para el campo `activo`
    - Actualizar con `$producto->update($data)` y redirigir a `productos.index` con flash `'Producto actualizado correctamente.'`
    - _Requirements: 5.2, 5.3, 5.4, 5.5, 5.9_

  - [x] 4.6 Implementar método `destroy(Producto $producto)`
    - Si `$producto->foto` no es nulo: `Storage::disk('public')->delete($producto->foto)`
    - Llamar a `$producto->delete()`
    - Redirigir a `productos.index` con flash `'Producto eliminado correctamente.'`
    - _Requirements: 6.1, 6.2, 6.3_

  - [ ]* 4.7 Test de propiedades — creación persiste datos válidos y redirige con flash
    - Generar conjuntos de datos válidos aleatorios (nombre, precios, stock, inventario) con `fake()`
    - Verificar que el registro existe en BD con los valores enviados y que la respuesta redirige a `productos.index` con el flash de éxito
    - **Property 6: Creación persiste datos válidos y redirige con flash**
    - **Validates: Requirements 4.2**

  - [ ]* 4.8 Test de propiedades — validación rechaza datos inválidos
    - Generar peticiones con campos inválidos: nombre vacío, `precio_compra` ≤ 0, `stock` negativo, imagen de tipo no permitido, etc.
    - Verificar que la respuesta redirige al formulario con errores y conserva los valores introducidos
    - **Property 7: Validación rechaza datos inválidos en creación y edición**
    - **Validates: Requirements 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 4.13, 5.3, 5.9**

  - [ ]* 4.9 Test de propiedades — almacenamiento de imagen en creación
    - Usar `Storage::fake('public')` y subir imágenes válidas aleatorias en `POST /productos`
    - Verificar que el archivo existe en `images/productos/` y que la ruta relativa está guardada en el campo `foto` del registro
    - **Property 8: Almacenamiento de imagen en creación**
    - **Validates: Requirements 4.10**

  - [ ]* 4.10 Test de propiedades — actualización persiste datos válidos y redirige con flash
    - Crear un producto existente, enviar datos válidos aleatorios a `PUT /productos/{producto}`
    - Verificar que el registro se actualiza en BD y que la respuesta redirige con el flash de éxito
    - **Property 10: Actualización persiste datos válidos y redirige con flash**
    - **Validates: Requirements 5.2**

  - [ ]* 4.11 Test de propiedades — conservación de imagen anterior cuando no se sube nueva
    - Usar `Storage::fake('public')`, crear producto con foto, enviar `PUT` sin imagen nueva
    - Verificar que el campo `foto` del registro permanece con el mismo valor
    - **Property 11: Conservación de imagen anterior cuando no se sube nueva**
    - **Validates: Requirements 5.4**

  - [ ]* 4.12 Test de propiedades — reemplazo de imagen en edición
    - Usar `Storage::fake('public')`, crear producto con foto, enviar `PUT` con imagen nueva válida
    - Verificar que el nuevo archivo existe en disco, el campo `foto` se actualiza y el archivo anterior se elimina
    - **Property 12: Reemplazo de imagen en edición**
    - **Validates: Requirements 5.5**

  - [ ]* 4.13 Test de propiedades — eliminación de registro y archivo de imagen
    - Usar `Storage::fake('public')`, crear producto con foto, enviar `DELETE /productos/{producto}`
    - Verificar que el archivo se elimina del disco y el registro desaparece de la BD
    - **Property 13: Eliminación de registro y archivo de imagen**
    - **Validates: Requirements 6.1, 6.2**

  - [ ]* 4.14 Test de ejemplo — eliminación sin foto no lanza error
    - Crear producto con `foto = null`, enviar `DELETE /productos/{producto}`
    - Verificar que la operación se completa sin excepciones y el registro se elimina
    - **Validates: Requirements 6.3**

- [ ] 5. Checkpoint — verificar controlador y modelo
  - Asegurarse de que todos los tests del controlador pasan, preguntar al usuario si surgen dudas.

- [x] 6. Registrar rutas en `routes/web.php`
  - Añadir `use App\Http\Controllers\ProductoController;` en los imports
  - Dentro del grupo `middleware(['auth', 'role:Administrador'])` existente, añadir:
    `Route::resource('productos', ProductoController::class)->except(['show']);`
  - _Requirements: 7.1_

  - [ ]* 6.1 Test de propiedades — control de acceso (autenticación)
    - Para cada ruta del módulo (`GET /productos`, `GET /productos/create`, `POST /productos`, `GET /productos/{id}/edit`, `PUT /productos/{id}`, `DELETE /productos/{id}`), enviar petición sin sesión autenticada
    - Verificar que la respuesta redirige a `/login`
    - **Property 14: Control de acceso — autenticación**
    - **Validates: Requirements 7.2**

  - [ ]* 6.2 Test de propiedades — control de acceso (autorización por rol)
    - Para cada ruta del módulo, enviar petición con usuario autenticado sin rol `Administrador`
    - Verificar que la respuesta devuelve 403
    - **Property 15: Control de acceso — autorización por rol**
    - **Validates: Requirements 7.3**

  - [ ]* 6.3 Test de smoke — rutas del módulo registradas correctamente
    - Verificar que las seis rutas resource (sin `show`) están registradas en el router de Laravel
    - **Validates: Requirements 7.1**

- [x] 7. Crear vista `productos/index.blade.php`
  - Crear `resources/views/productos/index.blade.php` extendiendo `layouts.app`
  - Flash messages de éxito/error con las clases `bg-green-100 text-green-800 border border-green-200` / `bg-red-100 text-red-800 border border-red-200`
  - Encabezado con título "Productos" y botón "Crear producto" → `route('productos.create')` con clases `bg-blue-600 text-white`
  - Estado vacío: `<div class="text-center py-12 text-gray-500 text-sm">No hay productos registrados.</div>`
  - Tabla con `divide-y divide-gray-200` dentro de contenedor `bg-white rounded-lg border border-gray-200 overflow-hidden`
  - Columnas: Foto, Nombre, Precio Compra, Precio Venta, Stock, Activo, Acciones
  - Miniatura: `<img src="{{ asset('storage/' . $producto->foto) }}" class="w-10 h-10 object-cover rounded">` cuando `$producto->foto` no es nulo; placeholder SVG cuando es nulo
  - Badge activo: `bg-green-100 text-green-800` con texto "Activo" / `bg-red-100 text-red-800` con texto "Inactivo"
  - Botón Editar: `bg-gray-100 text-gray-700` → `route('productos.edit', $producto)`
  - Botón Eliminar: `bg-red-100 text-red-700` con `onclick="return confirm('¿Estás seguro?')"` y form `DELETE`
  - Paginación: `{{ $productos->links() }}`
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 6.4, 9.1, 9.4, 9.5_

  - [ ]* 7.1 Test de propiedades — paginación de 15 en 15
    - Crear N productos aleatorios (N > 15), hacer `GET /productos`
    - Verificar que la primera página contiene exactamente `min(N, 15)` filas de producto
    - **Property 3: Paginación de 15 en 15**
    - **Validates: Requirements 3.1**

  - [ ]* 7.2 Test de propiedades — miniatura o placeholder según campo foto
    - Crear productos con `foto` no nulo y con `foto = null`, hacer `GET /productos`
    - Verificar que los productos con foto renderizan `<img>` con la ruta correcta y los sin foto renderizan el placeholder
    - **Property 4: Renderizado de miniatura o placeholder según campo foto**
    - **Validates: Requirements 3.3**

  - [ ]* 7.3 Test de propiedades — badge refleja estado activo del producto
    - Crear productos con `activo = 1` y `activo = 0`, hacer `GET /productos`
    - Verificar que los activos muestran badge verde "Activo" y los inactivos badge rojo "Inactivo"
    - **Property 5: Badge refleja estado activo del producto**
    - **Validates: Requirements 3.4**

  - [ ]* 7.4 Test de ejemplo — columnas del listado presentes en HTML
    - Verificar que el HTML de `GET /productos` contiene los encabezados: Foto, Nombre, Precio Compra, Precio Venta, Stock, Activo, Acciones
    - **Validates: Requirements 3.2**

  - [ ]* 7.5 Test de ejemplo — botón "Crear producto" presente
    - Verificar que el HTML de `GET /productos` contiene el enlace a `productos.create`
    - **Validates: Requirements 3.5**

  - [ ]* 7.6 Test de ejemplo — estado vacío muestra mensaje correcto
    - Con la tabla vacía, verificar que `GET /productos` muestra "No hay productos registrados."
    - **Validates: Requirements 3.6**

  - [ ]* 7.7 Test de ejemplo — botón eliminar con confirm() presente
    - Verificar que el HTML del listado contiene el atributo `onclick` con `confirm` en el botón de eliminar
    - **Validates: Requirements 6.4**

- [x] 8. Crear vista `productos/create.blade.php`
  - Crear `resources/views/productos/create.blade.php` extendiendo `layouts.app`
  - Flash messages con los mismos estilos que el listado
  - Encabezado "Crear producto" con botón "Volver" → `route('productos.index')` con clases `bg-gray-100 text-gray-700`
  - `<form action="{{ route('productos.store') }}" method="POST" enctype="multipart/form-data" novalidate>`
  - Campos: `nombre` (text), `precio_compra` (number, step="0.01"), `precio_venta` (number, step="0.01"), `stock` (number), `inventario` (number)
  - Campo `activo`: checkbox con `name="activo"` `value="1"`, marcado por defecto (`checked`) usando `old('activo', '1') == '1'`
  - Campo `foto`: `<input type="file" name="foto" accept="image/jpg,image/jpeg,image/png,image/webp">`
  - Errores inline: clase `border-red-400 bg-red-50` en el input afectado y `<p class="mt-1 text-xs text-red-600">` para el mensaje
  - Botón "Guardar" (`bg-blue-600 text-white`) y enlace "Cancelar" → `route('productos.index')`
  - Contenedor del formulario: `bg-white rounded-lg border border-gray-200 p-6 max-w-lg`
  - _Requirements: 4.1, 4.12, 4.13, 4.14, 9.2, 9.3, 9.5, 9.6_

  - [ ]* 8.1 Test de ejemplo — GET /productos/create devuelve 200
    - Verificar que la ruta devuelve HTTP 200 para un usuario Administrador autenticado
    - **Validates: Requirements 4.1**

  - [ ]* 8.2 Test de ejemplo — checkbox activo marcado por defecto en create
    - Verificar que el HTML del formulario de creación contiene el checkbox `activo` con el atributo `checked`
    - **Validates: Requirements 4.12**

  - [ ]* 8.3 Test de ejemplo — form tiene enctype y novalidate
    - Verificar que el formulario tiene `enctype="multipart/form-data"` y el atributo `novalidate`
    - **Validates: Requirements 4.14**

- [x] 9. Crear vista `productos/edit.blade.php`
  - Crear `resources/views/productos/edit.blade.php` extendiendo `layouts.app`
  - Flash messages con los mismos estilos
  - Encabezado "Editar producto" con botón "Volver" → `route('productos.index')` con clases `bg-gray-100 text-gray-700`
  - `<form action="{{ route('productos.update', $producto) }}" method="POST" enctype="multipart/form-data" novalidate>` con `@method('PUT')`
  - Campos precargados con `old('campo', $producto->campo)` para todos los campos de texto/número
  - Campo `activo`: checkbox precargado con `old('activo', $producto->activo ? '1' : '') == '1'`
  - Sección de imagen:
    - Si `$producto->foto`: mostrar `<img src="{{ asset('storage/' . $producto->foto) }}">` con leyenda "Imagen actual"
    - Si no: mostrar placeholder SVG
    - Bloque "Nueva imagen" (`id="bloque-nueva"`) oculto por defecto con clase `hidden`, contiene `<img id="preview-nueva">`
  - Script JavaScript con FileReader API (vanilla):
    ```javascript
    document.getElementById('foto').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(event) {
            document.getElementById('preview-nueva').src = event.target.result;
            document.getElementById('bloque-nueva').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });
    ```
  - Errores inline con los mismos estilos que en create
  - Botón "Guardar cambios" (`bg-blue-600 text-white`) y enlace "Cancelar" → `route('productos.index')`
  - _Requirements: 5.1, 5.6, 5.7, 5.8, 5.9, 9.2, 9.3, 9.5, 9.6_

  - [ ]* 9.1 Test de propiedades — formulario de edición precarga datos del producto
    - Para un producto existente aleatorio, hacer `GET /productos/{producto}/edit`
    - Verificar que el HTML contiene los valores actuales del producto en cada campo del formulario
    - **Property 9: Formulario de edición precarga datos del producto**
    - **Validates: Requirements 5.1**

  - [ ]* 9.2 Test de ejemplo — script JS de preview presente en edit
    - Verificar que el HTML de `GET /productos/{producto}/edit` contiene el script con `FileReader` y los IDs `preview-nueva` y `bloque-nueva`
    - **Validates: Requirements 5.7**

  - [ ]* 9.3 Test de ejemplo — placeholder cuando foto es null en edit
    - Crear producto con `foto = null`, hacer `GET /productos/{producto}/edit`
    - Verificar que el HTML muestra el placeholder en lugar de un `<img>` con ruta de imagen
    - **Validates: Requirements 5.8**

- [x] 10. Actualizar `layouts/app.blade.php` — grupo "Gestión de productos"
  - Editar `resources/views/layouts/app.blade.php`
  - Añadir `$productManagementActive = request()->routeIs('productos.*');` en el bloque `@php`
  - En el sidebar de escritorio: añadir un nuevo bloque `<div data-dropdown="product-management">` con la misma estructura HTML/Alpine que el grupo "Gestión de usuarios", con el enlace "Productos" → `route('productos.index')` dentro del menú desplegable
  - En el bottom nav móvil: añadir un nuevo bloque `<div data-dropdown="product-management-mobile">` con la misma estructura que el grupo "Gestión de usuarios" móvil, con el enlace "Productos" dentro del menú desplegable
  - El grupo "Gestión de usuarios" existente NO debe modificarse ni incluir el enlace "Productos"
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

  - [ ]* 10.1 Test de propiedades — estado activo del sidebar según ruta actual
    - Para rutas `productos.*`, verificar que el grupo "Gestión de productos" está expandido y el enlace "Productos" tiene las clases `bg-gray-100 text-gray-900 font-semibold`
    - Para rutas fuera de `productos.*`, verificar que el grupo está colapsado
    - **Property 16: Estado activo del sidebar según ruta actual**
    - **Validates: Requirements 8.3, 8.4**

  - [ ]* 10.2 Test de ejemplo — grupo "Gestión de productos" en sidebar y bottom nav
    - Verificar que el HTML del layout contiene el grupo "Gestión de productos" tanto en el sidebar como en el bottom nav
    - **Validates: Requirements 8.1, 8.2**

  - [ ]* 10.3 Test de ejemplo — grupo "Gestión de usuarios" no contiene "Productos"
    - Verificar que el bloque `data-dropdown="user-management"` no contiene el enlace a `productos.index`
    - **Validates: Requirements 8.5**

- [x] 11. Checkpoint final — verificar integración completa
  - Asegurarse de que todos los tests pasan, preguntar al usuario si surgen dudas.

---

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido.
- Cada tarea referencia los requisitos específicos para trazabilidad.
- Los checkpoints garantizan validación incremental antes de continuar.
- Los tests de propiedades validan invariantes universales del sistema.
- Los tests de ejemplo validan comportamientos específicos y casos borde.
- Todos los tests que involucran archivos deben usar `Storage::fake('public')`.
- Los tests de acceso deben usar `$user->assignRole('Administrador')` (Spatie) y `actingAs($user)`.
