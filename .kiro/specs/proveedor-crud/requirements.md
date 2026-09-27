# Requisitos — Módulo Proveedores (CRUD)

**Feature:** `proveedor-crud`
**Estado:** implementado
**Alcance:** paso 1 del flujo de ingreso de productos. Solo la entidad `Proveedor`
y su CRUD en la sección administrativa. No incluye el ingreso de stock ni la
relación con `productos` (ver "Fuera de alcance").

## Entidad

- **Proveedor**: Entidad del dominio almacenada en la tabla `proveedores`, con campos
  `id`, `ruc`, `razon_social`, `direccion`, `estado_tributario`, `condicion`, `estado`
  y `timestamps`. Un Proveedor es la empresa que vende productos al sistema.

## Listado

1. WHEN un usuario con permiso `acceso-proveedores` navega a `/proveedores`, THE ProveedorController SHALL listar los Proveedores ordenados por `razon_social` de forma paginada de 10 en 10.
2. THE Listado SHALL mostrar las columnas: RUC, Razón Social, Dirección, Estado Tributario, Condición, Estado y Acciones.
3. THE Listado SHALL renderizar `estado = 1` como "Activo" y `estado = 0` como "Inactivo".
4. WHEN no existen Proveedores, THE Listado SHALL mostrar el estado vacío "No hay proveedores registrados."
5. THE tabla SHALL usar el wrapper `overflow-x-auto` para permitir scroll horizontal en móvil.

## Validación

6. THE ProveedorController SHALL validar que el campo `ruc` es requerido, de tipo string, y que contiene exactamente 11 dígitos numéricos.
7. THE ProveedorController SHALL validar que el campo `ruc` es único en la tabla `proveedores`.
8. THE ProveedorController SHALL validar que el campo `razon_social` es requerido, de tipo string, con máximo 150 caracteres, y solo admite letras, números, espacios y los signos básicos de una denominação social (`. , & ( ) ' " - / +`).
9. THE ProveedorController SHALL validar que el campo `direccion` es opcional, con máximo 200 caracteres.
10. THE ProveedorController SHALL validar que el campo `estado_tributario` es opcional, con máximo 100 caracteres y solo letras (es el estado que reporta la consulta de RUC por API, de longitud variable).
11. THE ProveedorController SHALL validar que el campo `condicion` es opcional, texto libre con máximo 100 caracteres.
12. THE ProveedorController SHALL validar que el campo `estado` es requerido, de un solo carácter y con valor `1` (activo) u `0` (inactivo); nunca otra letra ni otro número.
13. THE ProveedorController SHALL aplicar los mismos mensajes de validación en español en la creación y en la edición.

## Frontend

14. THE formulario de creación SHALL restringir el campo `ruc` a 11 dígitos en el cliente mediante `maxlength="11"`, `data-filter="digits"` y `data-validate-length="11"`, bloqueando el envío si la longitud no es exacta.
15. THE formulario de edición SHALL aplicar las mismas restricciones de frontend al campo `ruc`.
16. THE formularios de creación y edición SHALL validar en submit mediante `Validation.validate(form)` (módulo `resources/js/proveedores/validate.js`).
17. THE formulario SHALL presentar `estado` como un `select` con las opciones `1 — Activo` y `0 — Inactivo`.

## Persistencia

18. WHEN un Proveedor se crea correctamente, THE ProveedorController SHALL persistirlo en `proveedores` con los valores validados, `timestamps` generados y `estado` con valor por defecto `1` cuando no se informa.
19. WHEN un Proveedor se actualiza, THE ProveedorController SHALL persistir los valores validados ignorando la regla `unique` del propio registro.
20. THE ProveedorController SHALL normalizar el campo `ruc` eliminando todo carácter que no sea dígito antes de persistirlo.
21. THE ProveedorModel SHALL castear `estado` a entero para poder compararlo con `1` y `0` en las vistas.
22. THE ProveedorController SHALL redirigir a `proveedores.index` tras crear, actualizar o eliminar, con el Flash Message de éxito correspondiente.
23. WHEN la validación falla, THE ProveedorController SHALL redirigir de vuelta al formulario conservando los valores introducidos (`old()`) y mostrando los mensajes de error inline junto a cada campo.
24. WHEN un Proveedor se elimina, THE ProveedorController SHALL borrarlo y redirigir a `proveedores.index`.

## Modelo de datos

25. THE tabla `proveedores` SHALL definir `id` como clave primaria autoincremental (`$table->id()`).
26. THE tabla `proveedores` SHALL definir `ruc` como `char(11)` con índice único.
27. THE tabla `proveedores` SHALL definir `estado` como `char(1)` con valor por defecto `'1'`, de modo que solo pueda almacenar un número.
28. THE tabla `proveedores` SHALL incluir `timestamps`.
29. THE tabla `proveedores` SHALL definir `direccion`, `estado_tributario` y `condicion` como columnas nulables.

## Permisos y navegación

30. THE sistema SHALL crear el permiso `acceso-proveedores` en `PermissionSeeder`.
31. THE rol `Administrador` SHALL recibir el permiso `acceso-proveedores` mediante `AuthSeeder` (que sincroniza todos los permisos existentes).
32. THE rutas del módulo SHALL estar protegidas por el middleware `permission:acceso-proveedores`.
33. THE enlace "Proveedores" SHALL aparecer en el dropdown "Gestión Administrativa" del sidebar de escritorio y en su espejo del nav inferior móvil, visible solo para quienes tengan `acceso-proveedores`.
34. THE módulo Proveedor SHALL registrarse en la lista de modelos auditados de `AuditServiceProvider`.

## Fuera de alcance

- Alta de `productos.proveedor_id` y su flujo de ingreso de mercadería.
- Consulta de RUC contra la API (el campo `estado_tributario` queda como entrada manual en esta etapa).
- Relación `Proveedor::productos()`.

## Propiedades (tests)

- **Property 1:** Para cualquier cadena no vacía `s` con 11 dígitos, `POST /proveedores` con `ruc = s` persiste `s` sin alterationes.
- **Property 2:** Para cualquier cadena `c` cuya representación numérica no tenga 11 dígitos, `POST /proveedores` con `ruc = c` falla la validación de `ruc` y no persiste.
- **Property 3:** Para todo Proveedor persistido, `estado` ∈ {0, 1}.
