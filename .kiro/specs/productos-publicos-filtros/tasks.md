# Tareas — Listado público de productos con filtros

- [x] Actualizar `PaginaInicioController::productosCategoria` para aceptar `letra`, `stock`, `q` y usar `paginate(20)->withQueryString()` con `$totalCategoria`.
  _Requisitos: R1, R5, R6_

- [x] Reescribir `resources/views/publica/productos-categoria.blade.php` sección 2: encabezado con categoría centrada, filtros (izq), contador (der), tabla junta y paginación.
  _Requisitos: R1, R2, R3, R4, R5, R6, R7_

- [x] Actualizar `card-producto-grande.blade.php` para aceptar `$junta` (suprime borde/redondeo, agrega `h-full`).
  _Requisitos: R1_

- [x] Crear `resources/js/publica/productos-filtros.js` con debounce y auto-submit; registrar en `vite.config.js`.
  _Requisitos: R8_

- [x] Agregar tests de ejemplo alistando: paginación de 20, búsqueda por nombre y marca, orden alfabético asc/desc, orden por stock, filtros combinables, estados vacíos.
  _Requisitos: R1–R8_

- [x] Agregar tests de propiedad (300 iteraciones): límite de 20 por página, búsqueda inclusiva, filtros válidos retornan ≤20.
  _Requisitos: R1–R8_
