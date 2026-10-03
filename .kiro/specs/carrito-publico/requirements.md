# Documento de Requisitos — Carrito de compra público (`/cuenta/carrito`)

## Introducción

Este documento define los requisitos del carrito de compra del lado público, limitado al alcance que pide el usuario: clientes con cuenta pública (rol `Cliente`, zona `/cuenta/*`) agregan productos desde la web pública, gestionan cantidades, ven subtotales y descargan un PDF con el detalle. Sin checkout, sin pago, sin reserva de stock.

Estado actual verificado: la web pública muestra productos sin compra posible (`card-producto-grande.blade.php:58-64` solo enlaza a `VER DETALLE`; `producto-detalle.blade.php:80-92` muestra `Agotado` o `Cotizar este producto`; comentario `:27-31` "no hay carrito aún"). El header trae el placeholder (`header.blade.php:94-106`: `href="#"`, contador `0` fijo). Precios/stock: `Producto` (`precio_venta decimal:2`, `stock/inventario integer`, `activo`), sin scopes (filtro `activo` repetido en controladores). El stock solo se descuenta en `VentaController::store` (`decrement('stock')` en transacción + Kardex); el carrito NO toca stock. PDF: `barryvdh/laravel-dompdf ^3.1` instalado y usado solo en reportes vía `ReportePdfService` (`loadView → a4 portrait → download`). JS público modular (`publica/app.js`, `productos-filtros.js` en `vite.config.js:12-14`). Sesión única guard `web`; zona privada exige `['auth','cliente','can:acceso-cuenta']` (`routes/web.php:137`); `EnsureCliente` + `puedeAccederCuenta()` (`User.php:102-113`). Zona horaria `America/Lima` (`now()`/Carbon, nunca `date()` crudo).

Decisión arquitectónica central: el carrito es una **lista de cotización persistida en BD** (`carritos` 1:1 por usuario + `carrito_items` con **precio snapshot**), no una reserva. El PDF es una proforma descargable, no compromete stock ni genera venta. La funcionalidad completa (agregar, ver, modificar, PDF) exige sesión de cuenta pública; el visitante sin sesión es redirigido a `cuenta.login`.

## Glosario

- **Sistema**: aplicación Laravel 12 del carwash (Blade + Tailwind 4, Vite 7).
- **Carrito**: fila `carritos` (`user_id UNIQUE`, timestamps); pertenece a una cuenta pública.
- **Ítem**: fila `carrito_items` (`carrito_id`, `producto_id`, `cantidad`, `precio_unitario` snapshot de `precio_venta` al agregar).
- **PrecioSnapshot**: `precio_venta` copiado al agregar; el PDF y los subtotales usan el snapshot, no el precio vivo.
- **Cuenta**: fila `users` con rol `Cliente` verificada y activa (`puedeAccederCuenta()`).
- **ZonaCuenta**: rutas `/cuenta/*` privadas (`auth + cliente + can:acceso-cuenta`).
- **PDF-Carrito**: PDF descargable con el detalle (DomPDF, `a4 portrait`).
- **AuditService**: `app/Services/AuditService.php` + tabla `registros_auditoria` (actor explícito en flujos guest según convención vigente).

---

## Requisitos

### Requisito 1: Botón agregar en el display total del producto

**Historia de usuario:** Como cliente autenticado, quiero agregar productos con cantidad desde la vista de detalle para armar mi lista de compra.

#### Criterios de Aceptación

1. WHEN el cliente autenticado abre `GET /nuestros-productos/{categoria}/{producto}` con stock > 0 y `activo`, THE Sistema SHALL mostrar selector de cantidad (1..stock) + botón "Agregar al carrito" junto al CTA actual.
2. WHEN el producto está agotado (`stock <= 0`) o inactivo, THE Sistema SHALL mostrar `Agotado` y ningún botón de agregar (igual que hoy, `producto-detalle.blade.php:80-92`).
3. WHEN envía `POST /cuenta/carrito/agregar` válido, THE Sistema SHALL crear/actualizar el ítem (suma cantidades, refresca `precio_unitario` al `precio_venta` vigente solo si el ítem es nuevo; si existe, mantiene su snapshot) y redirigir con `success` (o JSON si `expectsJson`).
4. IF el visitante no autenticado envía agregar, THEN THE Sistema SHALL redirigir a `cuenta.login` con mensaje `info` (sin crear nada).
5. THE botón SHALL funcionar sin JS (formulario POST + `@csrf`); el JS solo mejora (toast + contador).

### Requisito 2: Botón rápido en la esquina de las cards (hover)

**Historia de usuario:** Como cliente autenticado, quiero agregar 1 unidad desde la card sin abrir el detalle.

#### Criterios de Aceptación

1. WHEN el cliente autenticado pasa el cursor sobre `card-producto-grande` o `card-producto-pequena` con stock > 0 y `activo`, THE Sistema SHALL mostrar un botón pequeño `+` en la esquina que agrega 1 unidad vía `POST /cuenta/carrito/agregar`.
2. WHEN la card es de producto agotado/inactivo, THE Sistema SHALL no mostrar el botón (mantiene sello `Agotado`).
3. WHEN el visitante no autenticado pulsa el botón, THEN THE Sistema SHALL redirigir a `cuenta.login` (mismo Req. 1.4).
4. THE botón SHALL ser `type="submit"` dentro de un `<form>` (sin JS obligatorio), posicionado con Tailwind (`absolute`, visible en `group-hover`, siempre visible en táctil).

### Requisito 3: Ver el carrito (listado, cantidades, subtotales)

**Historia de usuario:** Como cliente autenticado, quiero ver mi carrito desde el icono del header, subir/bajar cantidades y ver subtotales.

#### Criterios de Aceptación

1. WHEN el cliente autenticado accede a `GET /cuenta/carrito`, THE Sistema SHALL listar solo sus ítems (scope `user_id` = sesión) con foto, nombre, `precio_unitario` snapshot, cantidad editable, subtotal por línea y total; vacío muestra estado vacío con enlace a productos.
2. WHEN modifica cantidad (`PATCH /cuenta/carrito/items/{item}`) con `1 <= cantidad <= stock vigente`, THE Sistema SHALL actualizar y recalcular; IF `cantidad` supera stock o producto inactivo, THEN THE Sistema SHALL rechazar con error junto al campo y mantener lo anterior.
3. WHEN quita un ítem (`DELETE /cuenta/carrito/items/{item}`) o vacía (`DELETE /cuenta/carrito`), THE Sistema SHALL eliminarlo(s) y redirigir con `success`.
4. WHEN un cliente A manipula un ítem de otro cliente B (ID ajeno), THE Sistema SHALL responder 403 (scoping estricto por `carrito.user_id`; sin Policy adicional por ser 1:1).
5. THE listado SHALL usar `paginate(10)` si excede (convención listados) u orden simple por agregado; tablas con `overflow-x-auto`; clases dark semánticas (`.bg-surface`, `.text-primary`, ...).
6. WHILE el visitante no está autenticado, THE Sistema SHALL redirigir `GET /cuenta/carrito` a `cuenta.login` (middleware de zona).

### Requisito 4: Contador del header

**Historia de usuario:** Como cliente autenticado, quiero ver en el header cuántos productos llevo.

#### Criterios de Aceptación

1. WHEN el cliente autenticado navega la web pública, THE Sistema SHALL mostrar en el icono del carrito la suma de cantidades de su carrito (0 si vacío), en vez del `0` fijo actual (`header.blade.php:104`).
2. WHEN el visitante no está autenticado, THE Sistema SHALL mostrar `0` y el icono enlaza a `cuenta.login`.
3. WHEN el cliente autenticado pulsa el icono, THE Sistema SHALL ir a `GET /cuenta/carrito`.
4. THE conteo SHALL resolverse con una sola consulta agregada (view composer solo para `publica.partials.header`, sin N+1).

### Requisito 5: PDF descargable del detalle

**Historia de usuario:** Como cliente autenticado, quiero descargar un PDF con mi carrito para guardarlo o presentarlo en el local.

#### Criterios de Aceptación

1. WHEN pulsa "Descargar PDF" (`GET /cuenta/carrito/pdf`), THE Sistema SHALL descargar `carrito-YYYYMMDD-HHMM.pdf` generado con DomPDF desde la vista `cuenta.carrito.pdf` (sin layout web, apta para impresión).
2. THE PDF SHALL contener: nombre/email del cliente, fecha-hora Lima (`now()`), líneas (producto, cantidad, precio unitario snapshot, subtotal), total y leyenda "Cotización referencial: no reserva stock ni genera venta".
3. IF el carrito está vacío, THEN THE Sistema SHALL redirigir al carrito con aviso (sin generar PDF).
4. THE generación SHALL reutilizar `ReportePdfService` (`loadView → a4 portrait → download`) si su API lo permite; si no, un servicio `Cuenta` mínimo con la misma convención.
5. THE endpoint SHALL exigir zona privada (guest → `cuenta.login`) y auditar (`modulo=cuenta, accion=carrito_pdf`).

### Requisito 6: Integridad de datos y auditoría

**Historia de usuario:** Como responsable del sistema, quiero un carrito íntegro, auditado y que nunca rompa ventas/stock.

#### Criterios de Aceptación

1. THE Sistema SHALL persistir `carritos(user_id UNIQUE FK cascade, timestamps)` + `carrito_items(carrito_id FK cascade, producto_id FK restrict, cantidad int, precio_unitario decimal 10,2, UNIQUE(carrito_id, producto_id), timestamps)`.
2. THE Sistema SHALL validar server-side: `producto_id` existe + `activo`, `1 <= cantidad <= stock` vigente al agregar/actualizar (sin reservar ni descontar stock).
3. THE Sistema SHALL auditar (`modulo=cuenta`): `carrito_agregar`, `carrito_actualizar`, `carrito_quitar`, `carrito_vaciar`, `carrito_pdf`, con actor = sesión (nunca secretos).
4. THE Sistema SHALL programar limpieza de carritos abandonados (p. ej. ítems de cuentas inactivas > N días — N por definir, por defecto 90) sin tocar ventas/Kardex.
5. THE entrega SHALL incluir tests automatizados (ver `design.md`): flujos guest/cliente, topes de stock, snapshot de precio, ownership A vs B, descarga PDF (content-type + nombre), con `RefreshDatabase` + `Permission::firstOrCreate + givePermissionTo`, tiempos relativos `now()` (Lima), `SESSION_DRIVER=array`, `MAIL_MAILER=array` (sin red).

### Requisito 7: Envío del carrito por WhatsApp (wa.me)

**Historia de usuario:** Como cliente autenticado, quiero enviar mi carrito al WhatsApp del local con un botón para que preparen mi pedido.

#### Criterios de Aceptación

1. WHEN el cliente autenticado con carrito no vacío pulsa "Enviar por WhatsApp" (`GET /cuenta/carrito/whatsapp`, `throttle:10,1`), THE Sistema SHALL generar el PDF en disco público (un archivo por usuario, nombre aleatorio, purga > 7 días) y redirigir (`302 away`) a `https://wa.me/{51+9 dígitos}?text=...` con resumen (cliente, líneas cantidad×producto—subtotal, total) + URL del PDF. wa.me solo admite texto: el PDF viaja como enlace.
2. THE número SHALL salir de `WHATSAPP_PEDIDOS` (`.env`, 9 dígitos PE → se antepone `51`); sin número, el botón se oculta y el endpoint responde error de validación.
3. IF el carrito está vacío, THEN THE Sistema SHALL redirigir al carrito con error (sin archivo ni redirección externa).
4. WHILE el visitante no está autenticado, THE Sistema SHALL redirigir a `cuenta.login`.
5. THE Sistema SHALL auditar (`modulo=cuenta, accion=carrito_whatsapp`) sin exponer secretos.

## Fuera de alcance (explícito, no implementar)

Checkout, pago, reserva o descuento de stock, cupones/descuentos, costo de envío, carrito de invitado con fusión al login, historial de carritos, notificaciones, edición de ficha comercial desde el carrito, app móvil/API (`routes/api.php` no existe).

## Decisiones pendientes (confirmar antes de implementar)

D1. Cantidad máxima por línea y máximo de líneas (propuesta: tope = stock vigente, sin tope de líneas salvo `paginate`). D2. Precio snapshot vs vivo en el carrito (propuesta: snapshot, rationale en diseño). D3. Días de limpieza de abandonados (propuesta: 90). D4. Guest: ¿botones visibles que redirigen a login (propuesta) u ocultos? D5. Nombre exacto del PDF y si incluye IGV desglosado (propuesta: solo total, sin IGV — el sistema no discrimina IGV hoy).
