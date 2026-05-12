# Plan de Implementación: trabajadores-crud

## Descripción general

Implementar el CRUD completo de la entidad `Trabajador` siguiendo exactamente los patrones del módulo `users` existente. El plan avanza de forma incremental: primero la ruta y el controlador (backend), luego las vistas (frontend), y finalmente los tests de propiedad que validan la lógica de negocio.

## Tareas

- [x] 1. Registrar la ruta resource en `routes/web.php`
  - Añadir `use App\Http\Controllers\TrabajadorController;` al bloque de imports.
  - Añadir `Route::resource('trabajadores', TrabajadorController::class)->except(['show']);` dentro del grupo `middleware(['auth', 'role:Administrador'])` existente, junto a las rutas de `users` y `roles`.
  - _Requirements: 5.1_

- [x] 2. Crear `TrabajadorController` con los seis métodos CRUD
  - [x] 2.1 Crear el archivo `app/Http/Controllers/TrabajadorController.php`
    - Implementar `index()`: obtener `Trabajador::paginate(15)` y retornar la vista `trabajadores.index`.
    - Implementar `create()`: retornar la vista `trabajadores.create`.
    - Implementar `store(Request $request)`: validar `nombre` (required, string, max:100, unique:trabajadores) y `estado` (required, boolean); crear con `Trabajador::create()`; redirigir a `trabajadores.index` con flash `'Trabajador creado correctamente.'`.
    - Implementar `edit(Trabajador $trabajador)`: retornar la vista `trabajadores.edit` con el modelo inyectado.
    - Implementar `update(Request $request, Trabajador $trabajador)`: validar `nombre` (unique ignorando el propio id) y `estado`; actualizar con `$trabajador->update()`; redirigir con flash `'Trabajador actualizado correctamente.'`.
    - Implementar `destroy(Trabajador $trabajador)`: verificar `cambioAceites()->exists()` y `ingresos()->exists()` antes de eliminar; redirigir con flash de error específico si hay relaciones, o con flash de éxito `'Trabajador eliminado correctamente.'` si no las hay.
    - _Requirements: 1.1, 2.2, 2.3, 2.4, 2.5, 2.6, 3.2, 3.3, 3.4, 3.5, 3.6, 4.1, 4.2, 4.3_

  - [ ]* 2.2 Escribir test de propiedad — Property 2: Creación persiste cualquier dato válido
    - **Property 2: Creación persiste cualquier dato válido**
    - Generar 100 combinaciones de nombre válido (string no vacío, ≤100 chars, único) y estado booleano aleatorio.
    - Verificar que `POST /trabajadores` crea exactamente un registro en la BD y redirige a `trabajadores.index` con flash de éxito.
    - **Validates: Requirements 2.2**

  - [ ]* 2.3 Escribir test de propiedad — Property 3: Validación rechaza nombres inválidos
    - **Property 3: Validación rechaza nombres inválidos**
    - Generar 100 inputs inválidos: string vacío, string de más de 100 caracteres, nombre duplicado ya existente en la tabla.
    - Verificar que tanto `POST /trabajadores` como `PUT /trabajadores/{trabajador}` rechazan la petición, redirigen al formulario y preservan los valores en sesión.
    - **Validates: Requirements 2.3, 2.4, 2.6, 3.3, 3.6**

  - [ ]* 2.4 Escribir test de propiedad — Property 4: Unicidad en edición ignora el propio registro
    - **Property 4: La unicidad de nombre en edición ignora el propio registro**
    - Para 100 trabajadores existentes, enviar `PUT /trabajadores/{trabajador}` con el mismo nombre sin cambios.
    - Verificar que la petición es válida y el registro se actualiza correctamente sin error de unicidad.
    - **Validates: Requirements 3.4**

  - [ ]* 2.5 Escribir test de propiedad — Property 6: Eliminación procede solo sin relaciones
    - **Property 6: Eliminación procede solo cuando no hay relaciones**
    - Para 100 trabajadores sin `cambioAceites` ni `ingresos`, enviar `DELETE /trabajadores/{trabajador}`.
    - Verificar que el registro es eliminado de la BD y la respuesta redirige con flash de éxito.
    - **Validates: Requirements 4.1**

  - [ ]* 2.6 Escribir test de propiedad — Property 7: Eliminación bloqueada por cualquier relación
    - **Property 7: Eliminación es bloqueada por cualquier relación existente**
    - Para 100 trabajadores con al menos un `CambioAceite` o al menos un `Ingreso` (variando aleatoriamente), enviar `DELETE /trabajadores/{trabajador}`.
    - Verificar que el registro sigue existiendo en la BD y la respuesta redirige con el flash de error correspondiente.
    - **Validates: Requirements 4.2, 4.3**

  - [ ]* 2.7 Escribir test de propiedad — Property 8: Rutas requieren autenticación
    - **Property 8: Todas las rutas del módulo requieren autenticación**
    - Para cada una de las 6 rutas del módulo (index, create, store, edit, update, destroy), enviar la petición HTTP sin sesión autenticada.
    - Verificar que todas redirigen a `/login`.
    - **Validates: Requirements 5.2**

  - [ ]* 2.8 Escribir test de propiedad — Property 9: Rutas requieren rol Administrador
    - **Property 9: Todas las rutas del módulo requieren el rol Administrador**
    - Para cada una de las 6 rutas del módulo, enviar la petición con un usuario autenticado sin el rol `Administrador`.
    - Verificar que todas devuelven HTTP 403 o redirigen con acceso denegado.
    - **Validates: Requirements 5.3**

- [x] 3. Checkpoint — Verificar que el controlador y las rutas funcionan
  - Asegurarse de que todos los tests del controlador pasan. Consultar al usuario si surgen dudas.

- [x] 4. Crear la vista `resources/views/trabajadores/index.blade.php`
  - Extender `layouts.app`.
  - Mostrar flash messages de éxito (`bg-green-100`) y error (`bg-red-100`) siguiendo el patrón de `users.index`.
  - Encabezado con título "Trabajadores" y botón "Crear trabajador" enlazando a `trabajadores.create`.
  - Estado vacío: mostrar "No hay trabajadores registrados." cuando `$trabajadores->isEmpty()`.
  - Tabla con columnas: Nombre, Estado, Acciones; contenedor `bg-white rounded-lg border border-gray-200`.
  - Badge de estado: verde (`bg-green-100 text-green-800`) con texto "Activo" cuando `estado` es `true`; rojo (`bg-red-100 text-red-800`) con texto "Inactivo" cuando `estado` es `false`.
  - Botón Editar (`bg-gray-100 text-gray-700`) enlazando a `trabajadores.edit`.
  - Formulario de eliminación con `@method('DELETE')`, botón (`bg-red-100 text-red-700`) y `onclick="return confirm('¿Estás seguro?')"`.
  - Paginación con `{{ $trabajadores->links() }}` debajo de la tabla.
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 4.4, 6.1, 6.4, 6.5_

  - [ ]* 4.1 Escribir test de propiedad — Property 1: Badge de estado consistente con valor booleano
    - **Property 1: Badge de estado es consistente con el valor booleano**
    - Para 100 trabajadores con `estado` aleatorio (true/false), renderizar la vista `trabajadores.index`.
    - Verificar que el HTML contiene "Activo" con clases verdes cuando `estado` es `true`, e "Inactivo" con clases rojas cuando `estado` es `false`.
    - **Validates: Requirements 1.3**

- [x] 5. Crear la vista `resources/views/trabajadores/create.blade.php`
  - Extender `layouts.app`.
  - Mostrar flash messages de éxito y error.
  - Encabezado con título "Crear trabajador" y botón "Volver" (`bg-gray-100 text-gray-700`) enlazando a `trabajadores.index`.
  - Formulario con `action="{{ route('trabajadores.store') }}"`, `method="POST"` y atributo `novalidate`.
  - Campo `nombre`: input text con `old('nombre')`, clases de error condicionales (`border-red-400 bg-red-50`) y mensaje `@error` con clase `text-xs text-red-600`.
  - Campo `estado`: `<select>` con opciones "Activo" (valor `1`) e "Inactivo" (valor `0`), con `old('estado')` para preservar selección.
  - Botón "Guardar" y enlace "Cancelar" enlazando a `trabajadores.index`.
  - Contenedor del formulario con clases `bg-white rounded-lg border border-gray-200 p-6 max-w-lg`.
  - _Requirements: 2.1, 2.6, 2.7, 2.8, 6.2, 6.3, 6.5, 6.6_

- [x] 6. Crear la vista `resources/views/trabajadores/edit.blade.php`
  - Extender `layouts.app`.
  - Mostrar flash messages de éxito y error.
  - Encabezado con título "Editar trabajador" y botón "Volver" enlazando a `trabajadores.index`.
  - Formulario con `action="{{ route('trabajadores.update', $trabajador) }}"`, `method="POST"`, `@method('PUT')` y atributo `novalidate`.
  - Campo `nombre`: input text con `old('nombre', $trabajador->nombre)`, clases de error condicionales y mensaje `@error`.
  - Campo `estado`: `<select>` con opciones "Activo" (valor `1`) e "Inactivo" (valor `0`); la opción correspondiente al estado actual debe estar preseleccionada usando `old('estado', $trabajador->estado ? '1' : '0')`.
  - Botón "Guardar cambios" y enlace "Cancelar" enlazando a `trabajadores.index`.
  - Contenedor del formulario con clases `bg-white rounded-lg border border-gray-200 p-6 max-w-lg`.
  - _Requirements: 3.1, 3.6, 3.7, 6.2, 6.3, 6.5, 6.6_

  - [ ]* 6.1 Escribir test de propiedad — Property 5: Formulario de edición precarga valores actuales
    - **Property 5: El formulario de edición precarga los valores actuales**
    - Para 100 trabajadores con nombre y estado aleatorios, renderizar la vista `trabajadores.edit`.
    - Verificar que el campo `nombre` contiene el valor actual del trabajador y que el `<select>` tiene preseleccionada la opción correspondiente al estado actual.
    - **Validates: Requirements 3.1, 3.7**

- [x] 7. Checkpoint final — Asegurarse de que todos los tests pasan
  - Ejecutar la suite completa de tests. Consultar al usuario si surgen dudas antes de cerrar.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido.
- Cada tarea referencia los requisitos específicos para trazabilidad.
- Los tests de propiedad deben ejecutarse con un mínimo de 100 iteraciones sobre inputs generados aleatoriamente (usando Faker en un loop dentro de Pest o la librería `eris/eris`).
- Los tests de ejemplo (flujos concretos, UI) se implementan como Laravel Feature Tests con PHPUnit/Pest.
- No se requieren migraciones: el modelo `Trabajador` y la tabla `trabajadores` ya existen.
