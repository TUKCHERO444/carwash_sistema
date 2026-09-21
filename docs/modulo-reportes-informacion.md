# Módulo de reportes: qué información muestra cada reporte

Documento de referencia que detalla, módulo por módulo, qué se muestra en cada
reporte del sistema. Útil para soporte y para validar que lo que se ve en
pantalla coincide con lo que se exporta.

## Elementos comunes a todos los reportes

- **Acceso:** menú lateral *Gestión Administrativa → Reportes*. Requiere el
  permiso RBAC `acceso-reportes` (solo el rol Administrador por defecto).
- **Índice:** `GET /reportes` (`reportes.index`) — cuadrícula de 9 tarjetas,
  una por reporte, con título y descripción.
- **Rango de fechas:** todos los reportes reciben `desde`/`hasta` (`Y-m-d`).
  Si no se envían, por defecto se usan los **últimos 30 días** (hoy incluido).
  La etiqueta del rango aplicado se muestra en la cabecera.
- **Filtros contextuales:** cada reporte añade selects propios debajo del rango
  (por ejemplo vehículo, servicio, trabajador, producto, categoría...). Siempre
  con opción "Todos" y botones **Filtrar** y **Limpiar**.
- **Acciones:** todos los reportes tienen los botones **Imprimir**
  (`window.print()`), **Exportar CSV** (añade `?export=csv` a la URL actual) y
  **Exportar PDF** (añade `?export=pdf`).
- **Formato CSV:** BOM UTF-8, separador de campos `;` (compatible Excel es-PE),
  fila de cabecera, sin paginación (detalle completo) y nombre de archivo
  descriptivo (`reporte-<modulo>-<fecha>.csv`).
- **Exportación PDF:** descarga A4 vertical (`application/pdf`) generada con
  dompdf desde `resources/views/reportes/pdf/*`. Replica los KPIs, agregados y
  el **detalle completo** (sin paginar) del reporte con los filtros aplicados.
  No incluye gráficos; el nombre de archivo es `reporte-<modulo>-<fecha>.pdf`.
- **Universo de datos:** solo se consideran operaciones **confirmadas**
  (lavados, cambios de aceite y ventas); los registros `pendiente` quedan
  excluidos de todos los cálculos.
- **Zona horaria:** todos los períodos se calculan en hora de Perú
  (`America/Lima`); las comparaciones de día usan el criterio: ventas →
  `created_at`, lavados → `fecha`, cambio de aceite → `created_at`.
- **Formato monetario:** todos los importes se muestran en S/ con 2 decimales.

---

## 1. Reporte de ingresos — `GET /reportes/ingresos` (`reportes.ingresos`)

Filtros: solo rango de fechas.

**KPIs (4 tarjetas):**
- Ingreso total (S/).
- Operaciones.
- Ticket promedio (S/).
- Por método de pago: Efectivo, Yape e Izipay (S/). El caso `mixto` se
  consolida repartiendo sus montos parciales entre los tres métodos.

**Desglose por fuente (3 tarjetas):** Ventas, Lavados y Cambio de aceite,
cada una con su subtotal del rango.

**Gráficos (Chart.js):**
- Barras apiladas de ingresos diarios por fuente (Ventas / Lavados / Cambio).
- Dona de distribución por método de pago.

**Tabla "Días de mayor ingreso" (top N = 10 del rango):**
columnas `Fecha | Ventas | Lavados | Cambio de aceite | Total | Actividad dominante`.
La actividad dominante marca un badge (Ventas, Lavados o Cambio de aceite)
cuando una sola fuente representa ≥ 50 % del día; si no, muestra "Mixto".

**CSV:** serie diaria del rango: `fecha;ventas;lavados;cambio_aceite;total`.

---

## 2. Reporte de ventas — `GET /reportes/ventas` (`reportes.ventas`)

Filtros: rango, **Usuario** (`user_id`), **Método de pago** (Efectivo, Yape,
Izipay, Mixto) y **Correlativo** (búsqueda parcial por texto).

**KPIs (3):** Ingreso total, Operaciones y Ticket promedio.

**Tabla "Por usuario":** `Usuario | Ventas | Total`.

**Tabla "Por método de pago":** `Método | Operaciones | Total`.

**Tabla "Detalle" (pagina de 10 en 10):**
`Correlativo | Fecha | Usuario | Método | Total`.

**CSV:** `id;correlativo;fecha;usuario;metodo_pago;total`.

---

## 3. Reporte de lavados — `GET /reportes/lavados` (`reportes.lavados`)

Filtros: rango, **Vehículo** (`vehiculo_id`), **Servicio** (`servicio_id`) y
**Trabajador** (`trabajador_id`).

**KPIs (3):** Ingreso total, Lavados confirmados y Ticket promedio.

**Tabla "Por vehículo":** `Placa | Lavados | Total`. Agrupa los lavados por
vehículo físico atendido (**placa** del automotor); los lavados sin automotor
registrado se agrupan bajo "Sin placa".

**Tabla "Por servicio":** `Nombre | Lavados | Total`.

**Tabla "Por trabajador":** `Nombre | Lavados | Total` (una operación cuenta
una vez por lavado en que participó el trabajador).

**Tabla "Detalle" (pagina de 10 en 10):**
`ID | Fecha | Cliente | Placa | Vehículo | Servicios | Total`.

**CSV:** `id;fecha;cliente;placa;vehiculo;servicios;total;estado`.

---

## 4. Reporte de cambio de aceite — `GET /reportes/cambio-aceite` (`reportes.cambioAceite`)

Filtros: rango, **Trabajador** (`trabajador_id`) y **Producto** (`producto_id`).

**KPIs (3):** Ingreso total, Cambios confirmados y Ticket promedio.

**Tabla "Por producto":** `Producto | Unidades | Total` (unidades consumidas).

**Tabla "Por trabajador":** `Trabajador | Cambios | Total`.

**Tabla "Detalle" (pagina de 10 en 10):**
`ID | Fecha | Cliente | Placa | Trabajadores | Productos | Total`.

**CSV:** `id;fecha;cliente;placa;trabajadores;productos;total;estado`.

---

## 5. Reporte de inventario — `GET /reportes/inventario` (`reportes.inventario`)

Filtros: rango, **Categoría** (`categoria_id`) y **Marca** (`marca_id`).

**Tabla "Top de productos vendidos (por cantidad)":**
`Producto | Categoría | Marca | Cantidad | Ingreso`. Combina la cantidad e
ingresos de `detalle_ventas` y `cambio_productos` confirmados del rango.

**Tabla "Stock actual valorizado":** `Producto | Stock | Valorizado`
(Valorizado = `stock × precio_compra`; **independiente del rango**).

**Tabla "Resumen por categoría":** `Categoría | Productos | Stock | Ingresos`.

**Tabla "Resumen por marca":** `Marca | Productos | Stock | Ingresos`.

**CSV:** `producto;categoria;marca;cantidad_vendida;ingreso`.

---

## 6. Reporte de clientes y automotores — `GET /reportes/clientes` (`reportes.clientes`)

Filtros: solo rango de fechas.

**Tabla "Top clientes por gasto" (top 20 por gasto):**
`Cliente | Gasto | Visitas | Automotores | Lavados | Cambio de aceite | Visitas / mes`.

- **Automotores:** automotores **distintos atendidos** en el rango (unión de
  lavados confirmados y cambios de aceite confirmados).
- **Lavados:** automotores distintos con al menos un lavado confirmado.
- **Cambio de aceite:** automotores distintos con al menos un cambio confirmado.
- Un mismo automotor con lavado y cambio cuenta en sus dos columnas.
- Los automotores solo registrados (vinculados al cliente) que no fueron
  atendidos **no** cuentan aquí.

**Tabla "Automotores más atendidos" (top 20):**
`Placa | Cliente | Visitas` (visitas = lavados + cambios confirmados del
automotor en el rango).

**Tabla "Frecuencia por cliente" (detalle paginado de 10 en 10):**
`Cliente | Gasto | Visitas | Automotores | Visitas / mes`. En esta tabla
"Automotores" corresponde al número de automotores **registrados** del cliente
(no solo los atendidos).

**CSV:** `cliente;gasto;visitas;automotores;lavados;cambio_aceite;visitas_por_mes`.

---

## 7. Reporte de caja — `GET /reportes/caja` (`reportes.caja`)

Filtros: rango de fechas por **fecha de cierre** (solo cajas cerradas).

**KPIs (4):** Cajas cerradas, Ingresos, Egresos y Saldo neto (suma de balances).

**Tabla "Balance por jornada" (pagina de 10 en 10):**
`Caja | Usuario | Apertura | Cierre | Monto inicial | Ingresos | Egresos | Balance final`.

**Tabla "Egresos por descripción" (agregado):**
`Descripción | Tipo de pago | Cantidad | Total`.

**Tabla "Detalle de egresos":**
`Fecha | Caja | Descripción | Usuario | Monto`.

**CSV:** `caja;usuario;apertura;cierre;monto_inicial;total_ingresos;total_egresos;balance_final`.

---

## 8. Reporte de personal — `GET /reportes/personal` (`reportes.personal`)

Filtros: **Mes** (`mes` en formato `Y-m`, por defecto el mes actual) y
**Trabajador** (`trabajador_id`, opcional).

**KPIs (3):** Trabajadores con marca, Días con marcación y
Total a pagar (jornales).

**Tabla de resumen por trabajador:**
`Trabajador | Jornal | Asistencias | % asistencia | Hora promedio | Total a pagar | Estado`.
- **Asistencias:** días del mes con al menos una marcación.
- **% asistencia:** asistencias ÷ días con alguna marca en el mes.
- **Jornal:** el `pago_diario` del trabajador; si no tiene jornal (legado) se
  muestra el badge "Sin jornal" y el total a pagar figura como S/ 0.00.
- **Total a pagar:** `asistencias × pago_diario`.
- **Estado:** Activo / Inactivo.
- El total a pagar general aparece en el KPI "Total a pagar".

**CSV:** `trabajador;pago_diario;asistencias;porcentaje_asistencia;hora_promedio;total_pago;sin_jornal;activo`.

---

## 9. Reporte de kardex — `GET /reportes/kardex` (`reportes.kardex`)

Filtros: rango, **Producto** (`producto_id`) y **Tipo** (`entrada|salida`).

**Tabla "Movimientos agregados por producto":**
`Producto | Entradas | Salidas | Saldo neto | Stock actual`.
Saldo neto = entradas − salidas del rango.

**Tabla "Detalle de movimientos" (pagina de 10 en 10):**
`ID | Fecha | Producto | Tipo | Fuente | Cantidad | Stock antes | Stock después | Usuario`.

**CSV:** `id;fecha;producto;tipo;fuente;cantidad;stock_antes;stock_despues;usuario`.

---

## Notas finales

- Todos los montos de la aplicación se manejan y despliegan en **soles (S/)**.
- El módulo es de **solo lectura**: no registra transacciones, no abre/cierra
  caja y no escribe en la auditoría.
- Las tablas de detalle pagan de 10 en 10 (convención del proyecto); los
  agregados muestran el total de la dimensión sin paginar. En las exportaciones
  (CSV y PDF) el detalle siempre es completo, sin paginación.
- Cuando no hay datos en el período, cada tabla muestra su estado vacío
  ("Sin datos", "Sin registros en el período", etc.).