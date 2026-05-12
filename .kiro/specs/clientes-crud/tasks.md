# Plan de Implementación: clientes-crud

## Visión General

Implementar el CRUD de Clientes siguiendo el patrón MVC existente del proyecto (Laravel + Blade + Tailwind CSS). El módulo incluye controlador resource, rutas protegidas por rol, tres vistas Blade, modificación del layout para agregar el enlace "Clientes" en la sección "Gestión Administrativa" existente, y tests de ejemplo y de propiedades. **No se requiere migración**: la tabla `clientes` y el modelo `Cliente` ya existen y están correctamente configurados.

## Tareas

- [x] 1. Implementar `ClienteController`
  - [x] 1.1 Crear `app/Http/Controllers/ClienteController.php` con los 6 métodos resource
    - `index()`: `Cliente::paginate(15)` → vista `clientes.index`
    - `create()`: retorna vista `clientes.create`
    - `store(Request $request)`: validar, `Cliente::create()`, redirect con flash `'Cliente creado correctamente.'`
    - `edit(Cliente $cliente)`: retorna vista `clientes.edit` con model binding
    - `update(Request $request, Cliente $cliente)`: validar, `$cliente->update()`, redirect con flash `'Cliente actualizado correctamente.'`
    - `destroy(Cliente $cliente)`: verificar `ingresos()->exists()`, `ventas()->exists()`, `cambioAceites()->exists()` en orden; eliminar o redirigir con flash de error correspondiente
    - Reglas de validación `store`: `dni` required|string|size:8|regex:/^\d{8}$/|unique:clientes,dni; `nombre` required|string|max:100; `placa` required|string|max:7
    - Reglas de validación `update`: igual que `store` pero con `unique:clientes,dni,{$cliente->id}` para excluir el propio registro
    - _Requisitos: 1.1, 2.3, 2.4, 2.5, 2.6, 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4_

  - [ ]* 1.2 Escribir property test — Propiedad 1: creación persiste datos válidos
    - **Propiedad 1: Creación persiste datos válidos**
    - Crear `tests/Feature/ClientePropertiesTest.php` con el primer test
    - Generar ≥50 combinaciones: DNIs de exactamente 8 dígitos numéricos únicos, nombres de 1–100 chars, placas de 1–7 chars alfanuméricos
    - Verificar: `Cliente::count()` aumenta en 1, el registro tiene los valores enviados, response redirige a `clientes.index` con flash `success`
    - **Valida: Requisitos 2.3**

  - [ ]* 1.3 Escribir property test — Propiedad 2: validación rechaza DNI inválido
    - **Propiedad 2: Validación rechaza DNI inválido sin persistir**
    - Agregar al archivo `tests/Feature/ClientePropertiesTest.php`
    - Generar entradas inválidas: DNI vacío, longitud ≠ 8, con letras/símbolos, DNI duplicado de registro existente
    - Verificar: `Cliente::count()` no cambia, response contiene errores de validación para el campo `dni`
    - **Valida: Requisitos 2.4, 3.3**

  - [ ]* 1.4 Escribir property test — Propiedad 3: validación rechaza nombre o placa inválidos
    - **Propiedad 3: Validación rechaza nombre o placa inválidos sin persistir**
    - Agregar al archivo `tests/Feature/ClientePropertiesTest.php`
    - Generar entradas inválidas: nombre vacío, nombre >100 chars; placa vacía, placa >7 chars
    - Verificar: `Cliente::count()` no cambia, response contiene errores de validación para el campo correspondiente
    - **Valida: Requisitos 2.5, 2.6, 3.4, 3.5**

  - [ ]* 1.5 Escribir property test — Propiedad 4: repoblación de campos tras fallo de validación
    - **Propiedad 4: Repoblación de campos tras fallo de validación**
    - Agregar al archivo `tests/Feature/ClientePropertiesTest.php`
    - Generar combinaciones de datos con al menos un campo inválido (creación y edición)
    - Verificar: los valores enviados aparecen en el HTML de la respuesta (`old()`)
    - **Valida: Requisitos 2.7, 3.6**

  - [ ]* 1.6 Escribir property test — Propiedad 5: edición actualiza y preserva identidad
    - **Propiedad 5: Edición actualiza datos y preserva identidad del registro**
    - Agregar al archivo `tests/Feature/ClientePropertiesTest.php`
    - Generar clientes existentes aleatorios y nuevos datos válidos aleatorios
    - Verificar: mismo `id`, nuevos valores persistidos, `Cliente::count()` no cambia, redirect a `clientes.index` con flash `success`
    - **Valida: Requisitos 3.1, 3.2**

  - [ ]* 1.7 Escribir property test — Propiedad 6: eliminación bloqueada por registros asociados
    - **Propiedad 6: Eliminación bloqueada por cualquier registro asociado**
    - Agregar al archivo `tests/Feature/ClientePropertiesTest.php`
    - Generar clientes con 1 a N ingresos; clientes con 1 a N ventas; clientes con 1 a N cambios de aceite
    - Verificar: `Cliente::find($id)` sigue existiendo, response redirige con flash `error` con el mensaje correspondiente al tipo de asociación
    - **Valida: Requisitos 4.2, 4.3, 4.4**

  - [ ]* 1.8 Escribir property test — Propiedad 7: control de acceso por rol
    - **Propiedad 7: Control de acceso por rol**
    - Agregar al archivo `tests/Feature/ClientePropertiesTest.php`
    - Cubrir las 6 rutas del recurso con usuarios no autenticados y autenticados sin rol `Administrador`
    - Verificar: no autenticado → redirect `/login`; autenticado sin rol → HTTP 403
    - **Valida: Requisitos 5.1, 5.2**

- [x] 2. Registrar rutas en `routes/web.php`
  - Agregar `use App\Http\Controllers\ClienteController;` al bloque de imports
  - Dentro del grupo `middleware(['auth', 'role:Administrador'])`, agregar:
    ```php
    Route::resource('clientes', ClienteController::class)
         ->except(['show'])
         ->parameters(['clientes' => 'cliente']);
    ```
  - _Requisitos: 5.3_

- [x] 3. Checkpoint — Verificar rutas y controlador
  - Asegurarse de que todos los tests pasen hasta este punto, preguntar al usuario si hay dudas.

- [x] 4. Crear vista `resources/views/clientes/index.blade.php`
  - Extender `layouts.app`, sección `content`
  - Flash messages: `session('success')` verde / `session('error')` rojo
  - Header con título "Clientes" y botón "Crear cliente" (`bg-blue-600`) → `clientes.create`
  - Estado vacío: `"No hay clientes registrados."` centrado (`text-center py-12 text-gray-500 text-sm`)
  - Tabla con columnas: DNI | Nombre | Placa | Acciones
  - Botón "Editar" (`bg-gray-100`) → `clientes.edit`
  - Botón "Eliminar" (`bg-red-100`) con form DELETE y `onclick="return confirm('¿Estás seguro de que deseas eliminar este cliente?')"`
  - Paginación: `{{ $clientes->links() }}`
  - _Requisitos: 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 5. Crear vista `resources/views/clientes/create.blade.php`
  - Extender `layouts.app`, sección `content`
  - Flash messages
  - Header con título "Crear cliente" y botón "Volver" (`bg-gray-100`) → `clientes.index`
  - Formulario `action="{{ route('clientes.store') }}"` method POST, `novalidate`
  - Campo DNI: `type="text"`, `old('dni')`, `maxlength="8"`, error state `border-red-400 bg-red-50`, `@error('dni')`
  - Campo Nombre: `type="text"`, `old('nombre')`, error state `border-red-400 bg-red-50`, `@error('nombre')`
  - Campo Placa: `type="text"`, `old('placa')`, `maxlength="7"`, error state `border-red-400 bg-red-50`, `@error('placa')`
  - Botón "Guardar" (`bg-blue-600`) y enlace "Cancelar"
  - _Requisitos: 2.1, 2.2, 2.7_

- [x] 6. Crear vista `resources/views/clientes/edit.blade.php`
  - Idéntica a `create.blade.php` con las siguientes diferencias:
  - Título: "Editar cliente"
  - `action="{{ route('clientes.update', $cliente) }}"` + `@method('PUT')`
  - Valores precargados: `old('dni', $cliente->dni)`, `old('nombre', $cliente->nombre)`, `old('placa', $cliente->placa)`
  - Botón submit: "Guardar cambios"
  - _Requisitos: 3.1, 3.6_

  - [ ]* 6.1 Escribir tests de ejemplo en `tests/Feature/ClienteCrudTest.php`
    - Crear `tests/Feature/ClienteCrudTest.php`
    - Casos a cubrir:
      - `GET /clientes` retorna 200 con vista correcta (usuario Administrador)
      - `GET /clientes/create` retorna 200
      - `GET /clientes/{id}/edit` retorna 200 con datos precargados
      - Estado vacío muestra "No hay clientes registrados."
      - El botón Eliminar incluye `onclick="return confirm(...)"` en la vista
      - La tabla muestra las columnas DNI, Nombre y Placa
      - Con 16 clientes, aparecen controles de paginación
      - Tras fallo de validación, los campos se repueblan con `old()`
    - _Requisitos: 1.1, 1.2, 1.3, 1.4, 2.1, 2.7, 3.1, 3.6_

- [x] 7. Checkpoint — Verificar vistas y tests de ejemplo
  - Asegurarse de que todos los tests pasen hasta este punto, preguntar al usuario si hay dudas.

- [x] 8. Modificar `resources/views/layouts/app.blade.php` — Agregar enlace "Clientes"
  - En el bloque `@php`, actualizar la variable existente:
    ```php
    // Antes:
    $gestionAdministrativaActive = request()->routeIs('vehiculos.*', 'servicios.*');
    // Después:
    $gestionAdministrativaActive = request()->routeIs('vehiculos.*', 'servicios.*', 'clientes.*');
    ```
  - En el sidebar desktop, dentro del `data-dropdown-menu="gestion-administrativa"` existente, agregar después del enlace "Servicios":
    ```html
    <a href="{{ route('clientes.index') }}"
       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
              {{ request()->routeIs('clientes.*') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
        Clientes
    </a>
    ```
  - En el bottom nav móvil, dentro del `data-dropdown-menu="gestion-administrativa-mobile"` existente, agregar después del enlace "Servicios":
    ```html
    <a href="{{ route('clientes.index') }}"
       class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
              {{ request()->routeIs('clientes.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
        Clientes
    </a>
    ```
  - _Requisitos: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [ ]* 8.1 Escribir tests de ejemplo para la navegación en `ClienteCrudTest.php`
    - Agregar a `tests/Feature/ClienteCrudTest.php`:
      - El sidebar muestra el enlace "Clientes" para usuarios Administrador
      - La sección "Gestión Administrativa" permanece expandida al navegar por `clientes.*`
      - La sección "Gestión Administrativa" no aparece para usuarios sin el rol
    - _Requisitos: 6.1, 6.3_

- [x] 9. Checkpoint final — Asegurarse de que todos los tests pasen
  - Ejecutar la suite completa: `php artisan test`
  - Asegurarse de que todos los tests pasen, preguntar al usuario si hay dudas.

## Notas

- Las sub-tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- **No se requiere migración**: la tabla `clientes` y el modelo `Cliente` ya existen con la estructura correcta
- Cada tarea referencia los requisitos específicos para trazabilidad
- Los checkpoints garantizan validación incremental
- Los property tests usan `it()->with(...)` de Pest con ≥50 combinaciones de inputs por propiedad
- Los tests de ejemplo cubren casos concretos de rutas, vistas y estructura HTML
- La eliminación verifica tres relaciones independientes en orden: `ingresos`, `ventas`, `cambioAceites`
