# Requisitos — Listado público de productos con filtros

## R1
El listado de productos por categoría (/nuestros-productos/{categoria:slug}) muestra una tabla junta de cards de producto idéntica a la de la página de marcas, sin bordes/redondeos individuales.

## R2
Encima de la tabla se muestra la categoría en letras grandes centradas.

## R3
En la esquina inferior izquierda del encabezado se muestran los filtros combinables:
- Orden alfabético (sin orden / A → Z / Z → A)
- Orden por stock (sin orden / mayor cantidad / menor cantidad)
- Búsqueda dinámica por nombre, categoría o marca (input con debounce 350 ms)

## R4
En la esquina inferior derecha del encabezado se muestra el contador "X productos — mostrando Y" (total de la categoría — visibles en la página actual).

## R5
Los filtros actúan en conjunto o por separado: cualquier combinación es válida y se conserva en los enlaces de paginación (withQueryString).

## R6
La paginación es de 20 productos por página.

## R7
Si los filtros devuelven cero resultados se muestra un estado vacío específico de búsqueda con enlace "Limpiar filtros".

## R8
Los filtros se envían automáticamente al cambiar un select o al escribir en el campo de búsqueda (debounce).
