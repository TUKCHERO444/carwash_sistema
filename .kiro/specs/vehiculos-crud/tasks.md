# Plan de Implementación: vehiculos-crud

## Visión General

Implementar el CRUD de Vehículos siguiendo el patrón MVC existente del proyecto (Laravel + Blade + Tailwind CSS). El módulo incluye migración, controlador resource, rutas protegidas por rol, tres vistas Blade, modificación del layout para la sección "Gestión Administrativa", y tests de ejemplo y de propiedades.

## Tareas

- [x] 1. Crear migración para hacer `descripcion` nullable
  - Crear `database/migrations/YYYY_MM_DD_HHMMSS_make_vehiculos_descripcion_nullable.php`
  - En `up()`: `$table->text('descripcion')->nullable()->change()`
  - En `down()`: `$table->text('descripcion')->nullable(false)->change()`
  - Ejecutar `php artisan migrate`
  - _Requisitos: 2.2_

- [x] 2. Implementar `VehiculoController`
  - [x] 2.1 Crear `app/Http/Controllers/VehiculoController.php` con los 6 métodos resource
    - `index()`: `Vehiculo::paginate(15)` → vista `vehiculos.index`
    - `create()`: retorna vista `vehiculos.create`
    - `store(Request $request)`: validar, `Vehiculo::create()`, redirect con flash `'Vehículo creado correctamente.'`
    - `edit(Vehiculo $vehiculo)`: retorna vista `vehiculos.edit` con model binding
    - `update(Request $request, Vehiculo $vehiculo)`: validar, `$vehiculo->update()`, redirect con flash `'Vehículo actualizado correctamente.'`
    - `destroy(Vehiculo $vehiculo)`: verificar `$vehiculo->ingresos()->exists()`, eliminar o redirigir con flash de error
    - Reglas de validación compartidas: `nombre` required|string|max:100, `descripcion` nullable|string, `precio` required|numeric|gt:0
    - _Requisitos: 1.1, 2.3, 2.4, 2.5, 3.1, 3.2, 3.3, 3.4, 4.1, 4.2_

  - [ ]* 2.2 Escribir property test — Propiedad 1: creación persiste datos válidos
    - **Propiedad 1: Creación persiste datos válidos**
    - Crear `tests/Feature/VehiculoPropertiesTest.php` con el primer test
    - Generar ≥50 combinaciones: nombres de 1–100 chars, precios de 0.01 a 999999.99
    - Verificar: `Vehiculo::count()` aumenta en 1, el registro tiene los valores enviados, response redirige a `vehiculos.index` con flash `success`
    - **Valida: Requisitos 2.3**

  - [ ]* 2.3 Escribir property test — Propiedad 2: validación rechaza entradas inválidas
    - **Propiedad 2: Validación rechaza entradas inválidas sin persistir**
    - Agregar al archivo `tests/Feature/VehiculoPropertiesTest.php`
    - Generar entradas inválidas: nombre vacío, nombre >100 chars, precio 0, precio negativo, precio no numérico
    - Verificar: `Vehiculo::count()` no cambia, response contiene errores de validación
    - **Valida: Requisitos 2.4, 2.5, 3.3, 3.4**

  - [ ]* 2.4 Escribir property test — Propiedad 3: edición actualiza y preserva identidad
    - **Propiedad 3: Edición actualiza datos y preserva identidad del registro**
    - Agregar al archivo `tests/Feature/VehiculoPropertiesTest.php`
    - Generar vehículos existentes y nuevos datos válidos aleatorios
    - Verificar: mismo `id`, nuevos valores persistidos, `Vehiculo::count()` no cambia, redirect con flash `success`
    - **Valida: Requisitos 3.2**

  - [ ]* 2.5 Escribir property test — Propiedad 4: eliminación bloqueada por integridad referencial
    - **Propiedad 4: Eliminación bloqueada por integridad referencial**
    - Agregar al archivo `tests/Feature/VehiculoPropertiesTest.php`
    - Generar vehículos con 1 a N ingresos asociados
    - Verificar: `Vehiculo::find($id)` sigue existiendo, response redirige con flash `error`
    - **Valida: Requisitos 4.2**

  - [ ]* 2.6 Escribir property test — Propiedad 5: control de acceso por rol
    - **Propiedad 5: Control de acceso por rol**
    - Agregar al archivo `tests/Feature/VehiculoPropertiesTest.php`
    - Cubrir las 6 rutas del recurso con usuarios no autenticados y autenticados sin rol `Administrador`
    - Verificar: no autenticado → redirect `/login`; autenticado sin rol → HTTP 403
    - **Valida: Requisitos 5.1, 5.2**

- [x] 3. Registrar rutas en `routes/web.php`
  - Agregar `use App\Http\Controllers\VehiculoController;` al bloque de imports
  - Dentro del grupo `middleware(['auth', 'role:Administrador'])`, agregar:
    ```php
    Route::resource('vehiculos', VehiculoController::class)
         ->except(['show'])
         ->parameters(['vehiculos' => 'vehiculo']);
    ```
  - _Requisitos: 5.3_

- [x] 4. Checkpoint — Verificar rutas y controlador
  - Asegurarse de que todos los tests pasen hasta este punto, preguntar al usuario si hay dudas.

- [x] 5. Crear vista `resources/views/vehiculos/index.blade.php`
  - Extender `layouts.app`, sección `content`
  - Flash messages: `session('success')` verde / `session('error')` rojo
  - Header con título "Vehículos" y botón "Crear vehículo" (`bg-blue-600`) → `vehiculos.create`
  - Estado vacío: `"No hay vehículos registrados."` centrado (`text-center py-12 text-gray-500 text-sm`)
  - Tabla con columnas: Nombre | Descripción | Precio | Acciones
  - Descripción vacía: `{{ $vehiculo->descripcion ?? '—' }}`
  - Precio: `S/ {{ number_format($vehiculo->precio, 2) }}`
  - Botón "Editar" (`bg-gray-100`) → `vehiculos.edit`
  - Botón "Eliminar" (`bg-red-100`) con form DELETE y `onclick="return confirm('¿Estás seguro de que deseas eliminar este vehículo?')"`
  - Paginación: `{{ $vehiculos->links() }}`
  - _Requisitos: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

  - [ ]* 5.1 Escribir property test — Propiedad 6: formato monetario consistente
    - **Propiedad 6: Formato monetario consistente**
    - Agregar al archivo `tests/Feature/VehiculoPropertiesTest.php`
    - Generar precios decimales positivos variados (enteros, decimales, grandes, pequeños)
    - Verificar que la vista muestra `S/ X,XXX.XX` cumpliendo regex `/^S\/ \d{1,3}(,\d{3})*\.\d{2}$/`
    - **Valida: Requisitos 1.3**

- [x] 6. Crear vista `resources/views/vehiculos/create.blade.php`
  - Extender `layouts.app`, sección `content`
  - Flash messages
  - Header con título "Crear vehículo" y botón "Volver" (`bg-gray-100`) → `vehiculos.index`
  - Formulario `action="{{ route('vehiculos.store') }}"` method POST, `novalidate`
  - Campo Nombre: `type="text"`, `old('nombre')`, error state `border-red-400 bg-red-50`, `@error('nombre')`
  - Campo Descripción: `<textarea>` rows="3", `old('descripcion')`, sin asterisco (opcional), sin validación de error requerida
  - Campo Precio: `type="number"` step="0.01" min="0.01", `old('precio')`, error state, `@error('precio')`
  - Botón "Guardar" (`bg-blue-600`) y enlace "Cancelar"
  - _Requisitos: 2.1, 2.2, 2.6, 3.5_

- [x] 7. Crear vista `resources/views/vehiculos/edit.blade.php`
  - Idéntica a `create.blade.php` con las siguientes diferencias:
  - Título: "Editar vehículo"
  - `action="{{ route('vehiculos.update', $vehiculo) }}"` + `@method('PUT')`
  - Valores precargados: `old('nombre', $vehiculo->nombre)`, `old('descripcion', $vehiculo->descripcion)`, `old('precio', $vehiculo->precio)`
  - Botón submit: "Guardar cambios"
  - _Requisitos: 3.1, 3.5_

  - [ ]* 7.1 Escribir tests de ejemplo en `tests/Feature/VehiculoCrudTest.php`
    - Crear `tests/Feature/VehiculoCrudTest.php`
    - Casos a cubrir:
      - `GET /vehiculos` retorna 200 con vista correcta (usuario Administrador)
      - `GET /vehiculos/create` retorna 200
      - `GET /vehiculos/{id}/edit` retorna 200 con datos precargados
      - Estado vacío muestra "No hay vehículos registrados."
      - El botón Eliminar incluye `onclick="return confirm(...)"` en la vista
      - Tras fallo de validación, los campos se repueblan con `old()`
    - _Requisitos: 1.1, 1.4, 2.1, 2.6, 3.1, 3.5_

- [x] 8. Checkpoint — Verificar vistas y tests de ejemplo
  - Asegurarse de que todos los tests pasen hasta este punto, preguntar al usuario si hay dudas.

- [x] 9. Modificar `resources/views/layouts/app.blade.php` — Sección "Gestión Administrativa"
  - Agregar al bloque `@php` la variable: `$gestionAdministrativaActive = request()->routeIs('vehiculos.*');`
  - En el sidebar desktop, dentro del `@if(auth()->user()?->hasRole('Administrador'))`, agregar después del bloque `product-management` el dropdown `data-dropdown="gestion-administrativa"` con enlace a `vehiculos.index`
  - En el bottom nav móvil, dentro del mismo `@if`, agregar el dropdown `data-dropdown="gestion-administrativa-mobile"` equivalente con `data-persistent` cuando está activo
  - Ambos dropdowns deben usar `$gestionAdministrativaActive` para el estado expandido/colapsado y el resaltado del enlace "Vehículos"
  - _Requisitos: 6.1, 6.2, 6.3, 6.4_

  - [ ]* 9.1 Escribir tests de ejemplo para la navegación en `VehiculoCrudTest.php`
    - Agregar a `tests/Feature/VehiculoCrudTest.php`:
      - La sección "Gestión Administrativa" aparece en el sidebar para usuario Administrador
      - La sección "Gestión Administrativa" no aparece para usuarios sin el rol
    - _Requisitos: 6.1_

- [x] 10. Checkpoint final — Asegurarse de que todos los tests pasen
  - Ejecutar la suite completa: `php artisan test`
  - Asegurarse de que todos los tests pasen, preguntar al usuario si hay dudas.

## Notas

- Las sub-tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia los requisitos específicos para trazabilidad
- Los checkpoints garantizan validación incremental
- Los property tests usan `it()->with(...)` de Pest con ≥50 combinaciones de inputs por propiedad
- Los tests de ejemplo cubren casos concretos de rutas, vistas y estructura HTML
