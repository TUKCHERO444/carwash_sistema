# Flujo de Productos, Inventario y Actualización de Stocks

**Ámbito:** sistema de lavado (POS/ERP) — Perú
**Naturaleza:** documento funcional. Describe **cómo circula el stock** por el sistema
y **en qué puntos se registra**, sin entrar en detalles de implementación.
**Estado del sistema:** refleja el comportamiento vigente en el código a la fecha de
este documento.

---

## 1. Propósito y alcance

Este documento responde tres preguntas:

1. ¿Qué es exactamente "inventario" y "stock" en este sistema, y por qué hay dos campos?
2. ¿En qué operaciones del día a día el stock de un producto sube o baja?
3. ¿Dónde queda registrado cada una de esas variaciones?

**Dentro del alcance:** alta y edición de productos, modal de ingreso de stock,
ventas, cambios de aceite, anulaciones, kardex, alertas de stock bajo, reportes de
inventario y kardex, y el panel de control.

**Fuera del alcance:** el ingreso de mercadería mediante compras a proveedor, que
aún no existe (ver §10).

---

## 2. Conceptos clave

### 2.1 El producto y su ficha

Un **Producto** es un artículo comercializable del car wash: shampoo, cera,
aceite de motor, pañuelos, etc. Su ficha contiene:

| Dato | Significado |
|---|---|
| Nombre, descripción | Identificación del artículo |
| Categoría | Clasificación del rubro (limpieza, lubricantes, accesorios…) |
| Marca | Fabricante |
| **Precio de compra** | Costo unitario de adquisición. Es la base del valorizado de inventario. |
| **Precio de venta** | Precio al público. El sistema obliga a que nunca sea inferior al de compra. |
| Foto | Imagen en la nube (Cloudinary) |
| Activo | Interruptor de disponibilidad comercial |
| **Stock** | **Existencia física actual.** Es el número que se descuenta al vender o al confirmar un servicio. |
| **Inventario** | **Existencia de referencia al inicio del ciclo de carga actual.** No es un almacén distinto: es la línea base contra la que se mide el consumo. |
| Marcas de tiempo | Cuándo se creó y cuándo se modificó la ficha |

### 2.2 El ciclo de inventario

La idea central del modelo es que **el stock no se vigila contra un mínimo fijo, sino
contra un ciclo de carga**.

Un ciclo comienza cuando se hace una **entrada de mercadería**. En ese momento se fija
la línea base: el campo `inventario` pasa a ser igual al stock resultante, y el
porcentaje consumido vuelve a 0 %. A partir de ahí, cada venta o cada servicio
consumido va restando del stock, y el sistema mide:

- **Consumido** = `inventario` − `stock`
- **Porcentaje consumido** = consumido ÷ `inventario`

Cuando se alcanza el **75 % de consumo**, el producto entra en **alerta de stock bajo**.

Gráficamente, un ciclo de 100 unidades:

```
Inicio del ciclo   inventario = 100   stock = 100   consumido = 0     (0 %)
   ... ventas ...
Alerta             inventario = 100   stock =  25   consumido = 75    (75 %)  ← alerta
Fin de ciclo       inventario = 100   stock =   0   consumido = 100  (100 %)
```

**Consecuencia de diseño importante:** la alerta no es un número absoluto, sino
proporcional al lote cargado. Un producto que tiene 3 unidades pero cuyo ciclo fue de
3 unidades no está en alerta; uno que tiene 3 unidades de un ciclo de 100 sí lo está.

**Excepción:** si el inventario de referencia es 0, el producto nunca entra en alerta,
porque no existe un ciclo contra el cual medir.

### 2.3 El Kardex (libro de movimientos)

El Kardex es el **historial inmutable de todas las variaciones de stock**. Cada
movimiento guarda:

- El producto afectado
- El tipo: **entrada** o **salida**
- La **fuente** que originó el movimiento: venta, cambio de aceite o inventario
- El **origen**: el número de la venta, la placa del vehículo, o el correlativo del ingreso de inventario
- La cantidad movida
- El **stock antes** y el **stock después**
- El usuario responsable
- La fecha y hora del movimiento

Punto clave de la arquitectura: el registro del Kardex **no altera el stock**. Es un
escribano. Quien modifica el stock es la operación de negocio, y el Kardex se limita a
fotografiar el antes y el después. Por eso la consistencia del histórico depende de que
cada operación registre su movimiento después de mover el stock.

---

## 3. Mapa maestro de eventos que mueven stock

Esta es la tabla de referencia del sistema. Cada fila es un flujo documentado en §4.

| # | Operación | Efecto en stock | Efecto en inventario | Registro en Kardex | Origen |
|---|---|---|---|---|---|
| 1 | Crear producto | Fija el stock al valor inicial | = stock inicial | **Entrada** | `INV-0001`, `INV-0002`… |
| 2 | Ingreso de mercadería (modal "Actualizar stock") | **Suma** la cantidad ingresada | **= nuevo stock** (reinicia el ciclo) | **Entrada** | `INV-XXXX` |
| 3 | Registrar venta | **Resta** la cantidad vendida | No se toca | **Salida** | `VTA-XXXX` |
| 4 | Anular / eliminar venta | **Suma** lo que se había descontado | No se toca | **Entrada** | Mismo `VTA-XXXX` |
| 5 | Crear cambio de aceite (ticket pendiente) | **No toca el stock** | No se toca | — | — |
| 6 | Confirmar cambio de aceite | **Resta** la cantidad de cada producto | No se toca | **Salida** | Placa del vehículo |
| 7 | Editar cambio de aceite **ya confirmado** | **Suma** lo anterior y **resta** lo nuevo | No se toca | **Entrada + Salida** | Placa del vehículo |
| 8 | Editar cambio de aceite **pendiente** | **No toca el stock** | No se toca | — | — |
| 9 | Eliminar cambio de aceite **confirmado** | **Suma** lo que se había descontado | No se toca | **Entrada** | Placa del vehículo |
| 10 | Eliminar cambio de aceite **pendiente** | No toca el stock | No se toca | — | — |
| 11 | Editar ficha de producto (pantalla de edición) | **Escribe el valor del formulario** | **Escribe el valor del formulario** | **NINGUNO** ⚠️ | — |
| 12 | Activar / desactivar producto | No toca el stock | No se toca | — | — |
| 13 | Eliminar producto | Se borra la ficha; sus movimientos se van con él | — | — | — |

Las filas 1, 2, 3, 4, 6, 7 y 9 dejan rastro completo. La **fila 11 es la única
ruptura del patrón** y se analiza en §9.

---

## 4. Flujos detallados

### 4.1 Flujo A — Alta de producto con stock inicial

**Disparador:** el operador con permiso de inventario crea un producto nuevo.

**Desarrollo:**

1. El operador accede al formulario de alta desde el listado de productos.
2. Completa los datos de identificación: nombre, descripción, categoría, marca.
3. Completa los dos precios. El sistema impide continuar si el precio de venta es
   menor o igual al de compra.
4. Ingresa **una sola cantidad: el inventario inicial**. Este campo es obligatorio y
   admite enteros de 1 como mínimo; el sistema no acepta dar de alta un producto con
   cero existencias.
5. Opcionalmente adjunta una foto (formatos JPG, PNG o WEBP, hasta 2 MB). La imagen se
   sube a la nube.
6. Guarda el formulario.

**Qué ocurre en el sistema:**

1. Se valida toda la información con mensajes en español. Cualquier error devuelve al
   formulario con los campos ya rellenados y el error señalado junto al campo culpable.
2. Se abre una transacción de base de datos.
3. El stock y el inventario del producto se fijan ambos al mismo valor: el producto
   nace con el 100 % de existencias y el ciclo arranca en 0 % consumido.
4. Se genera un **correlativo de ingreso de inventario** con el formato `INV-XXXX`,
   que identifica a esta operación como origen del movimiento.
5. Se escribe un movimiento de **entrada** en el Kardex: origen `INV-XXXX`, cantidad
   igual al inventario inicial, stock antes 0, stock después igual al inventario.
6. Si se asignó categoría, se incrementa el contador de productos de esa categoría.
7. Se cierra la transacción y se confirma con un mensaje de éxito.

**Resultado:** producto activo, ciclo de inventario iniciado, Kardex con su primera
línea de entrada.

**Observación:** como el stock inicial no tiene un documento de compra detrás, el
origen es un correlativo genérico `INV-XXXX` y no un proveedor. Esto es una
consecuencia directa de que el ingreso de mercadería aún no existe (§10).

---

### 4.2 Flujo B — Ingreso de mercadería por modal "Actualizar stock"

Este es el mecanismo que el sistema usa para reponer existencias. Es una acción
diseñada para ser rápida y recurrente, no para documentar una compra.

**Disparador:** el operador pulse el botón de stock en el listado de productos.

**Desarrollo:**

1. Se abre un modal con el nombre del producto y su stock actual.
2. El operador ingresa **la cantidad a sumar** (enteros de 1 a 9999).
3. Confirma.

**Qué ocurre en el sistema:**

1. La cantidad se valida en el cliente: no puede ir vacía, ni ser cero, ni negativa,
   ni superar 9999. Si falla, el error se muestra dentro del mismo modal.
2. Se abre una transacción.
3. Se genera el correlativo `INV-XXXX` siguiente.
4. Se anota la acción de auditoría "ajustar stock" asociada al usuario que la ejecuta.
5. El stock del producto se incrementa en la cantidad ingresada.
6. **El inventario de referencia se iguala al nuevo stock.** Esto reinicia el ciclo:
   el consumo vuelve a 0 % y la alerta previa se apaga.
7. Se escribe un movimiento de **entrada** en el Kardex, capturando el stock anterior
   y el nuevo.
8. Se cierra la transacción y el modal se actualiza en pantalla mostrando el nuevo
   stock, recalculando en el cliente si corresponde mostrar la insignia de stock bajo.

**Características del flujo:**

- Es **unidireccional**: solo suma. El sistema no admite, por esta vía, restar
  existencias por merma, deterioro, error de conteo o mercadería dada de baja.
- La cantidad a ingresar **no está asociada a un documento de compra, un precio de
  compra ni un proveedor**. Es una suma genérica.
- El modal no recarga la página: la operación es asíncrona y el listado queda
  actualizado en el momento.

---

### 4.3 Flujo C — Registro de venta (salida por venta)

**Disparador:** el operador registra una venta en el punto de venta.

**Desarrollo — fase de preparación:**

1. Se exige que haya una **caja abierta**. Si no la hay, la operación se rechaza y se
   muestra el aviso de caja requerida. Ninguna venta se registra sin caja.
2. El operador busca productos por nombre. El buscador **solo ofrece productos
   activos y con stock mayor que cero**: un producto agotado o desactivado es
   invisible en el punto de venta.
3. Cada producto añadido a la venta muestra su stock disponible y una marca de stock
   bajo si corresponde.
4. El operador indica cantidades, y el sistema calcula subtotales y el total.
5. Elige el método de pago: efectivo, Yape, Izipay o mixto. En el caso mixto
   desglosa los montos por cada medio.

**Desarrollo — validaciones de disponibilidad:**

6. Antes de guardar, el sistema **relee el stock real de cada producto** y compara
   contra la cantidad solicitada. Si alguna cantidad supera lo disponible, se rechaza
   la venta completa con un mensaje que indica producto, cantidad pedida y stock
   disponible, devolviendo al formulario con todo lo escrito.

**Qué ocurre en el sistema (todo dentro de una única transacción):**

7. Se genera el correlativo de venta `VTA-XXXX` a partir del siguiente identificador
   disponible.
8. Se crea la cabecera de la venta con su método de pago, montos, usuario y caja
   asociada.
9. Por cada producto de la venta se crea su línea de detalle.
10. Se **descuenta el stock** de cada producto.
11. Se escribe un movimiento de **salida** en el Kardex por cada producto, con origen
    `VTA-XXXX`, fuente "venta", capturando el stock antes y después.

**Resultado:** la venta queda registrada, el stock Bajó, y cada producto vendido tiene
su línea de salida en el Kardex, trazable al número de venta.

**Punto importante:** el inventario de referencia **no se toca**. El consumo se
acumula, pero la línea base del ciclo permanece intacta hasta que haya un ingreso
nuevo.

---

### 4.4 Flujo D — Anulación de venta (entrada compensatoria)

**Disparador:** se elimina una venta ya registrada.

**Desarrollo:**

1. El sistema anota la acción de auditoría "anular venta".
2. Por cada línea de detalle de la venta, **devuelve la cantidad al stock** del
   producto correspondiente.
3. Por cada línea, escribe un movimiento de **entrada** en el Kardex con el **mismo
   correlativo `VTA-XXXX`** de la venta anulada y la misma fuente "venta".
4. Se elimina la venta, lo que arrastra sus líneas de detalle.
5. Si algo falla en medio del proceso, la operación se revierte completa y se avisa al
   usuario.

**Resultado:** el stock vuelve al valor previo y el Kardex conserva **la salida
original y su compensating entrada**, de modo que la historia muestra que el producto
salió y volvió a entrar por la misma venta anulada. El saldo del producto es correcto;
lo que queda es un par de movimientos que un lector debe interpretar.

**Consecuencia para el ciclo:** como el inventario de referencia no se modificó, la
anulación **revierte correctamente el porcentaje de consumo**.

---

### 4.5 Flujo E — Cambio de aceite: del ticket pendiente a la confirmación

El cambio de aceite es el segundo consumidor de stock y el flujo con la lógica más
delicada del sistema, porque separa la **reserva de trabajo** del **consumo real**.

#### 4.5.1 Creación del ticket (pendiente) — no consume stock

1. El operador elige cliente y vehículo, fecha, trabajadores asignados y productos a
   aplicar (aceite, filtro, additive).
2. El buscador de productos, igual que en ventas, **solo ofrece productos activos con
   stock mayor que cero**.
3. El sistema valida contra el stock disponible en ese momento. Si una cantidad
   excede lo disponible, rechaza la operación indicando producto, cantidad pedida y
   stock disponible.
4. Los precios de cada línea se calculan en el servidor, no se/confían del formulario.
5. El ticket se crea en estado **pendiente** con sus líneas de producto.

**Punto clave:** el stock **no se descuenta** en esta fase. El ticket pendiente es una
planificación del trabajo: el aceite está "apartado" mentalmente, pero el sistema lo
sigue ofreciendo para la venta. Esto significa que dos tickets pendientes distintos
planifican el mismo aceite sin que el sistema lo detecte. La garantía real de
disponibilidad se produce en la confirmación.

#### 4.5.2 Edición del ticket pendiente — no toca stock

Editar un ticket pendiente permite corregir datos, cambiar productos y reasignar
trabajadores. El sistema **sí valida** que las cantidades solicitadas no excedan el
stock disponible, pero **no mueve el stock en ninguna dirección**: no hay entrada ni
salida en el Kardex. El ticket sigue siendo una planificación.

#### 4.5.3 Confirmación del ticket — aquí sí se consume el stock

Este es **el único punto del módulo donde el cambio de aceite descuenta inventario**.

1. El operador abre el panel de confirmación del ticket, que muestra el stock actual de
   cada producto para que pueda decidir con información.
2. El sistema valida que las cantidades solicitadas, más lo que el propio ticket ya
   tenía reservado de una operación anterior, no excedan el stock disponible.
3. Se anota la acción de auditoría "confirmar".
4. El ticket pasa a estado **confirmado**.
5. Se **descuenta el stock** de cada producto del ticket.
6. Se escribe un movimiento de **salida** en el Kardex por cada producto, con
   **fuente "cambio de aceite"** y **origen = la placa del vehículo**.
7. Si algo falla, todo se revierte y el ticket permanece pendiente.

**Sobre el origen:** a diferencia de la venta, que usa un correlativo propio, el cambio
de aceite usa **la placa del vehículo** como origen del movimiento. Esto permite
encontrar en el Kardex todo lo que se consumió para un vehículo concreto, pero pierde
el identificador del ticket: si un mismo vehículo tiene dos cambios de aceite, sus
movimientos se mezclan bajo la misma placa.

#### 4.5.4 Edición de un ticket ya confirmado

Editar un ticket ya confirmado es una operación de **compensación completa**:

1. El sistema determina el stock realmente disponible sumando a la cantidad que el
   propio ticket ya tenía consumida, para no penalizar al producto que ya está en uso.
2. Dentro de una transacción, **devuelve al stock** todo lo que el ticket anterior
   consumía y escribe la **entrada** compensatoria en el Kardex.
3. Sincroniza el conjunto de productos del ticket con los nuevos.
4. **Vuelve a descontar** el stock de los productos nuevos y escribe la **salida**
   correspondiente.

**Consecuencia:** la edición de un ticket confirmado genera **dos movimientos por
producto** (una entrada y una salida) con origen en la placa, **aunque el producto y la
cantidad no hayan cambiado**. Si solo se corrige, por ejemplo, la fecha del servicio, el
Kardex registra igualmente una entrada y una salida. El saldo final del stock es
correcto, pero el histórico se llena de ruido que un lector debe filtrar mentalmente.

#### 4.5.5 Eliminación de un ticket

- Si el ticket está **pendiente**, se elimina sin tocar el stock, porque nunca lo
  consumió.
- Si el ticket está **confirmado**, el sistema **devuelve al stock** todo lo consumido y
  escribe la **entrada** compensatoria antes de eliminar el ticket. Si el vehículo no
  tiene placa registrada, el origen se registra como un valor por defecto en lugar de
  la placa.

---

### 4.6 Flujo F — Edición de la ficha de producto (ruptura del patrón) ⚠️

**Disparador:** el operador edita un producto existente desde el listado.

**Desarrollo:** el formulario de edición expone **dos campos de cantidad editables de
forma independiente**: el stock y el inventario de referencia. Ambos aceptan enteros
desde cero.

**Qué ocurre en el sistema:**

1. Se validan los datos de la ficha, incluidos ambos campos de cantidad, cada uno de
   forma independiente.
2. Se abre una transacción.
3. Se **escriben los valores tal como vinieron del formulario**.
4. Si cambió la categoría, se ajustan los contadores de productos.
5. Se cierra la transacción con un mensaje de éxito.

**Lo que NO ocurre:**

- **No se registra ningún movimiento en el Kardex.**
- **No se valida que el stock sea menor o igual al inventario.** El sistema admite
  que un producto tenga más existencias físicas que las que dice su ciclo de referencia.
- **No hay una justificación ni un motivo** que se registre del cambio.

**Por qué importa:** esta es la **única vía del sistema que altera el stock sin dejar
rastro en el Kardex**. Una edición rutinaria —corregir un nombre, cambiar un precio—
puede convertir el stock de 20 a 3 sin que exista ningún movimiento que lo explique.
Como el Kardex es la fuente de verdad histórica, el resultado es que el historial del
producto y su stock real dejan de coincidir, y la diferencia no es reconstruible.

**Matiz relevante para el documento:** este comportamiento no es un error de
programación casual, es una decisión de diseño heredada. El formulario de edición
presenta ambos campos como si fueran datos de la ficha, cuando `inventario` es en
realidad un dato del ciclo de carga y `stock` es el resultado de operaciones de
negocio. Mezclarlos en un formulario de edición de ficha es la causa de fondo.

---

### 4.7 Flujo G — Baja de producto

1. Se solicita confirmación al operador antes de eliminar.
2. Se elimina la fotografía del producto del almacenamiento en la nube.
3. Se abre una transacción: si el producto estaba contabilizado en una categoría, se
   decrementa su contador.
4. Se elimina la ficha del producto.
5. Al borrarse el producto, **sus movimientos de Kardex se eliminan también** por la
   regla de cascada de la base de datos.

**Consecuencia para la trazabilidad:** dar de baja un producto **destruye su historial
de movimientos**. En la práctica el sistema evita esto porque el detalle de venta y el
detalle de cambio de aceite restringen el borrado de productos que ya fueron usados, de
modo que solo se
pueden dar de baja productos que nunca se movieron (y por tanto, casi sin historial).

---

### 4.8 Flujo H — Activar y desactivar producto

El interruptor de estado es una acción inmediata, sin formulario y sin confirmación.

1. Se anota la acción de auditoría correspondiente.
2. Se invierte el estado.
3. El sistema responde de inmediato y el listado actualiza el indicador visual.

**Efecto sobre el stock:** ninguno.

**Efecto sobre la disponibilidad:** un producto desactivado desaparece de los
buscadores de venta y de cambio de aceite. Esto permite "retirar" un artículo del
mostrador sin borrarlo del historial, pero **el stock sigue siendo visible en el
listado de inventario y sigue contando en los reportes y en el valorizado**.

---

## 5. El modelo de alerta de stock bajo

### 5.1 Cómo se determina

Un producto está en alerta cuando se ha consumido al menos el **75 %** de su ciclo de
carga. Formalmente, la alerta se activa cuando la diferencia entre el inventario de
referencia y el stock actual iguala o supera el 75 % del inventario.

Si el inventario de referencia es cero, el producto **nunca** entra en alerta.

### 5.2 Dónde se ve la alerta

| Ubicación | Comportamiento |
|---|---|
| Listado de productos | Insignia "Stock bajo" junto a la celda de stock |
| Buscador AJAX del listado | Cada resultado incluye el indicador de alerta |
| Punto de venta | Insignia junto a cada producto añadido a la venta |
| Cambio de aceite | El buscador expone el stock; el panel de confirmación muestra el stock actual |
| Modal de ingreso de stock | Recalcula la insignia en cliente después de ingresar |
| Reporte de inventario | Columna con el porcentaje consumido y el indicador de alerta |

### 5.3 Inconsistencias del modelo de alerta

El sistema tiene **tres criterios distintos de stock bajo conviviendo**, lo que puede
mostrar alertas contradictorias al operador:

1. **El criterio del 75 % de consumo**, que es el que usan el listado, los buscadores,
   el modal y el reporte de inventario. Es proporcional al ciclo.
2. **El criterio del panel de control**, que considera en alerta cualquier producto cuyo
   stock sea **igual o menor a 5 unidades**, sin importar el ciclo. Para un producto
   con un ciclo de 500 unidades, tener 5 en stock no genera alerta en el listado pero
   sí aparece en el panel.
3. **El filtro del punto de venta**, que por diseño no ofrece productos con stock cero,
   pero sí ofrece productos con stock 1 aunque estén en alerta.

Además, el criterio del 75 % **está duplicado en dos lugares**: la lógica del modelo de
producto y una recálculo equivalente en el código del cliente del modal. Si uno de los
dos cambia y el otro no, la insignia del modal discrepará de la del listado.

### 5.4 El efecto del ingreso sobre la alerta

Cada ingreso de mercadería reinicia el ciclo, y por lo tanto **apaga cualquier alerta
previa del producto**. Es el comportamiento correcto conceptualmente (el producto se
repuso), pero tiene un efecto secundario que conviene documentar: si un producto entra
en alerta porque se vendió mucho, un ingreso—even pequeño—devuelve el porcentaje de
consumo a cero, ocultando el hecho de que la rotación de ese producto es alta.

---

## 6. Flujos de consulta

### 6.1 Kardex — consulta de movimientos

**Ubicación:** dentro de la sección de **Auditoría**, protegida por el permiso de
auditoría (no por el de inventario). Esto es coherente con su naturaleza de registro
de trazabilidad.

**Vista general:** listado paginado de todos los movimientos, con filtros por:

- Producto
- Fuente: venta, cambio de aceite o inventario
- Origen: el número de venta (`VTA-0001`), el de ingreso (`INV-0001`) o una placa
- Rango de fechas

Cada fila muestra fecha y hora, producto, tipo (entrada o salida, distinguidas
visualmente), cantidad, stock anterior, stock posterior, fuente, origen y usuario
responsable.

**Vista por producto:** al hacer clic en el nombre de un producto se abre su Kardex
particular, con el stock actual en la cabecera y el mismo detalle de movimientos sin la
columna de producto.

**Observación de diseño:** el Kardex muestra la **fuente en su forma técnica** —
"cambio_aceite" con guion bajo, "inventario"— en lugar de texto presentable. Es un
detalle de pulido pendiente.

### 6.2 Reportes de inventario

Protegido por el permiso de reportes. Ofrece:

- **Top de productos por cantidad vendida**, combinando las salidas por venta y las
  salidas por cambio de aceite **confirmado** (los tickets pendientes se excluyen
  correctamente porque aún no consumieron stock).
- **Stock actual valorizado**: el stock multiplicado por el precio de compra. Es un
  dato independiente del rango de fechas, porque refleja el estado actual.
- **Resumenes por categoría y por marca.**
- Filtros por categoría y marca.
- Exportación a hoja de cálculo.

### 6.3 Reporte de Kardex

En el mismo módulo de reportes, con una estructura distinta a la consulta de Kardex:

- **Vista agregada por producto:** total de entradas, total de salidas, saldo neto y
  stock actual, con filtros por rango de fechas, producto y tipo de movimiento.
- **Vista de detalle:** listado paginado de movimientos individuales con las mismas
  columnas que el Kardex operativo.
- Exportación a hoja de cálculo y a PDF.

### 6.4 Visibilidad pública

La web pública del car wash muestra los productos activos y, en el listado por
categoría, permite ordenar por stock ascendente o descendente. **La ficha de producto
pública no verifica el stock**: un producto con stock 0 activo sigue siendo visible y
enlazable para el visitante. La web pública no consume inventario, pero expone un
dato de disponibilidad que puede no coincidir con la realidad.

---

## 7. Control de acceso y auditoría dentro del flujo

### 7.1 Permisos

| Permiso | Cubre |
|---|---|
| Acceso a inventario | Listado, alta, edición, baja, ingreso de stock, activados, búsqueda |
| Acceso a auditoría | Consulta del Kardex |
| Acceso a reportes | Reporte de inventario y de Kardex |
| Acceso a proveedores | Ficha de proveedores (módulorecién incorporado) |

El rol **Administrador** tiene acceso total por configuración del sistema. Los usuarios
con permisos acotados pueden ver el Kardex sin poder modificar el stock, o modificar
stock sin poder auditarlo: son responsabilidades separables a propósito.

### 7.2 Auditoría de acciones

Cada operación sensible del flujo deja una entrada en el registro de auditoría:

| Acción auditada | Cuándo |
|---|---|
| "ajustar stock" | Cada ingreso de mercadería por modal |
| "toggle estado" | Cada activación o desactivación de producto |
| "anular venta" | Cada venta eliminada |
| "confirmar" | Cada cambio de aceite confirmado |
| "actualizar ticket" | Cada edición de cambio de aceite |

**Distinción importante:** los movimientos de Kardex **no se auditan como entidades**.
Son parte del inventario físico del producto y se consultan a través del propio Kardex.
La auditoría de modelos cubre las fichas (producto, proveedor); la trazabilidad de las
cantidades la cubre el Kardex. Son dos capas complementarias y no se solapan.

**Hueco de la auditoría:** la edición de la ficha de producto que altera el stock
( Flujo F) **no tiene una acción de auditoría propia**. Se registra la actualización
del modelo, pero no que se cambió una cantidad, ni de cuánto a cuánto, ni por qué. En
el histórico de acciones aparece una actualización genérica de producto, indistinguible
de un cambio de nombre.

---

## 8. Coherencia del ciclo: reglas que el sistema respeta

Recopilando los flujos anteriores, estas son las invariantes que el diseño persigue y
que hoy se cumplen en todos los caminos salvo el indicado:

1. **Toda variación de stock deja rastro en el Kardex.** → Se cumple excepto en el
   Flujo F.
2. **El inventario de referencia solo cambia cuando hay una entrada de mercadería.** →
   Se cumple: ventas, cambios de aceite y anulaciones no lo tocan.
3. **Cada salida tiene su entrada compensatoria cuando la operación se revierte.** →
   Se cumple para anulación de venta y para eliminación/edición de cambio de aceite
   confirmado. No aplica a la baja de producto, que destruye el historial por cascada.
4. **Solo se consume stock de productos activos y disponibles.** → Se cumple en venta y
   cambio de aceite.
5. **La validación de disponibilidad precede al descuento.** → Se cumple en todos los
   caminos, aunque con la salvedad de concurrencia indicada en §9.
6. **La operación de stock y su registro en el Kardex son atómicos.** → Se cumple:
   todas las operaciones que mueven stock lo hacen dentro de una transacción que
   incluye la escritura del movimiento.

---

## 9. Anomalías detectadas en el estado actual

Estas observaciones no describen funcionalidad, sino **deficiencias que el flujo
presenta hoy**. Se documentan porque afectan la fiabilidad de la información de
inventario.

### 9.1 Edición de producto sin rastro en el Kardex — prioridad alta

Descrito en §4.6. Es la anomalía de mayor impacto: permite modificar el stock de un
producto sin dejar ningún registro, admite estados imposibles (más existencias que las
que declara el ciclo) y no queda ningún dato que permita reconstruir qué pasó.

### 9.2 El ingreso de mercadería apaga permanentemente la capacidad de detectar alta rotación — prioridad media

Al reiniciar el ciclo con cada ingreso, el porcentaje de consumo vuelve a cero. Como
la alerta es proporcional al ciclo y no absoluta, **un producto que rota muy rápido
puede no aparecer nunca en alerta** si su consumo se mantiene por debajo del 75 % entre
ingresos. En la práctica, esto desactiva la utilidad de la alerta para el producto más
crítico del negocio: el de mayor consumo.

### 9.3 Condición de carrera en la validación de disponibilidad — prioridad media

En venta y en confirmación de cambio de aceite, la comprobación de disponibilidad se
hace **antes** de abrir la transacción, y el descuento posterior es atómico. Si dos
operaciones simultáneas piden el mismo producto con existencias justa, **ambas pasan la
validación** y el stock puede quedar negativo, con un stock posterior negativo congelado
en el Kardex. El riesgo es real en un punto de venta con varias terminales o con
usuarios rapidistas.

### 9.4 Ruido en el Kardex por edición de cambios de aceite confirmados — prioridad baja

Descrito en §4.5.4. El saldo es correcto, pero el histórico se llena de pares
entrada/salida que no representan un cambio real de existencias.

### 9.5 Identificador de origen del cambio de aceite poco preciso — prioridad baja

Se usa la placa como origen en lugar del identificador del ticket. Con varios cambios
de aceite para el mismo vehículo, el Kardex no permite distinguir a qué servicio
pertenece cada consumo.

### 9.6 La web pública puede mostrar productos agotados — prioridad baja

La ficha pública de producto no verifica el stock, por lo que puede promocionar un
artículo que ya no hay.

### 9.7 Códigos internos de fuente visibles al usuario — prioridad baja

"Kardex" y los reportes muestran los valores técnicos de la fuente. Es un detalle de
presentación pendiente.

---

## 10. Vacíos funcionales

Lo que **no existe** hoy en el sistema, y que en ocasiones se presupone pero no está:

### 10.1 Ingreso de mercadería como operación de compra

No hay ningún módulo de compras a proveedor. El único camino para reponer stock es el
modal de suma, que es genérico y sin respaldo documental: no registra proveedor, ni
documento de compra, ni fecha de vencimiento, ni precio de compra unitario de esa
entrada en particular, ni lote.

**Consecuencias directas:**

- El **precio de compra** de la ficha del producto es un valor único y manual. No
  refleja el costo histórico real; si el proveedor sube precios, el valorizado del
  inventario y el margen real quedan desalineados.
- No hay **trazabilidad entre proveedor ymercadería**.
- No hay forma de saber **cuánto se le pagó a cada proveedor ni qué se le compró**.

### 10.2 Relación entre proveedor y producto

La ficha de proveedor existe y es completa, pero **no está conectada a los productos**.
Un producto no sabe de qué proveedor viene, y un proveedor no tiene la lista de lo que
suministra. La conexión está declarada como siguiente paso en la especificación del
módulo de proveedores.

### 10.3 Salidas no comerciales

El sistema no contempla salidas de inventario por motivos distintos de la venta y el
servicio. No hay merma, ni deterioration, ni consumo interno, ni donation, ni
mercadería dañada, ni regularización por conteo físico. La única forma de reducir el
stock sin vender es editar la ficha del producto, con las consecuencias de §9.1.

### 10.4 Cálculo de costo por movimiento

Los movimientos de Kardex no capturan el costo unitario en el momento del movimiento.
El reporte de inventario valora el stock actual con el precio de compra **actual** de la
ficha, no con el costo histórico. Si el precio de compra se editó después, el
valorizado refleja el precio nuevo aplicado a existencias viejas.

### 10.5 Trazabilidad por lote y vencimiento

No hay lotes, no hay fechas de vencimiento, no hay control de productos perecederos ni
alerta de vencimiento próxima. En un rubro que maneja aceites y aditivos, la ausencia
de control de vencimiento es una limitación funcional.

### 10.6 Monedas y conversión

El sistema trabaja en una sola moneda (soles, implícito). No hay manejo de tipo de
cambio ni de múltiples monedas en la compra a proveedor, lo que será relevante cuando
se implemente el ingreso de mercadería.

---

## 11. Hoja de ruta natural

El encadenamiento de lo que falta, en el orden en que el modelo actual lo sugiere:

1. **Ingreso de mercadería como operación de compra.** Reemplazar o complementar el
   modal genérico con una pantalla que registre: proveedor, fecha, documento,
   productos, cantidades, y **precio de compra unitario por línea**. Este es el punto
   de inflexión que convierte el "inventario" actual (que solo es un contador) en un
   inventario con costo real.
2. **Captura del costo por movimiento en el Kardex.** Una vez que la compra registre
   el precio, el movimiento de entrada puede guardar ese costo, y el valorizado del
   reporte puede basarse en costo real en lugar del precio de la ficha.
3. **Restauración del precio de compra vigente.** Con el paso 1, el precio de compra
   puede actualizarse automáticamente al confirmar un ingreso, o mantenerse congelado
   como histórico con una vigencia temporal.
4. **Merma y ajuste manual.** Dotar al sistema de una salida justificada, con motivo
   tipificado, para completar el ciclo de salidas del inventario.
5. **Entrada en servicio de consulta de RUC.** Autocompletar los datos del proveedor al
  -ingresarlo, en lugar de la captura manual actual.
6. **Unificación del criterio de alerta.** Consolidar los tres umbrales de stock bajo
   en uno solo, preferentemente el proporcional al ciclo, decidiendo si se añade un
   piso absoluto.
7. **Cierre de la brecha de la edición de producto.** Retirar del formulario de
   edición los campos de cantidad, o convertirlos en un ajuste formal que sí registre
   su movimiento.
8. **Lotes y vencimientos.** Cuando el volumen de operación lo justifique.

---

## 12. Resumen para el operador

- Para **dar de alta un artículo** se defines su stock inicial en un solo campo.
- Para **reponer existencias** usas el botón de stock del listado e ingresas cuánto
  suma. Solo suma, y reinicia el conteo de consumo del producto.
- Para **dar de baja por merma o error de conteo** el sistema **no tiene una vía
  correcta**: la única opción actual es editar la ficha del producto, lo cual no deja
  registro en el Kardex.
- Para **consultar qué pasó con un producto** vas al Kardex, en la sección de
  Auditoría, y puedes filtrar por producto, origen o fecha.
- Para **saber el valor del inventario** vas al reporte de inventario, que multiplica
  el stock por el precio de compra de la ficha.
- Un producto en **alerta** es aquel que ya consumió el 75 % de su ciclo de carga, no
  necesariamente el que tiene menos unidades.
- La **web pública** puede mostrar artículos agotados, porque no valida el stock.

---

## 13. Referencias internas

Para profundizar en cada pieza, los documentos existentes del proyecto son:

| Documento | Cubre |
|---|---|
| `docs/feature-kardex-auditoria.md` | Diseño completo del Kardex: esquema, casos de movimiento, requisitos |
| `docs/fix-ventas-stock-validation.md` | Capas de validación de disponibilidad de stock en venta y cambio de aceite |
| `docs/flujo-cloudinary-productos.md` | Arquitectura de imágenes del CRUD de productos |
| `docs/modulo-marcas.md` | Ficha de marca y su relación con producto |
| `docs/modulo-reportes-informacion.md` | Referencia funcional de los nueve reportes, incluidos inventario y Kardex |
| `docs/patron-validacion-formularios.md` | Patrón de validación en cuatro capas aplicado a todos los formularios |
| `.kiro/specs/stock-update-modal/` | Especificación del modal de ingreso de stock |
| `.kiro/specs/ventas-module/` | Especificación del módulo de ventas y su consumo de stock |
| `.kiro/specs/cambio-aceite-confirmacion/` | Especificación de la separación pendiente/confirmado |
| `.kiro/specs/proveedor-crud/` | Especificación del módulo de proveedores y su alcance declarado |
| `.kiro/specs/producto-crud/` | Especificación del CRUD de productos |
