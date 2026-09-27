# Requisitos — Compras e Ingreso de Mercadería (Hito 1: Fases 0 a 3)

**Feature:** `compras-ingreso-mercaderia`
**Estado:** en curso
**Alcance:** Fases 0 a 3 del plan de once fases. Deja el sistema con un módulo de
compras funcionando de punta a punta: catálogo desvinculado de existencias, compra
como operación comercial con estados, recepción que genera entrada de inventario
registrada en Kardex, y pago registrado en caja.
**Documento de trabajo asociado:** `docs/plan-compras-ingreso-mercaderia.md`

## Principio rector

> Ningún usuario modifica directamente la existencia de un producto. La existencia es
> el resultado de las operaciones de inventario registradas por el sistema.

## Decisiones

| # | Decisión | Fundamento |
|---|---|---|
| D1 | Naming en español: `compras`, `detalle_compras`, `costo_unitario`, `Compra`, `DetalleCompra` | El proyecto es íntegramente español y `AGENTS.md` lo exige. El documento original usa inglés genérico como pseudocódigo, no como requisito. |
| D2 | `precio_compra` pasa a opcional y se autocompleta al recibir una compra. Se representa como `0`, no `null` | La columna es `NOT NULL` y `ReporteInventarioService` la usa en aritmética. Sin compra, el costo real es desconocido. |
| D3 | `stock_minimo` se aplaza a la Fase 9 | Resuelve la contradicción del documento original, que lo incluye en la sección 4 pero lo aplaza en el plan por fases. |
| D4 | La compra exige caja activa al recibirse y registra el egreso | Toda operación que mueve dinero en este sistema exige caja. Sin esto, el pago a proveedores quedaría fuera de la rendición. |
| D5 | Alcance: Fases 0 a 3 | Entrega el núcleo crítico de la feature. |
| D6 | `movimientos_kardex.fuente` deja de ser ENUM y pasa a `string(30)` | Cada fuente nueva exigiría un `->change()`. Convertir una vez evita repetirlo en Fases 3, 5 y 6. Se compensa con constantes en `KardexService`. |
| D7 | `detalle_compras` con `unique(compra_id, producto_id)` | `detalle_ventas` no lo tiene, y por eso una venta con el mismo producto repetido genera varios decrementos con el mismo `origen_id`. No se repite el defecto. |
| D8 | En Hito 1 solo se anula una compra en `borrador` | Anular una compra `recibida` es la Fase 7. El egreso de caja ya registrado no se puede compensar si la caja está cerrada. Una reversión a medias es peor que bloquearla. |

## Desacoplamiento del catálogo

Los requisitos de esta sección describen lo que hace la **aplicación por interfaz**.
Los seeders de demostración son una excepción consciente hasta la Fase 3.9, que los
sustituye por compras recibidas (requisitos 63 y 64). Un seeder no es un usuario: si
`ProductoSeeder` sembrara existencias sin una compra que las justifique, las ventas de
demostración se consumirían sobre mercadería que nunca se recibió.

1. WHEN un usuario crea un producto, THE ProductoController SHALL persistirlo con `stock = 0` e `inventario = 0`.
2. THE ProductoController SHALL no exigir ningún campo de cantidad al crear un producto.
3. THE ProductoController SHALL no registrar ningún movimiento en Kardex al crear un producto, porque un producto que nace en el catálogo no recibió mercadería.
4. THE ProductoController SHALL no permitir modificar `stock` ni `inventario` desde el endpoint de actualización del producto.
5. THE ProductoController SHALL ignorar cualquier valor de `stock` o `inventario` recibido en una solicitud de actualización, sin lanzar error y sin escribir en base de datos.
6. THE formulario de alta de producto SHALL no contener campos de cantidad.
7. THE formulario de edición de producto SHALL no contener campos de cantidad, y en su lugar mostrará el stock actual como referencia de solo lectura.

## Precio de compra

8. THE ProductoController SHALL aceptar `precio_compra` ausente, vacío o igual a `0`, y persistirlo como `0`.
9. WHEN `precio_compra` es mayor que `0`, THE ProductoController SHALL exigir que `precio_venta` sea mayor o igual a `precio_compra`.
10. WHEN `precio_compra` es `0` o está ausente, THE ProductoController SHALL exigir únicamente que `precio_venta` sea mayor que `0`.
11. THE ProductoController SHALL aplicar las mismas reglas en la creación y en la edición.
12. THE formularios de alta y edición SHALL marcar el campo `precio_compra` como opcional y SHALL informar que se completa con el costo de la última compra.

## Cabecera de compra

13. THE tabla `compras` SHALL almacenar `id`, `correlativo` único con formato `CMP-####`, `proveedor_id`, `fecha`, `tipo_documento`, `numero_documento`, `estado`, `subtotal`, `total`, `observaciones`, `user_id`, `caja_id`, `egreso_caja_id` y `fecha_recepcion`.
14. THE columna `estado` SHALL admitir exclusivamente `borrador`, `recibida` o `anulada`.
15. THE columna `correlativo` SHALL ser única en la tabla `compras`.
16. THE columna `proveedor_id` SHALL referenciar a `proveedores` con restricción `restrict`.
17. THE columna `producto_id` de `detalle_compras` SHALL referenciar a `productos` con restricción `restrict`, de modo que un producto usado en una compra no pueda borrarse.

## Detalle de compra

18. THE tabla `detalle_compras` SHALL almacenar `compra_id`, `producto_id`, `cantidad`, `costo_unitario` y `subtotal`.
19. THE tabla `detalle_compras` SHALL imponer la restricción de unicidad sobre `(compra_id, producto_id)`.
20. THE `CompraController` SHALL calcular `subtotal` y `total` en el servidor a partir de la cantidad y el costo unitario de cada línea, sin confiar en los valores enviados por el formulario.
21. THE `subtotal` de una línea SHALL ser exactamente `cantidad × costo_unitario`.
22. THE `total` de la compra SHALL ser exactamente la suma de los subtotales de sus líneas, sin desglose de impuesto.
23. THE `CompraController` SHALL exigir al menos una línea de detalle en el alta y en la edición.

## Estados y transiciones

24. THE CompraController SHALL crear toda compra nueva en estado `borrador`.
25. WHILE una Compra está en estado `borrador`, THE CompraController SHALL permitir editarla, borrarla y anularla.
26. WHILE una Compra está en estado `recibida`, THE CompraController SHALL no permitir editarla, borrarla ni anularla.
27. WHILE una Compra está en estado `anulada`, THE CompraController SHALL no permitir editarla ni borrarla.
28. THE CompraController SHALL rechazar con un mensaje explícito cualquier intento de modificar el contenido de una compra `recibida`, comunicando que la reversión no está disponible en esta fase.

## Recepción

29. WHEN se recibe una Compra en estado `borrador`, THE CompraController SHALL exigir una caja abierta, y en su defecto devolver `error_caja` sin aplicar ningún cambio.
30. THE CompraController SHALL verificar que ningún producto del detalle esté inactivo antes de recibir.
31. THE CompraController SHALL asignar un correlativo `CMP-####` a la compra si aún no lo tiene, calculado dentro de la misma transacción que la recepción.
32. THE CompraController SHALL bloquear con `lockForUpdate` la compra y cada producto del detalle durante la recepción.
33. WHEN se recibe una Compra, THE CompraController SHALL incrementar el `stock` de cada producto del detalle por la cantidad de su línea.
34. WHEN se recibe una Compra, THE CompraController SHALL asignar al campo `inventario` de cada producto el valor resultante de `stock`, manteniendo el ciclo de inventario.
35. WHEN se recibe una Compra, THE CompraController SHALL registrar una entrada en Kardex por cada línea, con `fuente = 'compra'`, `origen_id` igual al correlativo de la compra, el `stock_despues` resultante y el costo unitario de la línea.
36. WHEN se recibe una Compra, THE CompraController SHALL actualizar el `precio_compra` de cada producto del detalle con el costo unitario de su línea.
37. WHEN se recibe una Compra, THE CompraController SHALL registrar un egreso en caja mediante `CajaService::registrarEgreso` con un importe igual al total de la compra.
38. WHEN se recibe una Compra, THE CompraController SHALL guardar en la compra el `caja_id` y el `egreso_caja_id` correspondientes, y marcar el estado como `recibida` con su `fecha_recepcion`.
39. THE CompraController SHALL ejecutar todos los pasos de la recepción dentro de una única transacción, de modo que un fallo en cualquier paso revierta el stock, el inventario, el Kardex, el precio de compra, el egreso de caja y el estado.
40. THE CompraController SHALL rechazar una segunda recepción de la misma compra con un error de estado, sin duplicar existencias ni Kardex.
41. THE CompraController SHALL informar al operador de los productos afectados cuyo `precio_venta` quedara por debajo del nuevo `precio_compra`, sin bloquear la operación.

## Kardex

42. THE columna `movimientos_kardex.fuente` SHALL aceptar el valor `compra` y SHALL dejar de ser un tipo ENUM, quedando como `string(30)`.
43. THE KardexService SHALL definir las fuentes válidas como constantes de clase en lugar de depender del ENUM de la base de datos.
44. THE KardexService SHALL seguir la convención de no modificar el stock: solo materializa el movimiento con el antes y el después que le entrega el llamador.
45. THE KardexService SHALL calcular el correlativo de un movimiento dentro de la transacción que genera el movimiento.

## Caja

46. THE tabla `egresos_caja` SHALL admitir los tipos de pago `efectivo`, `yape`, `transferencia` y `tarjeta`, porque una compra a proveedor Lima no se paga por Yape.
47. THE CompraController SHALL reutilizar `CajaService::registrarEgreso` y no SHALL crear un mecanismo paralelo de registro de pagos.
48. THE CompraController SHALL preservar la invariante existente de que el egreso de caja solo puede registrarse sobre una caja abierta.

## Auditoría

49. THE AuditServiceProvider SHALL auditar el modelo `Compra` en su lista de modelos.
50. THE AuditService SHALL excluir el modelo `DetalleCompra` de la auditoría, por ser un pivote y no una entidad de negocio, junto a `MovimientoKardex`.
51. THE AccionesAuditoriaController SHALL etiquetar las acciones `recibir compra` y `anular compra` para su filtro.

## Permisos y navegación

52. THE PermissionSeeder SHALL definir el permiso `acceso-compras` dentro del bloque de Inventario y Servicios.
53. THE rutas de compras SHALL exigir autenticación y el permiso `acceso-compras`.
54. THE menú lateral SHALL enlazar al módulo de Compras dentro de "Gestión de productos", porque una compra es una operación de inventario y no un dato maestro.

## Interfaz

55. THE vista de listado de compras SHALL usar `paginate(10)`, wrapper `overflow-x-auto`, cabeceras `py-6` y celdas `py-8`, y SHALL mostrar un estado vacío cuando no existan compras.
56. THE vista de detalle de compra SHALL mostrar la cabecera, las líneas, el total, el estado y el botón de recepción con su advertencia de irreversibilidad.
57. THE vista de recepción SHALL advertir que la operación no puede revertirse en esta fase.
58. Los formularios y vistas de compras SHALL usar las clases semánticas de modo oscuro definidas en `resources/css/app.css`, sin negro puro.
59. Los módulos JS de compras SHALL estar declarados en el array `input` de `vite.config.js` y cargados con `@vite([...])` al final de `@section('content')` de cada vista.
60. THE Blade de compras SHALL no contener bloques `<script>` de comportamiento, salvo inicialización de datos antes de `@vite`.

## Transición

61. WHILE el modal de reposición de stock siga vigente, THE vista de listado de productos SHALL etiquetarlo como reposición provisional e indicar que el abastecimiento formal es el módulo de Compras.
62. THE botón "Actualizar stock" SHALL permanecer operativo en Hito 1; su eliminación es la Fase 4.
63. THE DatabaseSeeder SHALL ordenar la cadena de datos de demostración como productos con stock cero, compras recibidas, Kardex de entrada, ventas, cambios de aceite y Kardex de salidas, de modo que ninguna demostración produzca stock negativo.
64. THE KardexSeeder SHALL dejar de fabricar entradas `INV-` y reconstruir la secuencia a partir de las compras recibidas.

## Fuera de alcance de Hito 1

65. THIS feature SHALL no incluir ajustes ni mermas de inventario (Fase 5).
66. THIS feature SHALL no incluir `costo_unitario` ni `costo_total` en la tabla de Kardex (Fase 6), aunque el costo nazca en `detalle_compras`.
67. THIS feature SHALL no permitir anular una compra recibida (Fase 7).
68. THIS feature SHALL no extender el bloqueo de fila a la venta ni al cambio de aceite (Fase 8).
69. THIS feature SHALL no agregar `stock_minimo` como criterio de abastecimiento (Fase 9).
70. THIS feature SHALL no implementar valorización por costo promedio ponderado (Fase 10).
71. THIS feature SHALL no retirar el modal de reposición de stock (Fase 4).
