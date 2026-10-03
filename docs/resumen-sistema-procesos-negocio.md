# Resumen general del sistema — procesos de negocio y lógica cubierta

> Documento de síntesis elaborado a partir del análisis de `docs/`, `.kiro/specs/`, migraciones, modelos, controladores y servicios.
> Fecha: 2026-10-03. Stack: Laravel 12 / PHP 8.2, Blade + Tailwind 4 (Vite 7), SQLite dev / MySQL prod, zona `America/Lima`.

## 1. Qué es el sistema

Sistema de gestión para **carwash / lubricentro** ("Carwash El Chinito") con dos caras:

- **Lado administrativo (panel):** operación diaria del local — ventas de mostrador, lavados, cambios de aceite, compras e inventario con Kardex, caja, personal y asistencia, reportes y dashboard, administración de usuarios/roles/clientes/vehículos y auditoría total.
- **Página pública:** vitrina (servicios, productos, marcas, quiénes somos, cotización) + **zona de clientes** (`/cuenta/*`): registro, login local y con Google, panel propio, vehículos del cliente y carrito de cotización con PDF/WhatsApp.

Idea central del negocio: el vehículo se atiende por **tickets en 2 etapas** (pendiente → confirmado/pagado), el dinero solo se mueve con **caja abierta**, y el inventario solo se mueve por **operaciones registradas** (nadie edita existencias a mano).

## 2. Procesos del lado administrativo

### 2.1 Ventas de mostrador (`VentaController`, permiso `acceso-ventas`)
Venta inmediata sin etapa pendiente: valida método de pago (`efectivo, yape, izipay, mixto` con montos), líneas y **tope de stock**; exige **caja activa** (`error_caja` si no); en una transacción crea la venta (correlativo `VTA-`, `caja_id`), sus detalles y **descuenta stock** (`decrement`) + salida Kardex (`fuente=venta`). Anular restaura stock con entrada compensatoria y borra la venta. Ticket Blade imprimible. Búsqueda AJAX de productos (solo activos con stock).

### 2.2 Lavados (`LavadoController`, `acceso-ventas`)
Flujo en 2 bandejas: **pendientes** (cola de atención) → **confirmados**. El registro crea el ticket pendiente sin caja ni pago, con precio calculado en servidor (precio del tipo de vehículo + servicios), foto (Cloudinary) y alta implícita de cliente+automotor (`ClienteAutomotorService`). La confirmación exige caja, valida pago + trabajadores + foto, cierra tiempos de atención (`TiempoAtencionService`) y pone `estado=confirmado, caja_id`. **No tocan stock ni Kardex.** Edición de pendientes, borrado (restaura foto) y ticket.

### 2.3 Cambio de aceite (`CambioAceiteController`, `acceso-ventas`)
Mismo patrón 2 etapas, pero **con productos y stock**: el registro pendiente no descuenta (solo valida topes); la confirmación es el **único punto que descuenta** (`decrement` + salida Kardex `fuente=cambio_aceite`, correlativo `CAM-XXXX`). Editar un confirmado restaura y vuelve a descontar; borrar confirmado restaura stock. Soporta varios trabajadores y productos N:N con precio/total por línea.

### 2.4 Compras, inventario y Kardex (`acceso-compras`, `acceso-inventario`)
- **Compras** (`CompraController`): máquina de estados `borrador → recibida/anulada`. El borrador no mueve nada; **recibir** exige caja y, con `lockForUpdate`, suma `stock+inventario`, registra entrada Kardex (`fuente=compra`, `CMP-`), actualiza `precio_compra` (marca `precio_incoherente` si supera la venta) y genera el **egreso en caja** (pago a proveedor). **Anular recibida** valida stock suficiente, registra salida compensatoria + ingreso compensatorio (nota de crédito) y pasa a `anulada`.
- **Productos**: altas nacen con `stock=0/inventario=0` sin Kardex; regla precio venta ≥ costo (si costo > 0); la reposición es operación de inventario (`updateStock` + entrada `INV-`). Categorías con contador, marcas con foto, toggle `activo`, foto Cloudinary.
- **Ajustes** (`AjusteInventarioService`): 5 tipos (`positivo/negativo/merma/daño/conteo_fisico`), correlativo `AJU-`, costo promedio (CPP) y Kardex.
- **Kardex** (`KardexService`, solo lectura en panel): única verdad de movimientos por producto (`tipo, fuente, origen_id, cantidad, stock_antes/despues, costo, usuario`); fuentes: venta, compra, cambio_aceite, inventario, ajustes, merma, daño, conteo. El servicio **nunca muta stock**, solo inserta.

### 2.5 Caja (`CajaController`, `acceso-caja` / `historial-caja`)
Apertura (monto inicial, anti-doble-apertura con lock) y cierre diarios; egresos manuales (`efectivo/yape`); historial y detalle solo con `historial-caja`. `CajaService` es el **único entrypoint de `caja_id`**: consolida ingresos (ventas + cambios + lavados confirmados, con desglose por método y reparto de mixtos), egresos y balance. Sin caja activa no hay cobros (ventas, confirmaciones, recepciones lo exigen).

### 2.6 Clientes, vehículos y automotores (`acceso-clientes`, `acceso-vehiculos`, `acceso-automotores`)
- **Clientes**: DNI 8 único (nullable), nombres, teléfono; borrado bloqueado con movimiento; búsqueda por placa y consulta DNI (`DniApiService`, resiliente).
- **Vehículos** (catálogo): tipos de vehículo con precio base del lavado.
- **Automotores** (físico): PK = placa normalizada, FK a cliente; marca/modelo/serie/color/motor/VIN; consulta de placa (`AutomotorApiService`, resiliente); se crean/enriquecen al confirmar tickets.
- Alta implícita cliente+automotor desde tickets sin salir del flujo.

### 2.7 Personal y asistencia (`acceso-trabajadores`, `acceso-asistencia`)
Trabajadores (DNI único, pago diario, foto; borrado bloqueado con tickets) y asistencia con calendario mensual, marcación manual **solo del día actual** y sincronización transaccional (`AsistenciaService`).

### 2.8 Reportes y dashboard (`acceso-reportes`)
9 reportes solo-lectura sobre universo **solo confirmado** (ingresos, ventas, lavados, cambio-aceite, inventario, clientes, caja, personal, kardex), con filtros de fecha, CSV/PDF/impresión. Dashboard: KPIs hoy/mes, serie diaria por fuente, métodos de pago, top productos y stock bajo (Chart.js).

### 2.9 Administración, accesos y auditoría
Usuarios/roles con Spatie (roles `Administrador` con bypass total, `Vendedor` operativo, `Cliente` solo-puerta; toggle `activo`; sin auto-borrado), contenido web clave-valor (textos, toggles, curaduría de marcas de la vitrina) y **auditoría total**: observers automáticos + `AuditService` (`registros_auditoria` polimórfica con antes/después, sensibles excluidos), con visor de acciones filtrable.

### 2.10 Integraciones externas (resilientes por diseño)
DNI y placa contra `api.json.pe` (mismo token `VEHICLE_API_TOKEN`); Cloudinary para fotos (productos, lavados, cambios, marcas, trabajadores); **nunca bloquean** formularios (devuelven `[]`/null ante fallo).

## 3. Procesos de la página pública

### 3.1 Vitrina (sin sesión)
Inicio, servicios (reales, con toggle web), productos por categoría (filtros letra/stock/búsqueda, `paginate(20)`), detalle con stock/precio, marcas (curaduría o top), quiénes somos y cotización (maqueta). Header adaptativo: invitado → INGRESAR al registro; `Cliente` → MI CUENTA; personal → login operativo; icono carrito con conteo real.

### 3.2 Cuenta de cliente (`/cuenta/*`, rol `Cliente`, permiso único `acceso-cuenta`)
La identidad sigue en `users` (la entidad `Cliente` **no** es autenticable); el vínculo es el pivote `customer_user_links` 1:1. Registro (transacción usuario PENDING + ficha + vínculo + token 24h + correo) → verificación (activa + autologin) → login (anti-enumeración, bloqueo 15 min tras 5 fallos) / logout → recuperación (token 60 min, respuesta genérica, revocación global de sesiones). **Google OIDC** (Code+PKCE, validación firma/`iss`/`aud`/`exp`/`nonce`): vincula por `provider+sub`, nunca auto-vincula por email (flujo de vinculación con sesión), impide quitar el último método. Panel propio (bienvenida, vehículos en lectura con ownership por vínculo, vincular/desvincular Google, cerrar otras sesiones). Separación total con el panel: sesión de cliente en `/dashboard` o `/login` vuelve a `/cuenta`; rutas operativas responden 403.

### 3.3 Carrito de cotización (`/cuenta/carrito`, solo clientes)
Lista persistida en BD (`carritos` 1:1 + `carrito_items` con **precio snapshot**). Botones en detalle (con cantidad) y en cards (hover, +1); página con cantidades editables topadas a stock, subtotales/total y vaciado; **PDF proforma** descargable (DomPDF, leyenda "no reserva stock"); **envío por WhatsApp** (`WHATSAPP_PEDIDOS`, Perú +51): genera el PDF en disco público (un archivo por usuario, purga > 7 días) y abre `wa.me` con resumen + enlace (wa.me solo transporta texto). El carrito valida topes pero **jamás mueve stock**; el stock solo cambia en ventas/confirmaciones/recepciones/ajustes.

## 4. Reglas transversales

| Regla | Alcance |
|---|---|
| Caja activa obligatoria | Ventas, confirmaciones, recepciones, egresos (`error_caja` si no) |
| Stock solo por operaciones | Descuentan: venta, confirmación de cambio, anulación de compra; suman: recepción, anulación de venta, ajustes; carrito/PDF nunca |
| RBAC Spatie (`permission:`) | 18 permisos `guard web`; `Administrador` bypass; `Cliente` aislado; rutas estáticas antes que resource |
| Auditoría | Observers + `AuditService`; nuevo modelo auditable → lista `AUDITABLE`; acciones ≤ 30 caracteres (columna MySQL) |
| Zona horaria | `America/Lima` único punto (`config/app.php`); `now()`/Carbon, nunca `date()` crudo |
| Normalización | `DataNormalizer` (DNI 8, teléfono 9, RUC 11, placa UPPER, nombres); reutilizar, no duplicar regex |
| Frontend | Módulos `resources/js/<modulo>/` en `vite.config.js` + `@vite` (sin inline salvo `window.*=@json`); SweetAlert2 y Chart.js; clases dark semánticas; listados `paginate(10)`, tablas `py-6/py-8` + `overflow-x-auto` |
| Tests | PHPUnit en SQLite memoria (`RefreshDatabase`, permisos creados antes, APIs/Cloudinary/OIDC mockeados) + property tests (100+ iter) + Vitest sin DOM |

## 5. Estado y pendientes conocidos

Cubierto y verificado con tests: todo lo descrito arriba (fases cuenta local, Google, carrito/WhatsApp, ownership, separación de sesiones). Pendientes documentados en specs (no implementados): MFA de administradores, checkout/pago del carrito, fusión de carrito de invitado, notificaciones, `SESSION_SECURE_COOKIE`/HSTS solo en producción, migraciones de clientes legados a cuentas, properties 2–10 del carrito/Google en formato extendido, y fallos preexistentes ajenos (`EgresoCajaTipoPago`, `ServicioWebProperties` p5).

## 6. Índice de documentación por dominio

| Dominio | Specs (`.kiro/specs/`) | Docs (`docs/`) |
|---|---|---|
| Ventas mostrador | `ventas-module`, `mejoras-inventario-ventas` | `fix-ventas-stock-validation`, `fix-descuento-manual-*` |
| Lavados / ingresos | `ingresos-module`, `ingresos-confirmacion`, `orden-atencion` | — |
| Cambio de aceite | `cambio-aceite`, `cambio-aceite-correlativo`, `cambio-aceite-confirmacion` | — |
| Compras / proveedores | `compras-ingreso-mercaderia`, `operaciones-avanzadas-compras`, `proveedor-crud`, `anular-compra-recibida`, `concurrencia-ventas`, `concurrencia-stock` | `plan-compras-ingreso-mercaderia`, `nueva_implmentacion_compra.txt`, `pendientes-implementacion-compras` |
| Inventario / Kardex / ajustes | `producto-crud`, `producto-categorias`, `stock-minimo`, `stock-update-modal`, `retirar-modal-stock`, `ajustes-inventario`, `ajustes-kardex-completo`, `kardex-costo-fuentes`, `valorizacion-cpp` | `flujo-productos-inventario-stock`, `feature-kardex-auditoria`, `flujo-cloudinary-productos`, `modulo-marcas` |
| Caja | `sistema-caja` | — |
| Clientes / vehículos | `clientes-crud`, `vehiculos-crud`, `servicios-crud` | `feature-automotores`, `alcance-flujo-mixto-clientes-vehiculos`, `plan-busqueda-placa-opciones` |
| Personal / asistencia | `trabajadores-crud`, `asistencia` | — |
| Reportes / dashboard | `reportes` | `modulo-reportes-informacion`, `feature-dashboard-estadisticas` |
| Usuarios / auth operativa | `login-panel`, `user-management`, `user-activation-toggle` | `feature-borrado-total-usuarios`, `plan-integracion-campos-entidades` |
| Auditoría | — | `feature-auditoria-acciones`, `feature-kardex-auditoria` |
| APIs externas | — | `feature-api-consulta-dni`, `feature-api-consulta-placa` |
| Web pública / contenido | `contenido-web`, `servicios-web`, `productos-publicos-filtros` | `design.md`, `pagina/*.txt` (referencia Shopify) |
| Cuenta cliente / carrito | `autenticacion-publica-clientes`, `carrito-publico` | `contraste-autenticacion-publica`, `resumen_requerimientos_arquitectura_autenticacion_publica` (origen, en Descargas) |
| Base / frontend / UI | `database-schema-implementation`, `js-modularization`, `responsive-layout` | `patron-validacion-formularios`, `guia-modo-oscuro.md`, `reajuste-listados.md` |
