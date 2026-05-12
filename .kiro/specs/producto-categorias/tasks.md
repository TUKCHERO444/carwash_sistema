# Plan de Implementación: Módulo de Categorías de Productos

## Visión General

Implementación incremental del módulo de categorías en Laravel. Se parte de las migraciones y el modelo, se construye el CRUD de categorías, se modifica el módulo de productos para gestionar `categoria_id` con transacciones atómicas, se actualizan las vistas y la navegación, y finalmente se añaden el seeder y los tests.

## Tareas

- [x] 1. Crear migraciones de base de datos
  - Crear migración `create_categorias_table` con columnas: `id`, `nombre` (VARCHAR 150, UNIQUE), `descripcion` (TEXT nullable), `contador_productos` (INT UNSIGNED default 0), `timestamps`
  - Crear migración `add_categoria_id_to_productos_table` que añade `categoria_id` (BIGINT UNSIGNED nullable) después de `inventario`, con FK a `categorias.id` ON DELETE SET NULL
  - Verificar que el orden de migraciones es correcto (categorias debe existir antes de la FK en productos)
  - _Requisitos: 1.1, 5.2, 5.3_

- [x] 2. Crear el modelo Categoria y modificar el modelo Producto
  - [x] 2.1 Crear `app/Models/Categoria.php`
    - Definir `$fillable = ['nombre', 'descripcion', 'contador_productos']`
    - Definir `$casts = ['contador_productos' => 'integer']`
    - Añadir relación `productos(): HasMany` → `hasMany(Producto::class)`
    - _Requisitos: 1.1, 2.1, 6.1_

  - [x] 2.2 Modificar `app/Models/Producto.php`
    - Añadir `'categoria_id'` a `$fillable`
    - Añadir `'categoria_id' => 'integer'` a `$casts`
    - Añadir relación `categoria(): BelongsTo` → `belongsTo(Categoria::class)`
    - _Requisitos: 5.2, 5.3_

- [x] 3. Implementar CategoriaController con CRUD completo
  - [x] 3.1 Crear `app/Http/Controllers/CategoriaController.php`
    - Implementar `index()`: obtener todas las categorías y retornar vista `categorias.index`
    - Implementar `create()`: retornar vista `categorias.create`
    - Implementar `store(Request $request)`: validar (`nombre` required|string|max:150|unique:categorias,nombre, `descripcion` nullable|string|max:500), crear categoría con `contador_productos = 0`, redirigir a `categorias.index` con mensaje de éxito
    - Implementar `edit(Categoria $categoria)`: retornar vista `categorias.edit` con la categoría
    - Implementar `update(Request $request, Categoria $categoria)`: validar (`nombre` required|string|max:150|unique:categorias,nombre,{id}, `descripcion` nullable|string|max:500), actualizar, redirigir con mensaje de éxito
    - Implementar `destroy(Categoria $categoria)`: rechazar si `contador_productos > 0` con mensaje de error; si es 0, eliminar y redirigir con mensaje de éxito
    - _Requisitos: 1.2, 1.3, 1.4, 1.5, 2.1, 3.1, 3.2, 3.3, 4.1, 4.2_

  - [ ]* 3.2 Escribir tests unitarios para CategoriaController
    - Crear `tests/Feature/CategoriaControllerTest.php`
    - Test: crear categoría con datos válidos → redirige con mensaje de éxito y `contador_productos = 0`
    - Test: crear categoría con nombre vacío → error de validación en campo `nombre`
    - Test: crear categoría con nombre > 150 caracteres → error de validación
    - Test: crear categoría con nombre duplicado → error de validación con mensaje de nombre en uso
    - Test: editar categoría con nombre de otra categoría → error de validación
    - Test: editar categoría con el mismo nombre (misma categoría) → permitido, actualiza correctamente
    - Test: eliminar categoría sin productos → elimina y redirige con éxito
    - Test: eliminar categoría con productos → rechaza con mensaje de error
    - _Requisitos: 1.2, 1.3, 1.4, 3.1, 3.2, 3.3, 4.1, 4.2_

  - [ ]* 3.3 Escribir test de propiedad para validación de nombre (Propiedad 4)
    - Instalar `eris/eris` con `composer require --dev giorgiosironi/eris` si no está instalado
    - Crear `tests/Feature/CategoriaValidacionPropertyTest.php`
    - **Propiedad 4: La validación de nombre rechaza entradas inválidas y duplicadas**
    - Generar strings de longitud 0 → verificar que la validación rechaza (nombre vacío)
    - Generar strings de longitud > 150 → verificar que la validación rechaza
    - Generar strings de longitud 1–150 → verificar que la validación acepta
    - Generar nombre de categoría existente → verificar que la validación rechaza duplicado
    - Ejecutar con al menos 100 iteraciones (`->withMaxSize(100)`)
    - Anotar: `// Feature: producto-categorias, Property 4: La validación de nombre rechaza entradas inválidas y duplicadas`
    - **Valida: Requisitos 1.3, 1.4, 3.2, 3.3**

- [x] 4. Modificar ProductoController para gestionar categoria_id con transacciones
  - [x] 4.1 Modificar `store()` en `ProductoController`
    - Añadir `'categoria_id' => ['nullable', 'integer', 'exists:categorias,id']` a las reglas de validación
    - Envolver la creación del producto en `DB::transaction()`: crear producto con `categoria_id`, si `categoria_id` no es null incrementar `contador_productos` de la categoría con `increment('contador_productos')`
    - _Requisitos: 5.2, 5.3, 5.4, 6.3_

  - [x] 4.2 Modificar `update()` en `ProductoController`
    - Añadir `'categoria_id' => ['nullable', 'integer', 'exists:categorias,id']` a las reglas de validación
    - Envolver la actualización en `DB::transaction()`:
      - Si la categoría anterior (`$producto->categoria_id`) es distinta a la nueva: decrementar la anterior (si no era null) e incrementar la nueva (si no es null)
      - Si la categoría no cambia: no modificar contadores
      - Actualizar el producto con los nuevos datos incluyendo `categoria_id`
    - _Requisitos: 5.2, 5.3, 5.5, 6.3_

  - [x] 4.3 Modificar `destroy()` en `ProductoController`
    - Envolver la eliminación en `DB::transaction()`: si el producto tiene `categoria_id`, decrementar `contador_productos` de esa categoría antes de eliminar el producto
    - _Requisitos: 5.6, 6.3_

  - [ ]* 4.4 Escribir tests de integración para la lógica del contador en ProductoController
    - Crear `tests/Feature/ProductoCategoriaTest.php`
    - Test: asignar categoría al crear producto → `contador_productos` incrementa en 1
    - Test: crear producto sin categoría → contadores no cambian
    - Test: quitar categoría al editar producto → `contador_productos` decrementa en 1
    - Test: cambiar categoría al editar producto → categoría anterior decrementa, nueva incrementa
    - Test: editar producto sin cambiar categoría → contadores no cambian
    - Test: eliminar producto con categoría → `contador_productos` decrementa en 1
    - Test: eliminar producto sin categoría → contadores no cambian
    - _Requisitos: 5.4, 5.5, 5.6, 6.1, 6.2, 6.3_

  - [ ]* 4.5 Escribir test de propiedad para el contador (Propiedad 1)
    - Crear `tests/Feature/ContadorProductosPropertyTest.php`
    - **Propiedad 1: El contador siempre refleja el conteo real de productos**
    - Generar secuencias aleatorias de operaciones (crear/editar/eliminar productos con categorías)
    - Tras cada operación verificar: `$categoria->contador_productos === Producto::where('categoria_id', $categoria->id)->count()`
    - Ejecutar con al menos 100 iteraciones
    - Anotar: `// Feature: producto-categorias, Property 1: El contador siempre refleja el conteo real de productos`
    - **Valida: Requisitos 1.5, 5.4, 5.6, 6.1, 6.3**

  - [ ]* 4.6 Escribir test de propiedad para contador no negativo (Propiedad 2)
    - En el mismo archivo `ContadorProductosPropertyTest.php` o en uno separado
    - **Propiedad 2: El contador nunca es negativo**
    - Tras cualquier secuencia de operaciones generadas aleatoriamente, verificar: `$categoria->fresh()->contador_productos >= 0` para todas las categorías
    - Ejecutar con al menos 100 iteraciones
    - Anotar: `// Feature: producto-categorias, Property 2: El contador nunca es negativo`
    - **Valida: Requisito 6.2**

  - [ ]* 4.7 Escribir test de propiedad para invariante de suma global (Propiedad 3)
    - **Propiedad 3: La suma global de contadores es invariante ante reasignaciones**
    - Generar pares de categorías y productos, reasignar aleatoriamente entre categorías
    - Verificar que `Categoria::sum('contador_productos')` es igual antes y después de la reasignación
    - Ejecutar con al menos 100 iteraciones
    - Anotar: `// Feature: producto-categorias, Property 3: La suma global de contadores es invariante ante reasignaciones`
    - **Valida: Requisito 5.5**

- [x] 5. Checkpoint — Verificar lógica de negocio
  - Asegurarse de que todos los tests pasan hasta este punto, preguntar al usuario si hay dudas antes de continuar con las vistas.

- [x] 6. Crear vistas Blade para categorías
  - [x] 6.1 Crear `resources/views/categorias/index.blade.php`
    - Extender `layouts.app`
    - Mostrar flash messages de éxito y error
    - Tabla con columnas: Nombre, Descripción, Productos (contador), Acciones (Editar, Eliminar)
    - Botón "Crear categoría" en el header
    - Estado vacío si no hay categorías
    - Formulario de eliminación con confirmación (`confirm()`) y método DELETE
    - _Requisitos: 2.1, 2.2, 4.1, 4.2_

  - [x] 6.2 Crear `resources/views/categorias/create.blade.php`
    - Extender `layouts.app`
    - Formulario POST a `categorias.store`
    - Campo `nombre` (text, required, max 150) con visualización de errores de validación
    - Campo `descripcion` (textarea, opcional, max 500) con visualización de errores de validación
    - Botones: "Guardar" y "Cancelar" (vuelve a `categorias.index`)
    - _Requisitos: 1.1, 1.2, 1.3, 1.4_

  - [x] 6.3 Crear `resources/views/categorias/edit.blade.php`
    - Extender `layouts.app`
    - Formulario PUT a `categorias.update` con `@method('PUT')`
    - Campos pre-rellenados con los valores actuales de la categoría
    - Campo `nombre` con visualización de errores de validación
    - Campo `descripcion` con visualización de errores de validación
    - Botones: "Actualizar" y "Cancelar"
    - _Requisitos: 3.1, 3.2, 3.3_

- [x] 7. Modificar vistas de productos para incluir selector de categoría
  - [x] 7.1 Modificar `resources/views/productos/create.blade.php`
    - Añadir `<select name="categoria_id">` con opción vacía "Sin categoría" y las opciones de `$categorias` (id → nombre)
    - El select debe mostrar errores de validación si `categoria_id` es inválido
    - _Requisitos: 5.1, 5.2, 5.3_

  - [x] 7.2 Modificar `resources/views/productos/edit.blade.php`
    - Añadir `<select name="categoria_id">` con opción vacía "Sin categoría" y las opciones de `$categorias`
    - Pre-seleccionar la categoría actual del producto (`selected` cuando `$producto->categoria_id == $categoria->id`)
    - _Requisitos: 5.1, 5.2, 5.3_

  - [x] 7.3 Modificar `resources/views/productos/index.blade.php`
    - Añadir columna "Categoría" en la tabla, mostrando `$producto->categoria->nombre ?? '—'`
    - Actualizar el `index()` de `ProductoController` para hacer eager loading: `Producto::with('categoria')->paginate(15)`
    - _Requisitos: 2.1_

- [x] 8. Registrar rutas y actualizar navegación
  - [x] 8.1 Modificar `routes/web.php`
    - Añadir `use App\Http\Controllers\CategoriaController;` en los imports
    - Registrar `Route::resource('categorias', CategoriaController::class)->except(['show']);` dentro del grupo `middleware(['auth', 'role:Administrador'])`
    - _Requisitos: 1.2, 2.1, 3.1, 4.1_

  - [x] 8.2 Modificar `resources/views/layouts/app.blade.php`
    - Añadir `$categoriasActive = request()->routeIs('categorias.*');` en el bloque `@php` inicial
    - Actualizar `$productManagementActive` para incluir `categorias.*`: `request()->routeIs('productos.*', 'categorias.*')`
    - Añadir enlace "Categorías" dentro del dropdown "Gestión de productos" en el sidebar (desktop), apuntando a `route('categorias.index')`
    - Añadir enlace "Categorías" dentro del dropdown "Gestión de productos" en la navegación móvil
    - _Requisitos: 2.1_

- [x] 9. Crear CategoriaSeeder y actualizar DatabaseSeeder
  - [x] 9.1 Crear `database/seeders/CategoriaSeeder.php`
    - Crear las 8 categorías: "Aceites y Lubricantes", "Filtros", "Frenos", "Suspensión", "Neumáticos", "Batería y Eléctrico", "Correas y Cadenas", "Líquidos"
    - Para cada producto existente, asignar `categoria_id` según su nombre usando un mapa de palabras clave (ej. productos con "Aceite" o "Lubricante" → "Aceites y Lubricantes", "Filtro" → "Filtros", etc.)
    - Actualizar `contador_productos` de cada categoría con `Producto::where('categoria_id', $categoria->id)->count()` tras las asignaciones
    - Productos sin coincidencia deben quedar con `categoria_id = null`
    - _Requisitos: 7.1, 7.2, 7.3, 7.4_

  - [x] 9.2 Modificar `database/seeders/DatabaseSeeder.php`
    - Añadir `CategoriaSeeder::class` en el array de `$this->call()`, posicionado antes de `ProductoSeeder::class`
    - _Requisitos: 7.1_

- [x] 10. Checkpoint final — Verificar integración completa
  - Asegurarse de que todos los tests pasan.
  - Verificar que `php artisan migrate` se ejecuta sin errores.
  - Verificar que `php artisan db:seed --class=CategoriaSeeder` crea las 8 categorías y los contadores son correctos.
  - Preguntar al usuario si hay dudas antes de dar por finalizada la implementación.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia los requisitos específicos para trazabilidad
- Los tests de propiedades requieren `eris/eris` (`composer require --dev giorgiosironi/eris`)
- Los checkpoints garantizan validación incremental antes de avanzar a la siguiente capa
- La Propiedad 1 es la más crítica: el contador debe ser siempre igual al COUNT real de la BD
- Todas las operaciones que modifican `categoria_id` deben estar dentro de `DB::transaction()`
