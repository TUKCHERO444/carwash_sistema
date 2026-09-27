# Plan de implementación — Compras e Ingreso de Mercadería (Hito 1: Fases 0 a 3)

**Propósito:** documento de trabajo que fija las decisiones tomadas y detalla las tareas
concretas de las primeras fases, para dejar constancia del criterio antes de escribir
código y para que cualquier persona pueda retomar el trabajo sin reconstruir el
razonamiento.

**Origen:** `docs/nueva_implmentacion_compra.txt` (propuesta de arquitectura) en
contraste con `docs/flujo-productos-inventario-stock.md` (estado actual verificado del
sistema).

**Alcance:** Fases 0 a 3 del plan de once fases. Deja el sistema con un módulo de
compras funcionando de punta a punta: catálogo desvinculado de existencias, compra
como operación comercial con estados, recepción que genera entrada de inventario
registrada en Kardex, y cobro registrado en caja.

**Fuera de alcance:** ver §8.

---

## 1. Principio rector

> Ningún usuario modifica directamente la existencia de un producto. La existencia es
> el resultado de las operaciones de inventario registradas por el sistema.

Hoy esa regla se incumple en un camino: la edición de la ficha de producto escribe
`stock` e `inventario` desde el formulario sin generar ningún movimiento de Kardex.
Las Fases 0 y 1 cierran ese camino. La Fase 2 abre la vía formal. La Fase 3 la conecta
con el inventario y con la caja.

---

## 2. Decisiones tomadas

### 2.1 Confirmadas

| # | Decisión | Motivo |
|---|---|---|
| D1 | **Naming en español:** `compras`, `detalle_compras`, `costo_unitario`, `Compra`, `DetalleCompra` | El proyecto es íntegramente español (`ventas`, `detalle_ventas`, `precio_compra`, `Proveedor`) y `AGENTS.md` lo exige. El documento original usa inglés genérico como pseudocódigo, no como requisito. |
| D2 | **`precio_compra` pasa a opcional y se autocompleta al recibir compra** | Sin compra, el costo real es desconocido. El valor pasa a ser "último costo de referencia", no historial. |
| D3 | **`stock_minimo` se aplaza a la Fase 9** | Resuelve la contradicción del documento original, que lo incluye en la sección 4 pero lo aplaza en el plan por fases. Se prioriza el criterio del 75 % ya existente para no romper la interfaz actual. |
| D4 | **La compra exige caja activa al recibirse y registra el egreso** | Toda operación que mueve dinero en este sistema exige caja. Una compra es un egreso. Sin esto, el dinero pagado a proveedores quedaría fuera de la rendición de caja. |
| D5 | **Alcance: Fases 0 a 3** | Entrega el núcleo crítico de la feature. |

### 2.2 Técnicas, con su fundamento

| # | Decisión | Fundamento |
|---|---|---|
| D6 | **`fuente` del Kardex deja de ser ENUM y pasa a `string(30)`** | Cada fuente nueva exigiría un `->change()`. Convertir una vez a `string` evita repetirlo en Fases 3, 5 y 6, y esquiva el riesgo de SQLite in-memory. Se pierde validación a nivel base de datos, que se compensa con constantes en `KardexService`. |
| D7 | **`detalle_compras` con `unique(compra_id, producto_id)`** | Sigue el patrón de `cambio_productos`. `detalle_ventas` **no** lo tiene, y por eso una venta con el mismo producto repetido genera varios decrementos y varios movimientos con el mismo `origen_id`. No se repite ese defecto. |
| D8 | **En Hito 1 solo se anula una compra en `borrador`** | Anular una compra `recibida` es la Fase 7, y es delicada: el egreso de caja ya registrado no se puede compensar si la caja ya está cerrada (`CajaService::registrarEgreso` lanza excepción sobre caja cerrada). Una reversión a medias sería peor que bloquearla. |

### 2.3 Detalle de D2: cero, no nulo

`precio_compra` se representa como **`0`, no como `null`**. La columna es
`decimal(10,2) NOT NULL` y el reporte de inventario la usa en aritmética
(`ReporteInventarioService::stockActual`, línea 49: valorizado = `stock × precio_compra`).
Un `null` rompería ese cálculo.

Consecuencia en la validación: la regla `precio_venta >= precio_compra` solo debe
aplicarse cuando `precio_compra > 0`. Con `precio_compra = 0` basta con
`precio_venta > 0`.

### 2.4 Detalle de D4: el egreso de caja

Se reutiliza `CajaService::registrarEgreso`, no se crea un mecanismo paralelo. El egreso
usa:

- **Descripción**: identifica la compra y al proveedor de forma legible en el historial.
- **`tipo_pago`**: el enum actual es `(efectivo, yape)`, insuficiente para pago a
  proveedor, que típicamente es transferencia bancaria. **Se amplía** (ver Tarea 0.7).

`CajaService::calcularResumen` ya suma `egresos_caja.monto` en el balance, por lo que el
egreso de la compra se refleja automáticamente en el resumen de caja sin cambios.

---

## 3. Esquema objetivo

### 3.1 Tabla `compras`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | autoincremental | PK |
| `correlativo` | `string(20)` **unique** | Formato `CMP-0001`. Generado al crear, dentro de la transacción de recepción si aún no existe. |
| `proveedor_id` | FK → `proveedores`, `restrict` | Solo proveedores con `estado = 1`. |
| `fecha` | `date` | Fecha de la compra. |
| `tipo_documento` | `string(20)`, nullable | `factura`, `boleta`, `guia`, `nota_credito`, `sin_documento`. |
| `numero_documento` | `string(50)`, nullable | |
| `estado` | ENUM `(borrador, recibida, anulada)` | Default `borrador`. |
| `subtotal` | `decimal(10,2)` | Suma de subtotales del detalle. |
| `total` | `decimal(10,2)` | Por decisión del documento original, `total = Σ subtotales` (sin desglose de IGV). |
| `observaciones` | `text`, nullable | |
| `user_id` | FK → `users`, `restrict` | Quién registró. |
| `caja_id` | FK → `cajas`, nullable, `restrict` | Se llena al recibir. |
| `egreso_caja_id` | FK → `egresos_caja`, nullable | Traza del pago. |
| `fecha_recepcion` | `timestamp`, nullable | |
| `timestamps` | | |

Índices: `estado`, `fecha`, `proveedor_id`.

### 3.2 Tabla `detalle_compras`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | autoincremental | PK |
| `compra_id` | FK → `compras`, `cascade` | |
| `producto_id` | FK → `productos`, `restrict` | `restrict` impide borrar un producto usado en una compra. |
| `cantidad` | `integer` | ≥ 1. |
| `costo_unitario` | `decimal(10,2)` | > 0. **Es la primera aparición del costo real.** |
| `subtotal` | `decimal(10,2)` | `cantidad × costo_unitario`. |

`unique(compra_id, producto_id)` (D7).

### 3.3 Cambios en tablas existentes

| Tabla | Cambio |
|---|---|
| `productos` | Sin cambios de esquema. `precio_compra` admite `0`. `stock` e `inventario` salen de los formularios. |
| `egresos_caja` | `tipo_pago` amplía el ENUM a `efectivo, yape, transferencia, tarjeta`. |
| `movimientos_kardex` | `fuente`: ENUM(3) → `string(30)`. Se conserva `inventario` como valor histórico durante la transición. |

### 3.4 Nota sobre IGV

El documento original define `total = Σ subtotales`, sin impuesto. En el mercado
peruano las compras de bienes están gravadas con IGV, y el operador necesitará poder
registrar el total que efectivamente pagó. **En Hito 1 no se implementa desglose de
IGV.** Se registra el total tal como se recibe, sin desagregación. Si más adelante se
requiere separar base imponible e impuesto, será un campo adicional en la cabecera.

---

## 4. Fase 0 — Preparación

Objetivo: cerrar las ambigüedades y tapar el camino que permite alterar stock desde la
ficha de producto.

### Tarea 0.1 — Spec de la feature

Crear `.kiro/specs/compras-ingreso-mercaderia/` con `requirements.md`, `design.md` y
`tasks.md`, siguiendo el workflow `requirements-first` del proyecto. Debe incluir:

- Las 8 decisiones (D1–D8) como requisitos explícitos.
- La matriz de movimientos: operación → efecto en stock → movimiento en Kardex → fuente.
- Las 8 limitaciones conocidas de Hito 1, para que nadie las descubra como sorpresa.
- Los 3 casos de prueba de propiedades: subtotal exacto, recepción atómica, recepción
  idempotente.

`AGENTS.md` exige leer el `tasks.md` de una spec antes de implementar o modificar una
feature. Esta spec se escribe primero que el código.

### Tarea 0.2 — Tests de caracterización en rojo

Antes de tocar la validación, escribir los tests que hoy fallan y que describen el
comportamiento deseado. Así se prueba que el cambio es el que los hace pasar.

- Editar un producto **no** puede modificar `stock` ni `inventario`.
- `precio_compra = 0` es un alta válida.
- `precio_venta` sin `precio_compra` conocido solo exige `> 0`.

### Tarea 0.3 — Cerrar el paso directo en el controlador

`app/Http/Controllers/ProductoController.php`, método `update` (líneas 170-194):

- Quitar `stock` e `inventario` de las reglas de validación (líneas 175-176).
- Quitar ambas claves del arreglo de datos a persistir (líneas 190-191).
- Mantener la foto, la categoría, la marca y los precios.

### Tarea 0.4 — Cerrar el paso directo en el formulario

`resources/views/productos/edit.blade.php`: eliminar el bloque de `stock` (líneas
172-180) y el bloque de `inventario` (líneas 193-201). Mostrar en su lugar una
referencia de solo lectura que indique el stock actual y el porcentaje consumido del
ciclo, para que el operador no pierda visibilidad.

### Tarea 0.5 — `precio_compra` opcional

`ProductoController`, en `store` (líneas 98-113) y `update` (líneas 170-183):

- `precio_compra`: pasa a `nullable|min:0`, con default `0` en persistencia.
- La regla `gte:precio_compra` se aplica **solo** si el valor recibido es mayor que 0.
- Conservar el mensaje *"El precio de venta no puede ser inferior al precio de
  compra."* para el caso en que sí aplica.

### Tarea 0.6 — Corregir la factory

`database/factories/ProductoFactory.php` (líneas 24-25): hoy `stock` e `inventario` se
generan de forma independiente, lo que puede producir un producto con más existencias
que las declaradas en su ciclo. Ambos pasan a `0` por defecto, con estados alternativos
para los tests que necesiten valores.

### Tarea 0.7 — Ampliar `tipo_pago` de egresos

Nueva migración que altere el ENUM de `egresos_caja.tipo_pago` para incluir
`transferencia` y `tarjeta`. Verificar que `CajaController::registrarEgreso` (línea
100) y sus tests siguen funcionando.

---

## 5. Fase 1 — El producto deja de inventar existencias

Objetivo: separar catálogo de inventario. Un producto nuevo entra al catálogo con cero
existencias y se repone mediante compra.

### Tarea 1.1 — Quitar el campo del alta

`resources/views/productos/create.blade.php`, bloque `inventario` (líneas 171-179). El
formulario queda con: nombre, descripción, categoría, marca, precio de compra
(opcional), precio de venta, stock mínimo diferido, foto y estado.

### Tarea 1.2 — Alta con stock cero

`ProductoController::store` (líneas 98-148):

- Se elimina la regla `inventario => required|integer|min:1` (línea 103) y sus tres
  mensajes asociados.
- Se persiste con `stock = 0` e `inventario = 0` (reemplazando las líneas 127-128).
- **Se elimina la generación del correlativo `INV-XXXX` y el movimiento de entrada de
  Kardex** (líneas 135-143). Un producto que nace en el catálogo no genera un
  movimiento de inventario, porque no recibió mercadería.

Se conserva: validación de precios, subida de foto a Cloudinary fuera de la
transacción, incremento de `contador_productos` de la categoría, mensaje de éxito.

### Tarea 1.3 — Reescribir `ProductoInventarioTest`

`tests/Feature/ProductoInventarioTest.php` (7 casos) afirma hoy que `inventario` es
obligatorio y mínimo 1, y que el formulario exige ese mínimo. **Esos tres casos se
invierten:** el alta sin existencias debe ser válida y el formulario no debe contener
los campos de cantidad.

### Tarea 1.4 — Reescribir el test de Kardex de alta

`tests/Feature/KardexTest.php:119`
(`test_producto_store_registers_kardex_entrada_inventario`): se convierte en
`test_producto_store_no_registers_kardex`, que verifica que el alta **no** genera
movimiento alguno.

### Tarea 1.5 — Actualizar los tests de Cloudinary

`ProductoCloudinaryTest`, `MarcaCloudinaryTest` y `CambioAceiteCloudinaryTest` envían
el campo `inventario` en sus payloads de alta. Se retira de los tres.

### Tarea 1.6 — Actualizar `ProductoSeeder`

`database/seeders/ProductoSeeder.php` (líneas 129-150): los productos se crean con
cero existencias. El seeder deja de calcular el stock consumido y el badge de alerta,
que pasarán a generarse por la cadena de compras y Kardex (ver Tarea 3.9).

---

## 6. Fase 2 — Módulo de compras

Objetivo: la compra existe como operación comercial, independiente del inventario.

### Tarea 2.1 — Migraciones

`create_compras_table` y `create_detalle_compras_table` según §3.1 y §3.2.

### Tarea 2.2 — Modelos

- `app/Models/Compra.php`: `$fillable`, casts de los decimales, relación `proveedor()`,
  `detalles()`, `usuario()`, `caja()`, `egresoCaja()`, y el accessor del subtotal
  recalculable a partir del detalle.
- `app/Models/DetalleCompra.php`: relación `compra()` y `producto()`.
- `app/Models/Proveedor.php`: agregar `compras()` como `hasMany`.
- Ojo con el pluralizador: `Proveedor` ya declara `$table = 'proveedores'` porque
  `Str::singular('proveedores')` devuelve `proveedore`. La misma trampa aplica a las
  rutas: el resource necesita `->parameters(['compras' => 'compra'])`.

### Tarea 2.3 — `CompraController`

| Método | Reglas |
|---|---|
| `index` | `paginate(10)`, ordenado por fecha descendente y luego correlativo. |
| `create` | Proveedores con `estado = 1`, productos activos. |
| `store` | Valida cabecera y detalle; calcula `subtotal` y `total` **en el servidor** a partir de cantidad y costo (nunca confía en el formulario); estado `borrador`. |
| `edit` | Solo `borrador`. |
| `update` | Solo `borrador`. |
| `destroy` | Solo `borrador`. Un borrador no dejó rastro, así que borrarlo es seguro. |
| `recibir` | Ver Fase 3. |
| `anular` | Solo `borrador` (D8). |

Validaciones de cabecera: `proveedor_id` requerido y existente; `fecha` requerida y
válida; `tipo_documento` y `numero_documento` opcionales; al menos una línea de
detalle. Validaciones de línea: producto existente, cantidad entera ≥ 1, costo unitario
numérico > 0.

### Tarea 2.4 — Máquina de estados

| Estado actual | Transiciones permitidas en Hito 1 |
|---|---|
| `borrador` | → `recibida` (Fase 3), → `anulada`, → borrado |
| `recibida` | ninguna: ni edición, ni borrado, ni anulación |
| `anulada` | ninguna (terminal) |

Una compra `recibida` que el operador necesite revertir **no tiene camino en Hito 1**.
La interfaz debe comunicar esto con claridad, no con un error genérico.

### Tarea 2.5 — Vistas

`index`, `create`, `edit`, `show`, siguiendo `AGENTS.md` y las guías del proyecto:
clases semánticas de dark mode, `overflow-x-auto` en el wrapper de tabla, cabeceras
`py-6`, celdas `py-8`, badges por estado, estado vacío, mensajes de error inline con
`old()`.

`show` es la vista más importante: muestra cabecera, líneas, total, estado, y el botón
de recepción con su advertencia de irreversibilidad.

### Tarea 2.6 — Frontend

`resources/js/compras/{index,create,edit}.js` siguiendo el patrón de
`resources/js/ventas/create.js`: tabla de líneas dinámicas con búsqueda de producto,
autocomputado de subtotal y total, y validación de formulario.

**Obligatorio según `AGENTS.md`:** cada módulo usado por una vista debe estar en el
array `input` de `vite.config.js` **y** cargado con `@vite([...])` al final de
`@section('content')`. Una omisión falla en silencio en producción.

### Tarea 2.7 — Permiso y navegación

- `acceso-compras` en `database/seeders/PermissionSeeder.php`, dentro del bloque de
  Inventario y Servicios.
- Rutas bajo `auth` + `permission:acceso-compras`.
- Enlace en el menú, dentro de **"Gestión de productos"**, junto a Productos, Categorías
  y Marcas. Es una operación de inventario, no un dato maestro. Se extiende
  `$productManagementActive` en `resources/views/layouts/app.blade.php` (línea 15) para
  incluir `compras.*`, en escritorio y móvil.

### Tarea 2.8 — Auditoría

- `Compra::class` se registra en `$modelos` de `app/Providers/AuditServiceProvider.php`.
- `DetalleCompra::class` se **excluye** en `AuditService::EXCLUIDOS`, junto a
  `MovimientoKardex`: es un pivote, no una entidad de negocio. Sin una entidad en la
  lista de `$modelos` no se audita; sin una entrada en `EXCLUIDOS` produce ruido.
- Etiquetas de acción `recibir compra` y `anular compra`, agregadas al filtro de
  `app/Http/Controllers/AccionesAuditoriaController.php` (líneas 35-50).

### Tarea 2.9 — Datos de prueba

- `database/factories/CompraFactory.php` con estado por defecto `borrador` y estados
  alternativos `recibida` y `anulada`.
- `database/factories/DetalleCompraFactory.php`.
- `database/seeders/CompraSeeder.php`, idempotente, registrado en `DatabaseSeeder`
  después de `ProveedorSeeder` y `ProductoSeeder`.

---

## 7. Fase 3 — Integración con inventario y caja

Objetivo: recibir una compra genera la entrada de inventario correspondiente, todo
atómico y trazable.

### Tarea 3.1 — Spike de migración (bloqueante)

**Antes de escribir cualquier código de la Fase 3**, verificar que alterar `fuente` de
ENUM a `string` funciona en SQLite in-memory, que es como corre la suite de tests
(`phpunit.xml`). Un cambio de tipo de columna en SQLite implica recrear la tabla, y la
tabla tiene claves foráneas hacia `productos` y `users`.

Si el spike falla, las alternativas son: mantener ENUM y añadir `compra` con una
sentencia cruda condicionada por driver, o la inversa, conservar ENUM y resolver el
detalle en el modelo. **No se avanza a la Tarea 3.2 sin este resultado.**

### Tarea 3.2 — Migración de Kardex

`fuente` pasa a `string(30)`. Se conservan los tres valores existentes
(`venta`, `cambio_aceite`, `inventario`) y se añade `compra`. Los movimientos
históricos con `fuente = 'inventario'` **no se reetiquetan**: son datos reales y su
reinterpretación pertenece a la Fase 5, cuando exista el módulo de ajustes con motivos.

### Tarea 3.3 — `KardexService`

- Reemplazar el ENUM por constantes de clase.
- Añadir el parámetro de costo al registro de movimiento. En Hito 1 solo las entradas
  por compra llevan costo; el resto lo deja en `0` o `null` hasta la Fase 6.
- Añadir un método de registro de entrada por compra, análogo a los existentes.

Se mantiene la convención ya establecida de que **el servicio no modifica el stock**:
solo materializa el movimiento con el antes y el después que le pasa el llamador.

### Tarea 3.4 — Correlativo `CMP-XXXX`

Se sigue el patrón existente de `VentaController` (líneas 108-109): correlativo derivado
del siguiente identificador disponible, formateado a cuatro dígitos. Se calcula
**dentro de la transacción de recepción** cuando la compra aún no lo tiene, respetando
la convención documentada en `KardexService` (líneas 68-71).

`origen_id` es `string(20)`: `CMP-0001` cabe sin problema.

### Tarea 3.5 — Recibir compra

Una única transacción. El orden importa, porque el registro en caja y el descuento de
inventario deben ser consistentes entre sí:

1. Bloquear la compra y cada producto con `lockForUpdate`. Esto resuelve la condición
   de carrera que el sistema ya sufre en venta (§9 del análisis), y evita heredarla en
   la vía nueva.
2. Verificar que la caja esté abierta. Si no, devolver `error_caja`, igual que
   `VentaController::store` (líneas 74-77).
3. Verificar que ningún producto del detalle esté inactivo.
4. Generar el correlativo si falta.
5. Por cada línea: sumar la cantidad al `stock`, leer el valor resultante y asignarlo
   también a `inventario` (mantiene el ciclo, decisión §7 del documento original), y
   registrar la entrada de Kardex con `fuente = 'compra'` y origen `CMP-XXXX`.
6. Actualizar `precio_compra` del producto con el costo unitario de la línea.
7. Registrar el egreso en caja vía `CajaService::registrarEgreso`.
8. Marcar la compra como `recibida` con `fecha_recepcion`, y guardar `caja_id` y
   `egreso_caja_id`.
9. Anotar la acción de auditoría `recibir compra`.

Si cualquier paso falla, todo revierte. La invariante que se preserva es la que el
sistema ya tiene: **operación de stock y registro en Kardex son atómicos.**

### Tarea 3.6 — Advertencia de margen

Tras actualizar los `precio_compra`, se compara el `precio_venta` de cada producto
afectado. Si el nuevo costo supera el precio de venta, la respuesta lo informa
explícitamente al operador. No bloquea la operación, pero evita que el margen roto
pase inadvertido.

### Tarea 3.7 — Bloqueo del modal de reposición

El modal "Actualizar stock" (`ProductoController::updateStock`, líneas 243-282) deja de
ser el camino de abastecimiento, pero **permanece operativo** en Hito 1: su eliminación
es la Fase 4. Se le añade una etiqueta visible en
`resources/views/productos/index.blade.php` (líneas 254-289) indicando que es una
reposición provisional y que el abastecimiento formal es el módulo de Compras.

Durante la transición coexisten dos vías de entrada de existencias. Eso se comunica en
pantalla, no se deja implícito.

### Tarea 3.8 — Corregir el origen del cambio de aceite en el seeder

`database/seeders/KardexSeeder.php:67` usa `$cambio->automotor_id` (un entero) como
`origen_id`, mientras que el código de producción usa la placa. Son dos implementaciones
distintas de la misma idea. Unificar en el seeder al patrón de producción, aunque el
cambio real de identificador a `CAM-XXXX` con placa es de la Fase 6.

### Tarea 3.9 — Reordenar la cadena de seeders (obligatoria)

Hoy el orden es: productos con stock y con entrada `INV-` → ventas → cambios de aceite →
Kardex reconstruido. Con productos naciendo en cero, ese orden produce salidas sobre
stock inexistente y `stock_despues` negativos.

Nuevo orden:

1. Productos con `stock = 0`.
2. Compras en estado `recibida`, que generan la carga inicial.
3. Kardex de entrada por compra.
4. Ventas y sus detalles.
5. Cambios de aceite confirmados y sus productos.
6. Kardex de salidas.

`KardexSeeder` (líneas 22-33) deja de fabricar entradas `INV-` y reconstruye la
secuencia a partir de las compras recibidas.

Sin esta tarea, los datos de demostración quedan incoherentes y los reportes de
inventario muestran valores sin sentido.

### Tarea 3.10 — Orden de despliegue de migraciones

Por la dependencia de claves foráneas, el orden en producción es:

1. `egresos_caja.tipo_pago` ampliado.
2. `compras` y `detalle_compras`.
3. `movimientos_kardex.fuente` a `string`.

Ninguna migración elimina datos. La ampliación del ENUM y el cambio de tipo son
operaciones aditivas o de reescritura de tipo, sin pérdida de información.

---

## 8. Fuera de alcance de Hito 1

| Fase | Contenido | Por qué queda afuera |
|---|---|---|
| 4 | Retirar el modal de reposición y convertirlo en "Ajustar inventario" | Requiere que el módulo de ajustes exista primero (Fase 5). |
| 5 | Ajustes y mermas: ajuste positivo, ajuste negativo, merma, daño, conteo físico | Es el mecanismo que cubre el vacío de salidas no comerciales. Depende de las fuentes `ajuste` y `merma` en Kardex. |
| 6 | `costo_unitario` y `costo_total` en el Kardex; identificadores `CAM-0001` y `AJU-0001` | El dato de costo ya nace en `detalle_compras` en Hito 1, así que no se pierde nada. La Fase 6 lo proyecta al Kardex. |
| 7 | Anular una compra recibida, con salida compensatoria y compensación de caja | Requiere definir la reversión de un egreso en caja, que tiene el problema de la caja cerrada. |
| 8 | `lockForUpdate` en venta y cambio de aceite | El bloqueo en la vía de compra sí se incluye (3.5), porque es la vía nueva. Extenderlo a las otras dos es trabajo aparte. |
| 9 | `stock_minimo` como criterio de abastecimiento, y unificación de los tres umbrales de alerta existentes | D3. |
| 10 | Valorización por costo promedio ponderado, en reemplazo de `stock × precio_compra` | El documento original es explícito en no mezclarlo con la primera implementación de compras. |

**Deuda que Hito 1 deja abierta y que conviene no olvidar:**

- `productos.stock_minimo` sigue sin existir; la alerta del 75 % se reinicia con cada
  ingreso y puede ocultar productos de alta rotación.
- El Kardex muestra la fuente en su forma técnica.
- La web pública no valida stock: un producto activo con cero existencias sigue visible.
- La fila de Kardex no guarda costo hasta la Fase 6.
- `movimientos_kardex.origen_id` sigue siendo texto libre sin clave foránea al
  documento de origen.
- `detalle_ventas` carece de restricción de unicidad y sigue permitiendo líneas
  duplicadas del mismo producto.

---

## 9. Mapa de pruebas

### 9.1 Nuevas

| Archivo | Cubre |
|---|---|
| `tests/Feature/CompraTest.php` | CRUD del borrador, validaciones, cálculo de subtotal y total en servidor, transiciones de estado, permisos, borrado y anulación. |
| `tests/Feature/CompraRecepcionTest.php` | Recepción: incremento de stock, `inventario` igual al stock resultante, entrada de Kardex con fuente `compra` y origen `CMP-XXXX`, egreso de caja, `caja_id` y `egreso_caja_id` guardados, actualización de `precio_compra`, rechazo sin caja abierta, rollback completo ante fallo, y recepción no repetible. |
| `tests/js/compras/*.property.test.js` | Propiedad: para cualquier cantidad y costo válidos, el subtotal es exactamente su producto. Propiedad: el total es la suma exacta de los subtotales. Con `fast-check`, etiquetadas `// Feature: compras-ingreso-mercaderia, Property N`. |

### 9.2 A reescribir

| Archivo | Motivo |
|---|---|
| `tests/Feature/ProductoInventarioTest.php` | 7 casos que afirman `inventario min:1` en alta y en el formulario. Se invierten. |
| `tests/Feature/KardexTest.php` | El caso de línea 119 afirma que el alta genera entrada. Pasa a afirmar que **no** la genera. |
| `tests/Feature/ProductoPrecioTest.php` | El alta ahora admite `precio_compra = 0` y la regla de margen es condicional. |

### 9.3 A tocar

| Archivo | Motivo |
|---|---|
| `tests/Feature/ProductoCloudinaryTest.php` | Payload de alta sin `inventario`. |
| `tests/Feature/MarcaCloudinaryTest.php` | Ídem. |
| `tests/Feature/CambioAceiteCloudinaryTest.php` | Ídem. |
| `tests/Feature/Reportes/ReporteInventarioTest.php` | Valorizado con `precio_compra = 0`. |
| Tests de caja | `tipo_pago` ampliado. |

### 9.4 Invariantes a cubrir con pruebas de propiedades

- Ninguna operación de stock deja el producto sin movimiento en Kardex, salvo la edición
  de ficha, que ya no modifica cantidades.
- La suma de entradas menos salidas de un producto es igual a su `stock` actual.
- Recibir una misma compra dos veces es imposible: la segunda produce error de estado, no
  doble descuento.
- El subtotal de una línea es exactamente `cantidad × costo_unitario`, para cualquier
  combinación de enteros y decimales válidos.
- Revertir cualquier punto de la transacción de recepción deja el stock intacto.

### 9.5 Verificación

```
php artisan test --filter=CompraTest
php artisan test --filter=CompraRecepcionTest
php artisan test --filter=ProductoInventarioTest
npm run test
vendor\bin\pint --test
composer run test
```

Línea base conocida antes de empezar: 394 pruebas verdes y un fallo preexistente en
`ServicioWebPropertiesTest > property 5 inicio respects section toggle and first three`,
ajeno a esta feature y originado en cambios previos del sitio público. Ese fallo debe
seguir siendo el único.

---

## 10. Riesgos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| El cambio de tipo de `fuente` falla en SQLite in-memory | Alto: bloquea toda la Fase 3 | Tarea 3.1 es un spike bloqueante y se ejecuta antes de escribir la migración. |
| Reordenar los seeders produce datos incoherentes | Alto: inutiliza los reportes en demo | Tarea 3.9 es obligatoria, no opcional. |
| El `spike` falla y obliga a rediseñar D6 | Medio: afecta el modelo de Kardex completo | Alternativas evaluadas en la Tarea 3.1 antes de comprometer el diseño. |
| Coexistencia de dos vías de abastecimiento confunde al operador | Medio | Etiqueta visible en el modal (3.7) y enlace de Compras destacado en el menú. |
| Productos con `stock = 0` desaparecen de los buscadores de venta | Bajo: es el comportamiento correcto | Verificar los tests de la web pública, que comparten fixtures. |
| Ampliar `tipo_pago` afecta el módulo de caja | Bajo | Revisar `CajaController::registrarEgreso` (línea 100) y sus pruebas. |
| El egreso de caja hace la recepción irreversible en la práctica | Medio | Es intencional y se documenta en la vista de recepción. La reversión es Fase 7. |

---

## 11. Referencias

| Documento | Relación |
|---|---|
| `docs/nueva_implmentacion_compra.txt` | Propuesta de arquitectura de la que derivan las fases. |
| `docs/flujo-productos-inventario-stock.md` | Estado actual verificado, con las anomalías que motivation las Fases 0 y 1. |
| `docs/feature-kardex-auditoria.md` | Diseño original del Kardex. |
| `docs/fix-ventas-stock-validation.md` | Capas de validación de disponibilidad, reaplicables a la recepción. |
| `docs/patron-validacion-formularios.md` | Patrón de validación en cuatro capas, de uso obligatorio en los formularios nuevos. |
| `docs/modulo-reportes-informacion.md` | Reportes de inventario y Kardex, afectados por el valor del costo. |
| `.kiro/specs/proveedor-crud/` | Módulo de proveedores ya implementado, base de la relación `proveedor_id`. |
| `.kiro/specs/stock-update-modal/` | Modal que se retira en la Fase 4. |
| `.kiro/specs/ventas-module/` | Patrón de consumo de stock que la recepción debe replicar. |
