# Resumen ejecutivo del sistema carwash — procesos de negocio

> Documento de síntesis: qué hace el sistema, por módulo, sin detalle de código.
> Fecha de actualización: 2026-10-13 (versión anterior: 2026-10-03, v0.2 → v0.3).
> Stack: Laravel 12 / PHP 8.2, Blade + Tailwind 4, zona `America/Lima`. Moneda: soles (S/).

## 1. Qué es el sistema

Sistema de gestión para **carwash / lubricentro** con dos caras:

- **Lado administrativo (panel):** operación diaria del local — ventas de mostrador, lavados, cambios de aceite, compras e inventario con Kardex, devoluciones, caja con arqueo, personal y asistencia, 12 reportes + dashboard, administración de catálogos, usuarios/roles/clientes/vehículos y auditoría total.
- **Página pública:** vitrina (servicios, productos, marcas, quiénes somos, cotización) + **zona de clientes** (`/cuenta/*`): registro, login local y con Google, panel propio, sus vehículos y carrito de cotización con PDF/WhatsApp.

Idea central del negocio (sin cambios): el vehículo se atiende por **tickets en 2 etapas** (pendiente → confirmado/pagado), el dinero solo se mueve con **caja abierta**, y el inventario solo se mueve por **operaciones registradas** (nadie edita existencias a mano).

**Cambios grandes desde el 2026-10-03 (v0.3):** renombrado del dominio vehículos, pagos escalables por catálogo, devoluciones y cambios, arqueo obligatorio al cierre, egresos clasificados, insumos de consumo interno, 3 reportes nuevos (operativo, historial por vehículo, compras por proveedor), coherencia aritmética de totales y unicidad de documentos. Sin cambios funcionales en vitrina ni en cuenta/carrito (solo adaptación al renombrado).

## 2. Procesos del lado administrativo

### 2.1 Ventas de mostrador
Venta inmediata sin etapa pendiente: valida líneas contra stock, exige **caja activa** y registra el pago en **líneas de pago** (simple = 1 línea; mixto = 2 o más cuya suma debe igualar el total, ahora validado en servidor). En una transacción crea la venta (`VTA-`), sus detalles y **descuenta stock** + salida Kardex. Anular restaura stock, salvo que la venta tenga **devoluciones** (bloqueado, ver 2.4). Ticket imprimible.

### 2.2 Lavados
Flujo en 2 bandejas (**pendientes** → **confirmados**). El registro crea el ticket pendiente sin caja ni pago, con precio base del **tipo de vehículo** + servicios, foto y alta implícita de cliente + vehículo. La confirmación exige caja, valida pago (líneas), trabajadores (varios, vía pivote) y tiempos de atención, y pone `confirmado + caja`. **No tocan stock.** Edición de pendientes, borrado y ticket.

### 2.3 Cambio de aceite
Mismo patrón 2 etapas, pero **con productos y stock**: el pendiente solo valida topes; la **confirmación es el único punto que descuenta** (salida Kardex `cambio_aceite`, `CAM-XXXX`). Editar un confirmado restaura y vuelve a descontar; borrar confirmado restaura. Novedad: **varios trabajadores por ticket** (pivote `cambio_aceite_trabajadores`; la columna única `trabajador_id` se eliminó). Soporta N productos con precio/total por línea y coherencia aritmética de totales.

### 2.4 Devoluciones y cambios de productos (nuevo)
Sobre una venta existente, sin anularla ni modificarla: **devolución parcial** (1..N líneas, motivo obligatorio) o **cambio** (devolución + venta sustituta vinculada en la misma transacción). Reincorpora stock solo vía Kardex (fuente `devolucion`, `DEV-XXXX`) y genera **egreso compensatorio en caja** (los mixtos se prorratean). Exige caja activa. La anulación completa sigue existiendo pero se rechaza si hay devoluciones. Listado e historial propios.

### 2.5 Compras, inventario y Kardex
- **Compras:** máquina `borrador → recibida/anulada`. El borrador no mueve nada; **recibir** exige caja, suma stock+inventario, registra entrada Kardex (`CMP-`), actualiza costo (marca `precio_incoherente` si supera la venta) y genera el **egreso clasificado como compra/abastecimiento**. **Anular recibida** valida stock, registra movimientos compensatorios y pasa a `anulada`.
- **Productos:** nacen con stock 0 sin Kardex; regla precio venta ≥ costo; reposición por operación de inventario (`INV-`). Novedad: campo **destino** (`comercial` o `consumo_interno`, con badge; informativo, no bloquea ventas ni tickets). Categorías con contador, marcas con foto, toggle activo.
- **Ajustes (rehabilitado + ampliado):** 6 tipos (`positivo, negativo, merma, daño, conteo_fisico` + nuevo **`consumo_interno`** para insumos de lavado como salida general con motivo, no atada a un ticket). Correlativo `AJU-`, costo promedio y Kardex. El flujo se reparó como prerrequisito (era inoperante).
- **Kardex (solo lectura):** única verdad de movimientos por producto. Fuentes actuales: venta, compra, cambio_aceite, inventario, ajustes, merma, daño, conteo, **devolucion, consumo_interno**.

### 2.6 Caja (con arqueo y egresos clasificados)
Apertura (anti-doble-apertura) y cierre diarios; egresos manuales; historial y detalle con permiso separado. Novedades:
- **Arqueo obligatorio al cierre:** se compara el efectivo esperado del sistema (inicial + ingresos en efectivo − egresos en efectivo; Yape/tarjeta no son físico) con el contado físicamente; la diferencia (sobrante/faltante) queda registrada con usuario, fecha y observación, sin bloquear el cierre. Sin arqueo no hay cierre.
- **Egresos clasificados:** cada egreso lleva categoría obligatoria (insumos, mantenimiento, servicios básicos, movilidad, pago a trabajador, otros; `compra/abastecimiento` reservada al sistema) + trabajador opcional + descripción. El reporte de caja agrega por categoría.
- `CajaService` sigue siendo el **único entrypoint de `caja_id`**: consolida ingresos (ventas + cambios + lavados confirmados, con desglose por método desde las líneas de pago), egresos y balance. Sin caja activa no hay cobros.

### 2.7 Métodos de pago (nuevo catálogo)
Catálogo gestionable por el dueño (`efectivo, yape, izipay` de origen; ampliable sin migraciones, ej. Plin). Cada método define si es **efectivo físico** (uno solo, lo usa el arqueo) y su orden en formularios. Se **desactiva**, no se elimina (borrado bloqueado con pagos). Los tickets guardan **líneas de pago** en una tabla única en vez de columnas fijas por ticket; el valor `mixto` desapareció como método (un mixto son 2..N líneas). Formularios iguales para el operador; reportes, caja y dashboard agrupan por método desde las líneas.

### 2.8 Clientes, vehículos y tipos de vehículo (renombrado)
- **Clientes:** DNI 8 (ahora **único**), nombres, teléfono; borrado bloqueado con movimiento; búsqueda por placa y consulta DNI (resiliente). Novedad: botón **Lavados** en el listado abre un modal con el resumen de lavados confirmados (total, último, vehículos con conteo), sin salir de la página.
- **Tipos de vehículo** (antes `vehiculos`, ahora `tipos_vehiculo`): catálogo con precio base del lavado, CRUD propio, borrado bloqueado con lavados.
- **Vehículos** (antes `automotores`, ahora `vehiculos`): vehículo físico, PK = placa normalizada, dueño cliente; marca/modelo/serie/color/motor/VIN (**VIN ahora único**); consulta de placa resiliente; se crean/enriquecen al confirmar tickets. Alta implícita cliente+vehículo desde tickets.

### 2.9 Personal y asistencia
Trabajadores (DNI único, pago diario, foto; borrado bloqueado con tickets) y asistencia con calendario mensual, marcación manual **solo del día actual** y sincronización transaccional. Sin cambios funcionales en el período (solo adaptación al pivote multi-trabajador de cambios).

### 2.10 Reportes y dashboard (12 reportes + dashboard operativo)
Panel de solo lectura sobre universo **solo confirmado**, filtros de fecha, CSV/PDF/impresión. A los 9 anteriores se suman 3:
- **Operativo (nuevo):** atenciones (lavados + cambios), vehículos únicos, atenciones/día, tiempo promedio, ticket operativo, recurrencia de clientes, serie diaria sin huecos, ranking por trabajador/servicio/tipo de vehículo/placa, hora pico y tiempos por tipo.
- **Historial por vehículo (nuevo):** trazabilidad cronológica por placa (ficha, KPIs, servicios y productos usados, historial unificado). Sin placa muestra mensaje de búsqueda, no 404.
- **Compras por proveedor (nuevo):** comparativo general + ficha por proveedor (KPIs, frecuencia de productos, evolución de precios con mín/máx/variación, historial con estados).
- **Ampliados:** clientes (frecuencia/visitas por mes, automotores distintos vs. registrados), caja (egresos por categoría y por descripción).
- **Dashboard:** KPIs hoy/mes, serie diaria por fuente, métodos de pago (etiquetas desde el catálogo), top productos, stock bajo y **resumen operativo** (atenciones, tiempo promedio, vehículos únicos) reutilizando las reglas del reporte operativo, con selector 7/30 días/mes.

### 2.11 Administración, accesos y auditoría
Usuarios/roles con Spatie (Administrador con bypass total, Vendedor operativo, Cliente solo-puerta; toggle activo; sin auto-borrado), contenido web de la vitrina y **auditoría total** (observers + antes/después). Novedades: modelos auditables **TipoVehiculo, MetodoPago y DevolucionVenta** (Automotor sale); permisos nuevos **`acceso-tipos-vehiculo`, `acceso-metodos-pago`, `acceso-ajustes`** (19 permisos en total). Coherencia aritmética centralizada: los totales de cabecera se recalculan en servidor (línea = cantidad × precio; cabecera admite como máximo el recargo de facturación) para ventas, compras y cambios.

### 2.12 Integraciones externas (resilientes por diseño)
DNI y placa contra `api.json.pe` (mismo token); Cloudinary para fotos; **nunca bloquean** formularios.

## 3. Procesos de la página pública

Sin cambios funcionales en el período; solo adaptación interna al renombrado de vehículos (placa como clave).

### 3.1 Vitrina (sin sesión)
Inicio, servicios reales, productos por categoría (filtros, `paginate(20)`), detalle con stock/precio, marcas, quiénes somos y cotización. Header adaptativo según sesión y carrito con conteo real.

### 3.2 Cuenta de cliente (`/cuenta/*`)
La identidad sigue en `users` vinculada 1:1 a la ficha `Cliente`. Registro con verificación por correo (24 h) → login (anti-enumeración, bloqueo tras 5 fallos) / logout → recuperación (60 min, revocación global). **Google OIDC** con vinculación controlada (nunca auto-vincula por email). Panel propio (vehículos en lectura con ownership, vincular/desvincular Google, cerrar otras sesiones). Separación total con el panel operativo.

### 3.3 Carrito de cotización (solo clientes)
Lista persistida en BD con **precio snapshot**; cantidades topadas a stock; subtotales/total y vaciado; **PDF proforma** ("no reserva stock"); **envío por WhatsApp** (PDF público por usuario con purga > 7 días + enlace `wa.me`). Jamás mueve stock.

## 4. Reglas transversales

| Regla | Alcance |
|---|---|
| Caja activa obligatoria | Ventas, confirmaciones, recepciones, devoluciones, egresos |
| Cierre exige arqueo | Contado + observación si hay diferencia; arqueo inmutable |
| Stock solo por operaciones | Descuentan: venta, confirmación de cambio, anulación de compra; suman: recepción, anulación de venta, devolución, ajustes; carrito/PDF nunca |
| Pagos por líneas | 1 línea simple, N líneas mixto con suma == total; catálogo `metodos_pago`; `es_efectivo` define el físico |
| RBAC Spatie | 19 permisos `guard web`; Administrador bypass; Cliente aislado; estáticas antes que resource |
| Auditoría | Observers + servicio; nuevo modelo auditable → lista de auditables; acciones ≤ 30 caracteres |
| Unicidad | DNI de cliente único; VIN de vehículo único |
| Coherencia de totales | Servidor recalcula líneas y valida cabecera (tope facturación) |
| Zona horaria | `America/Lima` único punto; `now()`/Carbon, nunca `date()` crudo |
| Normalización | Centralizada (DNI 8, teléfono 9, RUC 11, placa UPPER, nombres) |
| Frontend | Módulos por vista en `vite.config.js` + `@vite` (sin inline salvo datos); SweetAlert2 y Chart.js; listados `paginate(10)` |
| Tests | PHPUnit en SQLite memoria + property tests (100+ iter) + Vitest sin DOM; APIs/Cloudinary/OIDC mockeados |

## 5. Estado y pendientes conocidos

Cubierto y verificado con tests: todo lo descrito arriba (devoluciones, arqueo, egresos clasificados, consumo interno, métodos de pago, renombrado, operativo/vehículo/compras-proveedor, coherencia de totales, unicidad, frecuencia de lavados). Pendientes documentados en specs (no implementados): MFA de administradores, checkout/pago del carrito, fusión de carrito de invitado, notificaciones, endurecimiento de cookies/HSTS solo en producción, migraciones de clientes legados a cuentas y fallos preexistentes ajenos.

## 6. Índice de documentación por dominio

| Dominio | Specs (`.kiro/specs/`) | Docs (`docs/`) |
|---|---|---|
| Ventas mostrador | `ventas-module`, `mejoras-inventario-ventas` | `fix-ventas-stock-validation`, `fix-descuento-manual-*` |
| Devoluciones y cambios | `devoluciones-ventas` | `feature-devoluciones-ventas` |
| Lavados | `ingresos-module`, `ingresos-confirmacion`, `orden-atencion` | — |
| Cambio de aceite (multi-trabajador) | `cambio-aceite`, `cambio-aceite-confirmacion`, `cambio-aceite-pivote-trabajadores` | — |
| Métodos de pago | — (diseño en docs) | `diseno-metodos-pago` |
| Compras / proveedores | `compras-ingreso-mercaderia`, `operaciones-avanzadas-compras`, `proveedor-crud`, `anular-compra-recibida`, `concurrencia-*` | `plan-compras-ingreso-mercaderia`, `pendientes-implementacion-compras`, `feature-reporte-compras-proveedor` |
| Inventario / Kardex / ajustes / consumo interno | `producto-crud`, `producto-categorias`, `stock-update-modal`, `ajustes-kardex-completo`, `kardex-*`, `valorizacion-cpp` | `flujo-productos-inventario-stock`, `feature-kardex-auditoria`, `feature-consumo-interno-insumos` |
| Caja (arqueo + egresos) | `sistema-caja` | `feature-arqueo-cierre-caja`, `feature-egresos-clasificados` |
| Clientes / vehículos / tipos | `clientes-crud`, `vehiculos-crud`, `renombrado-tipo-vehiculo` | `feature-automotores`, `feature-frecuencia-lavados-cliente`, `alcance-flujo-mixto-clientes-vehiculos` |
| Personal / asistencia | `trabajadores-crud`, `asistencia` | — |
| Reportes / dashboard | `reportes`, `dashboard-reportes-operativo` | `modulo-reportes-informacion`, `feature-dashboard-estadisticas`, `feature-reporte-historial-vehiculo` |
| Usuarios / auth operativa | `login-panel`, `user-management`, `user-activation-toggle` | `feature-borrado-total-usuarios` |
| Auditoría | — | `feature-auditoria-acciones` |
| APIs externas | — | `feature-api-consulta-dni`, `feature-api-consulta-placa` |
| Web pública / contenido | `contenido-web`, `servicios-web`, `productos-publicos-filtros` | `design.md`, `pagina/*` |
| Cuenta cliente / carrito | `autenticacion-publica-clientes`, `carrito-publico` | `contraste-autenticacion-publica` |
| Base / modelo de datos | `database-schema-implementation` | `diagrama-entidad-relacion.md`, `estructura-componentes.md` |
