# Plan de Implementación: servicios-crud

## Visión General

Implementar el CRUD de Servicios siguiendo el patrón MVC existente del proyecto (Laravel + Blade + Tailwind CSS). El módulo incluye controlador resource, rutas protegidas por rol, tres vistas Blade, modificación del layout para agregar "Servicios" en la sección "Gestión Administrativa", y tests de ejemplo y de propiedades. No se requieren migraciones: la tabla `servicios` y el modelo `Servicio` ya existen.

## Tareas

- [x] 1. Implementar `ServicioController`
  - [x] 1.1 Crear `app/Http/Controllers/ServicioController.php` con los 5 métodos resource
    - `index()`: `Servicio::paginate(15)` → vista `servicios.index`
    - `create()`: retorna vista `servicios.create`
    - `store(Request $request)`: validar, `Servicio::create()`, redirect con flash `'Servicio creado correctamente.'`
    - `edit(Servicio $servicio)`: retorna vista `servicios.edit` con model binding
    - `update(Request $request, Servicio $servicio)`: validar, `$servicio->update()`, redirect con flash `'Servicio actualizado correctamente.'`
    - `destroy(Servicio $servicio)`: verificar `$servicio->ingresos()->exists()`, eliminar o redirigir con flash de error `'No se puede eliminar el servicio porque tiene ingresos asociados.'`
    - Reglas de validación compartidas: `nombre` required|string|max:100, `precio` required|numeric|gt:0
    - _Requisitos: 1.1, 2.3, 2.4, 2.5, 3.2, 3.3, 3.4, 4.1, 4.2_

  - [ ]* 1.2 Escribir property test — Propiedad 1: creación persiste datos válidos
    - **Propiedad 1: Creación persiste datos válidos**
    - Crear `tests/Feature/ServicioPropertiesTest.php` con el primer test
    - Generar ≥50 combinaciones: nombres de 1–100 chars (alfanumérico, con espacios, con acentos, con caracteres especiales), precios de 0.01 a 999999.99 (enteros, decimales, grandes, pequeños)
    - Verificar: `Servicio::count()` aumenta en 1, el registro tiene los valores enviados, response redirige a `servicios.index` con flash `success`
    - **Valida: Requisitos 2.3**

  - [ ]* 1.3 Escribir property test — Propiedad 2: validación rechaza entradas inválidas
    - **Propiedad 2: Validación rechaza entradas inválidas sin persistir**
    - Agregar al archivo `tests/Feature/ServicioPropertiesTest.php`
    - Generar entradas inválidas: nombre vacío, nombre solo whitespace, nombre >100 chars, precio 0, precio negativo, precio no numérico, precio null
    - Verificar: `Servicio::count()` no cambia, response contiene errores de validación
    - Mínimo 50 combinaciones (25 nombre inválido + 25 precio inválido)
    - **Valida: Requisitos 2.4, 2.5, 3.3, 3.4**

  - [ ]* 1.4 Escribir property test — Propiedad 3: edición actualiza y preserva identidad
    - **Propiedad 3: Edición actualiza datos y preserva identidad del registro**
    - Agregar al archivo `tests/Feature/ServicioPropertiesTest.php`
    - Generar servicios existentes y nuevos datos válidos aleatorios
    - Verificar: mismo `id`, nuevos valores persistidos, `Servicio::count()` no cambia, redirect con flash `success`
    - **Valida: Requisitos 3.2**

  - [ ]* 1.5 Escribir property test — Propiedad 5: eliminación bloqueada por integridad referencial
    - **Propiedad 5: Eliminación bloqueada por integridad referencial**
    - Agregar al archivo `tests/Feature/ServicioPropertiesTest.php`
    - Generar servicios con 1 a N ingresos asociados en `detalle_servicios`
    - Verificar: `Servicio::find($id)` sigue existiendo, response redirige con flash `error`
    - Mínimo 50 combinaciones (variando número de ingresos asociados: 1, 2, 5, 10…)
    - **Valida: Requisitos 4.2**

  - [ ]* 1.6 Escribir property test — Propiedad 6: control de acceso por rol
    - **Propiedad 6: Control de acceso por rol**
    - Agregar al archivo `tests/Feature/ServicioPropertiesTest.php`
    - Cubrir las 6 rutas del recurso con usuarios no autenticados y autenticados sin rol `Administrador`
    - Verificar: no autenticado → redirect `/login`; autenticado sin rol → HTTP 403
    - Mínimo 50 combinaciones (rutas × métodos HTTP × tipos de usuario)
    - **Valida: Requisitos 5.1, 5.2**

- [x] 2. Registrar rutas en `routes/web.php`
  - Agregar `use App\Http\Controllers\ServicioController;` al bloque de imports
  - Dentro del grupo `middleware(['auth', 'role:Administrador'])`, agregar:
    ```php
    Route::resource('servicios', ServicioController::class)
         ->except(['show'])
         ->parameters(['servicios' => 'servicio']);
    ```
  - _Requisitos: 5.3_

- [x] 3. Checkpoint — Verificar rutas y controlador
  - Asegurarse de que todos los tests pasen hasta este punto, preguntar al usuario si hay dudas.

- [x] 4. Crear vista `resources/views/servicios/index.blade.php`
  - Extender `layouts.app`, sección `content`
  - Flash messages: `session('success')` verde / `session('error')` rojo
  - Header con título "Servicios" y botón "Crear servicio" (`bg-blue-600`) → `servicios.create`
  - Estado vacío: `"No hay servicios registrados."` centrado (`text-center py-12 text-gray-500 text-sm`)
  - Tabla con columnas: Nombre | Precio | Acciones
  - Precio: `S/ {{ number_format($servicio->precio, 2) }}`
  - Botón "Editar" (`bg-gray-100`) → `servicios.edit`
  - Botón "Eliminar" (`bg-red-100`) con form DELETE y `onclick="return confirm('¿Estás seguro de que deseas eliminar este servicio?')"`
  - Paginación: `{{ $servicios->links() }}`
  - _Requisitos: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

  - [ ]* 4.1 Escribir property test — Propiedad 7: formato monetario consistente
    - **Propiedad 7: Formato monetario consistente**
    - Agregar al archivo `tests/Feature/ServicioPropertiesTest.php`
    - Generar precios decimales positivos variados (enteros, decimales, grandes, pequeños, con muchos decimales que se redondean)
    - Verificar que la vista muestra `S/ X,XXX.XX` cumpliendo regex `/^S\/ \d{1,3}(,\d{3})*\.\d{2}$/`
    - Mínimo 50 valores de precio distintos
    - **Valida: Requisitos 1.3**

- [x] 5. Crear vista `resources/views/servicios/create.blade.php`
  - Extender `layouts.app`, sección `content`
  - Flash messages
  - Header con título "Crear servicio" y botón "Volver" (`bg-gray-100`) → `servicios.index`
  - Formulario `action="{{ route('servicios.store') }}"` method POST, `novalidate`
  - Campo Nombre: `type="text"`, `old('nombre')`, error state `border-red-400 bg-red-50`, `@error('nombre')`
  - Campo Precio: `type="number"` step="0.01" min="0.01", `old('precio')`, error state, `@error('precio')`
  - Botón "Guardar" (`bg-blue-600`) y enlace "Cancelar"
  - _Requisitos: 2.1, 2.2, 2.6_

- [x] 6. Crear vista `resources/views/servicios/edit.blade.php`
  - Idéntica a `create.blade.php` con las siguientes diferencias:
  - Título: "Editar servicio"
  - `action="{{ route('servicios.update', $servicio) }}"` + `@method('PUT')`
  - Valores precargados: `old('nombre', $servicio->nombre)`, `old('precio', $servicio->precio)`
  - Botón submit: "Guardar cambios"
  - _Requisitos: 3.1, 3.5_

  - [ ]* 6.1 Escribir tests de ejemplo en `tests/Feature/ServicioCrudTest.php`
    - Crear `tests/Feature/ServicioCrudTest.php`
    - Casos a cubrir:
      - `GET /servicios` retorna 200 con vista correcta (usuario Administrador)
      - `GET /servicios/create` retorna 200
      - `GET /servicios/{id}/edit` retorna 200 con datos precargados
      - Estado vacío muestra "No hay servicios registrados."
      - El botón Eliminar incluye `onclick="return confirm(...)"` en la vista
      - Tras fallo de validación, los campos se repueblan con `old()`
    - _Requisitos: 1.1, 1.4, 2.1, 2.6, 3.1, 3.5_

- [x] 7. Checkpoint — Verificar vistas y tests de ejemplo
  - Asegurarse de que todos los tests pasen hasta este punto, preguntar al usuario si hay dudas.

- [x] 8. Modificar `resources/views/layouts/app.blade.php` — Agregar "Servicios" en "Gestión Administrativa"
  - En el bloque `@php`, actualizar la variable existente:
    ```php
    $gestionAdministrativaActive = request()->routeIs('vehiculos.*', 'servicios.*');
    ```
  - En el sidebar desktop, dentro del `data-dropdown-menu="gestion-administrativa"`, agregar después del enlace "Vehículos":
    ```html
    <a href="{{ route('servicios.index') }}"
       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
              {{ request()->routeIs('servicios.*') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
        Servicios
    </a>
    ```
  - En el bottom nav móvil, dentro del `data-dropdown-menu="gestion-administrativa-mobile"`, agregar después del enlace "Vehículos":
    ```html
    <a href="{{ route('servicios.index') }}"
       class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
              {{ request()->routeIs('servicios.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
        Servicios
    </a>
    ```
  - _Requisitos: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [ ]* 8.1 Escribir property test — Propiedad 4: eliminación libre sin ingresos
    - **Propiedad 4: Eliminación libre cuando no hay ingresos asociados**
    - Agregar al archivo `tests/Feature/ServicioPropertiesTest.php`
    - Generar servicios sin ingresos asociados (nombres y precios variados)
    - Verificar: `Servicio::find($id)` retorna null, response redirige con flash `success`
    - Mínimo 50 combinaciones
    - **Valida: Requisitos 4.1**

  - [ ]* 8.2 Escribir tests de ejemplo para la navegación en `ServicioCrudTest.php`
    - Agregar a `tests/Feature/ServicioCrudTest.php`:
      - El enlace "Servicios" aparece en el sidebar para usuario Administrador
      - El enlace "Servicios" no aparece para usuarios sin el rol
      - La sección "Gestión Administrativa" se expande en rutas `servicios.*`
    - _Requisitos: 6.1, 6.3_

- [x] 9. Checkpoint final — Asegurarse de que todos los tests pasen
  - Ejecutar la suite completa: `php artisan test`
  - Asegurarse de que todos los tests pasen, preguntar al usuario si hay dudas.

## Notas

- Las sub-tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- No hay migración que crear: la tabla `servicios` ya existe y el modelo `Servicio` no requiere cambios
- La verificación de integridad usa `$servicio->ingresos()->exists()` (BelongsToMany a través de `detalle_servicios`)
- Los property tests usan `it()->with(...)` de Pest con ≥50 combinaciones de inputs por propiedad
- Los tests usan la base de datos SQLite de testing con `RefreshDatabase`
- Los tests de ejemplo cubren casos concretos de rutas, vistas y estructura HTML
