# Requisitos: módulo de reportes

## Contexto

El sistema acumula operaciones de los tres procesos centrales del negocio de lavado de vehículos:

- **Ventas** (tabla `ventas` + `detalle_ventas`): venta de productos de bodega.
- **Lavados** (tabla `lavados` + `detalle_servicios` + `lavado_trabajadores`): lavados vehiculares con servicios y trabajadores asignados.
- **Cambio de aceite** (tabla `cambio_aceites` + `cambio_productos` + `cambio_aceite_trabajadores`): servicio con productos consumidos.

A esto se suman entidades complementarias (productos, categorías, marcas, clientes, automotores, vehículos, servicios, trabajadores, asistencias, caja y egresos, movimientos de kardex) que hoy solo tienen CRUD o paneles puntuales (dashboard, historial de caja). **No existe un módulo de reportes consolidado** que permita al administrador tomar decisiones: el dashboard solo muestra un resumen fijo de 7/30 días/este mes.

Este módulo agrega un **bloque de reportes de solo lectura** con filtros variados por rango de fechas y selects contextuales, exportación CSV e impresión. Las decisiones:

- **Consolidación de ingresos**: se sigue el mismo criterio ya probado en `CajaService::calcularResumen()` y `DashboardService`: ventas y cambio_aceites por `DATE(created_at)`, lavados **confirmados** por `DATE(fecha)`. Los cálculos de reporte **nunca** incluyen lavados/cambios en estado `pendiente`.
- **Solo lectura**: el módulo no registra transacciones de dinero, no usa `error_caja` (no abre/cierra caja) y no audita (no escribe en `registros_auditoria`).
- **Zona horaria**: todos los períodos se calculan con `now()`/Carbon de la app (`America/Lima`), nunca con `date('...')` crudo, y las comparaciones de día usan `->toDateString()`.

## Glosario

- **Reporte**: vista de solo lectura que agrega o lista operaciones/entidades con filtros y KPIs.
- **Ingreso consolidado**: suma de ventas (`total`), lavados confirmados (`total`) y cambios de aceite confirmados (`total`) en un rango.
- **Día de mayor ingreso**: día del rango cuyo ingreso consolidado es el mayor, con desglose por actividad (ventas/lavados/cambios).
- **Actividad / fuente de ingreso**: uno de los tres procesos centrales (ventas, lavados, cambio de aceite).
- **Ticket promedio**: ingreso consolidado ÷ número de operaciones del mismo universo.
- **Resumen de pago**: asistencias del trabajador en el período × su `pago_diario`.
- **`pago_diario`**: jornal diario del trabajador en soles (decimal, default 50), nuevo campo.
- **Acceso-reportes**: permiso RBAC nuevo (`acceso-reportes`) que protege todas las rutas del módulo; solo lo recibe el Administrador (vía `AuthSeeder::syncPermissions(Permission::all())`). El rol Vendedor **no** lo recibe.
- **Exportación CSV**: respuesta `text/csv` (BOM UTF-8, separador `;` compatible Excel es-PE) generada con los mismos servicios de agregación que la vista.

---

## Requisitos

### Requisito 1: Acceso desde el bloque Gestión Administrativa

**User Story:** Como Administrador, quiero acceder a los reportes desde la navegación lateral dentro del bloque administrativo, para consultarlos sin buscar la URL.

#### Criterios de Aceptación

1. WHEN el usuario autenticado con permiso `acceso-reportes` abre el layout, THE Sistema SHALL mostrar una sección "Reportes" **dentro del dropdown "Gestión Administrativa"** del sidebar de escritorio y de la navegación inferior móvil.
2. THE sección SHALL estar protegida con `@can('acceso-reportes')`.
3. THE sección SHALL listar accesos a: Ingresos, Ventas, Lavados, Cambio de aceite, Inventario, Clientes, Caja, Personal y Kardex/Movimientos.
4. WHEN una ruta del grupo `reportes.*` está activa, THE dropdown "Gestión Administrativa" SHALL permanecer abierto (`data-persistent`) y las entradas del grupo SHALL resaltarse como el resto del menú.
5. THE variable `$gestionAdministrativaActive` del layout SHALL incluir `reportes.*`.

**Valida:** Acceso a la feature.

---

### Requisito 2: Permiso y control de acceso

**User Story:** Como Administrador, quiero que el módulo de reportes esté protegido por un permiso específico, para que solo el personal autorizado consulte la información.

#### Criterios de Aceptación

1. THE Sistema SHALL registrar el permiso `acceso-reportes` en `database/seeders/PermissionSeeder.php`, en un bloque `// Reportes`.
2. THE rol Administrador SHALL recibir el permiso automáticamente (el `AuthSeeder` sincroniza todos los permisos). No se modifica `AuthSeeder`.
3. THE rol Vendedor SHALL **no** recibir el permiso.
4. Todas las rutas del módulo SHALL estar dentro del grupo `middleware(['auth', 'permission:acceso-reportes'])`.
5. WHEN un usuario no autenticado intenta acceder a cualquier ruta del módulo, THE Sistema SHALL redirigirlo a `/login`.
6. WHEN un usuario autenticado sin el permiso `acceso-reportes` intenta acceder a cualquier ruta del módulo, THE Sistema SHALL responder HTTP 403.
7. Las rutas del módulo SHALL ser todas estáticas (sin parámetros dinámicos), evitando conflictos de Route Model Binding.

**Valida:** Control de acceso de toda la feature.

---

### Requisito 3: Panel índice de reportes

**User Story:** Como Administrador, quiero una página índice con tarjetas para cada reporte, para navegar con un clic.

#### Criterios de Aceptación

1. WHEN accede a `GET /reportes`, THE ReporteController SHALL devolver la vista `reportes.index`.
2. THE vista SHALL mostrar una cuadrícula de tarjetas (una por reporte) con título y descripción, cada una enlazando a su ruta de reporte.
3. THE tarjetas SHALL respetar el tema oscuro con las clases semánticas del proyecto (`bg-surface`, `border-main`, `text-primary`, `text-secondary`).

**Valida:** Navegación y orden del módulo.

---

### Requisito 4: Filtros comunes por rango de fechas

**User Story:** Como Administrador, quiero filtrar los reportes por un rango de fechas propio o predefinido, para acotar la información a lo que necesito.

#### Criterios de Aceptación

1. THE Sistema SHALL aceptar en todo reporte con rango los parámetros `desde` y `hasta` con formato `Y-m-d`.
2. IF no se envían `desde`/`hasta`, THEN THE Sistema SHALL usar por defecto los últimos 30 días (incluyendo hoy), calculados con `now()` de la app.
3. IF `desde` o `hasta` se omiten parcialmente, THEN THE Sistema SHALL completar con el mismo rango de 30 días terminando hoy.
4. THE validación SHALL rechazar `desde`/`hasta` con formato inválido y `desde > hasta` (HTTP 422 en CSV, `withErrors` en vista) y `hasta` posterior a hoy.
5. THE etiqueta del rango aplicado SHALL mostrarse en la cabecera del reporte (p. ej. "01/08/2026 – 30/08/2026").

**Valida:** Propiedad 1 (normalización del rango).

---

### Requisito 5: Reporte de ingresos consolidados

**User Story:** Como Administrador, quiero ver los ingresos consolidados por fuente en un rango, para evaluar la salud financiera del negocio.

#### Criterios de Aceptación

1. WHEN accede a `GET /reportes/ingresos`, THE Sistema SHALL calcular el ingreso consolidado del rango (`ventas` + `lavados_confirmados` + `cambios_confirmados`).
2. THE vista SHALL mostrar KPIs: ingreso total, número de operaciones, ticket promedio, e ingreso por cada fuente.
3. THE vista SHALL mostrar un gráfico de barras apiladas diarias por fuente (Chart.js) con el mismo criterio de `DashboardService::ingresosPorDia`.
4. THE vista SHALL mostrar la distribución por método de pago (efectivo, yape, izipay) consolidando el caso `mixto` por sus montos parciales.
5. THE gráficos SHALL adaptar los colores de ejes/leyendas al tema oscuro (pattern de `resources/js/dashboard.js`).
6. WHEN se solicita `?export=csv`, THE Sistema SHALL devolver la serie día × fuente (ventas, lavados, cambios, total) del rango.

**Valida:** Propiedad 2 (consistencia entre serie y consolidado).

---

### Requisito 6: Días de mayor ingreso por actividad

**User Story:** Como Administrador, quiero ver los días con más ingreso y qué actividad los generó, para detectar patrones de demanda.

#### Criterios de Aceptación

1. THE reporte de ingresos SHALL incluir una tabla "Días de mayor ingreso" con los top-N días del rango (N por defecto 10) ordenados por ingreso consolidado descendente.
2. THE tabla SHALL desglosar por fila: fecha, ingreso por ventas, lavados y cambios, e ingreso total del día.
3. THE filas SHALL marcarse con un indicador cuando una sola actividad represente ≥ 50% del ingreso del día, indicando la actividad dominante.
4. WHEN el rango tiene menos de N días con datos, THE tabla SHALL mostrar solo los días existentes.
5. WHEN no hay operaciones en el rango, THE tabla SHALL mostrar un mensaje de vacío.

**Valida:** Requisito 6; la serie del Requisito 5 alimenta esta tabla.

---

### Requisito 7: Reporte de ventas

**User Story:** Como Administrador, quiero un reporte detallado de ventas, para revisar correlativos, cajeros y formas de pago.

#### Criterios de Aceptación

1. THE reporte SHALL admitir filtros: rango `desde`/`hasta`, usuario (`user_id`), método de pago (`efectivo|yape|izipay|mixto`) y búsqueda por correlativo (contiene).
2. THE vista SHALL mostrar KPIs: total vendido, número de ventas y ticket promedio del filtro aplicado.
3. THE vista SHALL mostrar agregados: total por usuario y total por método de pago.
4. THE detalle SHALL ser una tabla paginada con `paginate(10)`: correlativo, fecha, usuario, método de pago, subtotal y total.
5. WHEN se solicita `?export=csv`, THE Sistema SHALL devolver el detalle completo (sin paginar) con los filtros aplicados.

**Valida:** Filtros y correcto totalizado.

---

### Requisito 8: Reporte de lavados

**User Story:** Como Administrador, quiero un reporte de lavados, para saber qué vehículos, servicios y trabajadores generan más operaciones e ingresos.

#### Criterios de Aceptación

1. THE reporte SHALL admitir filtros: rango, tipo de vehículo (`vehiculo_id`), servicio (`servicio_id`), trabajador (`trabajador_id`). El universo de datos son los lavados **confirmados**.
2. THE vista SHALL mostrar KPIs: operaciones, ingresos y ticket promedio del filtro aplicado.
3. THE vista SHALL mostrar agregados: operaciones e ingresos **por tipo de vehículo**, **por servicio** y **por trabajador** (un trabajador cuenta la operación una vez por lavado en que participó).
4. THE detalle SHALL ser una tabla paginada `paginate(10)`: fecha, placa/automotor, tipo de vehículo, servicios, trabajadores, método de pago y total.
5. WHEN se solicita `?export=csv`, THE Sistema SHALL devolver el detalle completo con los filtros aplicados.

**Valida:** Filtros y agregados por dimensión.

---

### Requisito 9: Reporte de cambio de aceite

**User Story:** Como Administrador, quiero un reporte de cambios de aceite, para conocer qué productos se consumen y qué trabajadores realizan el servicio.

#### Criterios de Aceptación

1. THE reporte SHALL admitir filtros: rango, trabajador (`trabajador_id`) y producto (`producto_id`). El universo son los cambios **confirmados**.
2. THE vista SHALL mostrar KPIs: operaciones, ingresos y ticket promedio del filtro aplicado.
3. THE vista SHALL mostrar agregados: **por producto** (cantidad consumida e ingreso) y **por trabajador** (operaciones e ingreso).
4. THE detalle SHALL ser una tabla paginada `paginate(10)`: fecha, placa/automotor, productos (cantidad), trabajador, método de pago y total.
5. WHEN se solicita `?export=csv`, THE Sistema SHALL devolver el detalle completo con los filtros aplicados.

**Valida:** Filtros y agregados.

---

### Requisito 10: Reporte de inventario

**User Story:** Como Administrador, quiero un reporte de inventario, para saber qué se vende más, qué stock queda y cómo se distribuye por categoría y marca.

#### Criterios de Aceptación

1. THE reporte SHALL admitir filtros: rango, categoría (`categoria_id`) y marca (`marca_id`).
2. THE vista SHALL mostrar el **top de productos por cantidad vendida** y el **top por ingreso** en el rango, combinando `detalle_ventas` y `cambio_productos` (criterio de `DashboardService::topProductos` extendido con rango).
3. THE vista SHALL mostrar la tabla de **stock actual**: stock, inventario inicial del ciclo, % consumido, indicador de alerta (`esta_en_alerta`) y valorizado a costo (`stock × precio_compra`).
4. THE vista SHALL mostrar **resúmenes por categoría y por marca**: número de productos, stock total e ingresos del rango.
5. WHEN se solicita `?export=csv`, THE Sistema SHALL devolver el detalle de stock/productos con los filtros aplicados.

**Valida:** Agregados de inventario consistentes con los detalle_ventas/cambio_productos.

---

### Requisito 11: Reporte de clientes y automotores

**User Story:** Como Administrador, quiero conocer a mis clientes y vehículos más frecuentes, para fidelizar y planear.

#### Criterios de Aceptación

1. THE reporte SHALL admitir filtros: rango (por defecto últimos 30 días).
2. THE vista SHALL mostrar el **top de clientes por gasto** (suma de lavados + cambios confirmados del rango) y **por visitas** (número de operaciones) de cada cliente.
3. THE vista SHALL mostrar un indicador de recurrencia (visitas por mes en el rango) y el número de automotores por cliente.
4. THE vista SHALL mostrar el **top de automotores atendidos**: placa, marca/modelo, cliente, visitas e ingresos (lavados + cambios).
5. THE tabla de detalle SHALL ser paginada `paginate(10)`.
6. WHEN se solicita `?export=csv`, THE Sistema SHALL devolver el detalle de clientes con los filtros aplicados.

**Valida:** Propiedad 3 (gasto = suma de sus operaciones).

---

### Requisito 12: Reporte de caja

**User Story:** Como Administrador, quiero un reporte consolidado de cajas cerradas y egresos, para auditar flujos y saldos.

#### Criterios de Aceptación

1. THE reporte SHALL admitir filtros: rango por `fecha_cierre` (por defecto últimos 30 días).
2. THE universo SHALL ser las cajas **cerradas**.
3. THE vista SHALL mostrar KPIs: cajas cerradas, ingresos totales, egresos totales y saldo neto (suma de balances).
4. THE detalle SHALL listar por caja: apertura, cierre, usuario, monto inicial, ingresos, egresos y balance final (reusando `CajaService::calcularResumen`).
5. THE vista SHALL mostrar el **total de egresos por tipo de pago** y un listado de egresos agregados por descripción (monto) en el rango.
6. WHEN se solicita `?export=csv`, THE Sistema SHALL devolver el detalle de cajas con los filtros aplicados.

**Valida:** Balance por caja cuadra con `CajaService`.

---

### Requisito 13: Reporte de personal (asistencias y resumen de pago)

**User Story:** Como Administrador, quiero un reporte mensual de asistencias y un resumen de pago por trabajador, para planificar la planilla.

#### Criterios de Aceptación

1. THE reporte SHALL admitir filtros: mes (`mes` en formato `Y-m`, por defecto el mes actual) y trabajador (`trabajador_id`, opcional; si se omite, todos los activos).
2. THE vista SHALL mostrar por trabajador: días asistidos, % de asistencia sobre los días con al menos una marca en el mes y hora promedio de entrada.
3. THE vista SHALL mostrar el **resumen de pago**: `asistencias × pago_diario` por trabajador y el total general del mes.
4. IF el trabajador no tiene `pago_diario` (null por legado), THEN THE Sistema SHALL usar 0 para el cálculo y mostrarlo explícitamente como "Sin jornal".
5. THE detalle por trabajador SHALL ser una tabla paginada `paginate(10)`.
6. WHEN se solicita `?export=csv`, THE Sistema SHALL devolver el resumen por trabajador con sus valores de pago.

**Valida:** Propiedad 4 (asistencias × jornal = total) y Propiedad 5 (acotación por mes).

---

### Requisito 14: Reporte de kardex/movimientos

**User Story:** Como Administrador, quiero un reporte de movimientos de inventario por periodo, para controlar entradas, salidas y consumo.

#### Criterios de Aceptación

1. THE reporte SHALL admitir filtros: rango, producto (`producto_id`) y tipo (`entrada|salida`).
2. THE vista SHALL mostrar **entradas y salidas agregadas por producto** en el rango: totales por tipo, por fuente y el saldo neto del periodo.
3. THE vista SHALL mostrar el **consumo por producto** en el rango frente al stock actual.
4. THE detalle SHALL ser una tabla paginada `paginate(10)` con los movimientos del  filtro: fecha, producto, tipo, fuente, cantidad y stock después.
5. WHEN se solicita `?export=csv`, THE Sistema SHALL devolver el detalle de movimientos con los filtros aplicados.

**Valida:** Coherencia de agregados con `movimientos_kardex`.

---

### Requisito 15: Campo `pago_diario` en trabajadores

**User Story:** Como Administrador, quiero registrar el jornal diario de cada trabajador, para que el resumen de pago se calcule con datos reales.

#### Criterios de Aceptación

1. THE tabla `trabajadores` SHALL tener la columna `pago_diario` (`decimal(8,2)`) con **default 50**.
2. THE seeder `TrabajadorSeeder` SHALL crear trabajadores con `'pago_diario' => 50` (como valor por defecto explícito).
3. THE factory `TrabajadorFactory` SHALL incluir `'pago_diario' => 50`.
4. THE modelo `Trabajador` SHALL incluir `pago_diario` en `$fillable` y en `$casts` como `decimal:2`.
5. THE formularios de crear/editar trabajador SHALL incluir el campo `pago_diario` con validación `nullable|numeric|min:0`.
6. THE campo SHALL ser opcional en el formulario (sin valor = null, que el reporte trata como 0 con aviso).

**Valida:** Base del resumen de pago.

---

### Requisito 16: Exportación CSV

**User Story:** Como Administrador, quiero descargar cada reporte en CSV, para analizarlo fuera del sistema.

#### Criterios de Aceptación

1. EVERY ruta de reporte SHALL aceptar el query param `export=csv` y responder `Content-Type: text/csv`.
2. THE exportación SHALL usar **BOM UTF-8** (`\xEF\xBB\xBF`) y separador de campos `;` (compatible con Excel es-PE).
3. THE exportación SHALL usar los mismos servicios de agregación que la vista, aplicando los mismos filtros.
4. THE exportación SHALL incluir una fila de cabecera legible y no SHALL paginar (detalle completo).
5. THE nombre de archivo SHALL ser descriptivo (p. ej. `reporte-ventas-2026-08-01-al-2026-08-30.csv`).
6. WHEN la validación de filtros falla con `export=csv`, THE Sistema SHALL responder HTTP 422 JSON.

**Valida:** Formato y consistencia de exportación.

---

### Requisito 17: Impresión

**User Story:** Como Administrador, quiero imprimir cada reporte, para tenerlo en papel o PDF.

#### Criterios de Aceptación

1. EVERY vista de reporte SHALL incluir un botón "Imprimir" que ejecute `window.print()`.
2. THE impresión SHALL ocultar el sidebar de escritorio, la navegación inferior móvil y los botones de acción (variante `print:` de Tailwind).
3. THE impresión SHALL conservar las tablas y KPIs visibles (fondo claro en impresión).

**Valida:** Salida a papel/PDF legible.

---

### Requisito 18: Convenciones de fechas y zonas horarias

**User Story:** Como desarrollador garantizado por especificación, quiero que todos los períodos usen la hora de Perú, para que reportes y marcas coincidan con la operación real.

#### Criterios de Aceptación

1. TODOS los cálculos de rango SHALL usar `now()`/Carbon de la app (config `America/Lima`), nunca `date('...')` crudo ni la hora local del servidor.
2. Las comparaciones de día SHALL usar `->toDateString()`.
3. Las pruebas SHALL usar tiempos relativos a `now()`, nunca literales fijos.
4. La columna de fecha efectiva por fuente SHALL ser: ventas → `created_at`, lavados → `fecha`, cambio_aceites → `created_at` (criterio de `DashboardService`).

**Valida:** Correctitud temporal de todos los reportes.

---

## Convenciones que la implementación debe respetar

- Todo el código (controlador, servicios, vistas, JS, mensajes, commit messages) en español.
- Sin librerías nuevas: Chart.js ya es dependencia del proyecto (solo se importa en `reportes/ingresos.js`); CSV e impresión usan utilidades del propio Laravel (StreamedResponse) y Tailwind.
- Sin Form Requests: validación inline en el controlador con `$request->validate([...])`.
- Sin guardar datos: el módulo es de solo lectura; no abre caja, no aplica `error_caja` y no escribe auditoría.
- La guía de listados (`reajuste-listados.md`) aplica: `paginate(10)` en detalle, cabeceras `py-6`, celdas `py-8`, `overflow-x-auto`.
- La guía de modo oscuro (`guia-modo-oscuro.md`) aplica: clases semánticas `bg-surface`, `text-primary`, `text-secondary`, `border-main`, `divide-main`.
- `resources/js/reportes/ingresos.js` y `resources/js/reportes/print.js` deben añadirse al `input` de `vite.config.js` y cargarse con `@vite(...)` al final de `@section('content')`.
- Los datos para gráficos se inyectan vía `window.reportesIngresos = @json(...)` ANTES del `@vite` (único script inline permitido). Sin `<script>` inline de comportamiento.
- Rutas todas estáticas, dentro del grupo `permission:acceso-reportes` con prefijo `reportes` y nombre `reportes.*`.