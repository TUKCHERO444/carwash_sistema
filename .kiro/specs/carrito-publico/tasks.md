# Plan de Implementación — Carrito de compra público (`/cuenta/carrito`)

## Visión General

Carrito-cotización en BD (sin checkout/pago/stock) para cuentas públicas: migraciones + modelos + `CarritoService` como único punto de escritura, botones en detalle/cards (forms sin JS + mejora `fetch`), página de carrito, PDF proforma, contador en header y tests. Todo aditivo: no se tocan ventas, Kardex, stock ni el panel.

Convenciones obligatorias: `America/Lima` (`now()`/Carbon), rutas estáticas antes que resource, cada vista JS con su `resources/js/cuenta/*.js` en `vite.config.js input` + `@vite` al final de `@section('content')` sin inline salvo `window.*=@json`, clases dark `.bg-surface/.input-main/...`, `paginate(10)`, `overflow-x-auto`, `composer run test` + `npm run test` cuando toque JS, `vendor/bin/pint`, permisos en tests con `Permission::firstOrCreate + givePermissionTo`, sin red real (sin mocks externos necesarios aquí).

> No empezar código hasta confirmar D1–D5 de `requirements.md` (propuestas por defecto anotadas si no hay respuesta).
>
> Acta 2026-10-03 (usuario autorizó implementar): D1 tope = stock vigente, sin tope de líneas; D2 snapshot sí; D3 purga 90 días; D4 botones visibles + redirect a login; D5 PDF `carrito-YYYYMMDD-HHMM.pdf`, sin IGV.

## Tareas

- [ ] 0. Decisiones y preparación
  - Confirmar D1 (topes: propuesta tope = stock, sin tope de líneas), D2 (snapshot: propuesta sí), D3 (purga 90 días), D4 (botones visibles + redirect login), D5 (PDF `carrito-YYYYMMDD-HHMM.pdf`, sin IGV).
  - Registrar acta en este spec.
  - _Requisitos: todos (D1–D5)_

- [x] 1. Migraciones + modelos (aditivos)
  - Crear `xxxx_create_carritos_table` (`user_id FK users cascade UNIQUE`, timestamps).
  - Crear `xxxx_create_carrito_items_table` (`carrito_id FK cascade`, `producto_id FK restrict`, `cantidad int`, `precio_unitario decimal(10,2)`, `UNIQUE(carrito_id, producto_id)`, índices).
  - Modelos `Carrito` (relaciones + `total`/`conteo`), `CarritoItem` (relaciones + `subtotal`), `User::carrito()` HasOne. `Producto` sin cambios.
  - Registrar en `AuditServiceProvider::$modelos` + `AuditService::AUDITABLE` si aplica (respetando `SENSIBLES/EXCLUIDOS`).
  - _Requisitos: 6.1_
  - Hecho 2026-10-03: `2026_10_03_000006/000007`, modelos + `User::carrito()`; en `AUDITABLE` + mapa `cuenta` sí, en provider `$modelos` no (evita duplicar filas del observer; el negocio audita manual con actor).

- [x] 2. `CarritoService` + contador header
  - Crear `app/Services/CarritoService.php`: `agregar/actualizarCantidad/quitar/vaciar/resumen/conteoParaHeader` con transacciones + `lockForUpdate`, validación activo/stock, auditoría `carrito_*` (`modulo=cuenta`, actor sesión).
  - View composer en `AppServiceProvider` solo para `publica.partials.header` con `carritoConteo`.
  - _Requisitos: 3.1, 4.1, 4.4, 6.2, 6.3_

- [x] 3. Botones agregar (detalle + cards)
  - `Cuenta\CarritoController@agregar` + `POST /cuenta/carrito/agregar` (`throttle:60,1`); respuesta redirect `back()+success` o JSON si `expectsJson`.
  - `producto-detalle.blade.php`: selector cantidad (`max=stock`) + botón junto al CTA; agotado intacto.
  - `card-producto-grande/pequena.blade.php`: form oculto (`producto_id`, `cantidad=1`) + botón `+` esquina hover/táctil; sin stock intactas.
  - _Requisitos: 1.1–1.5, 2.1–2.4_

- [x] 4. Página del carrito
  - `CarritoController@index/actualizar/quitar/vaciar` + rutas (`PATCH/DELETE`, estáticas antes que resource); scoping `findOrFail` por carrito propio.
  - `cuenta/carrito/index.blade.php`: tabla `overflow-x-auto`, `paginate(10)`, forms por línea, vaciar, total, botón PDF; dark tokens; `@vite carrito.js`.
  - `resources/js/cuenta/carrito.js` (entrada Vite): intercepta `form[data-carrito]`, `fetch` JSON, actualiza `[data-carrito-conteo]` + toast, fallback nativo.
  - _Requisitos: 3.1–3.5_

- [x] 5. Header + PDF
  - `header.blade.php:94-106`: `href="#"`→`carrito.index`/`cuenta.login`, contador fijo→`carritoConteo`.
  - `CarritoController@pdf` + `GET /cuenta/carrito/pdf` (antes de `items/{item}`): vacío→redirect con aviso; si no, reutilizar `ReportePdfService` o servicio mínimo (`loadView('cuenta.carrito.pdf') → a4 portrait → download`) + auditoría `carrito_pdf`.
  - `cuenta/carrito/pdf.blade.php`: HTML+CSS inline apto DomPDF, datos cliente/fecha Lima/líneas/total + leyenda no-reserva.
  - _Requisitos: 4.1–4.3, 5.1–5.5_
  - Hecho 2026-10-03: reutilizado `ReportePdfService::descargar` tal cual.

- [x] 6. Limpieza programada
  - Scheduler en `routes/console.php`: purga ítems/carritos abandonados (> 90 días, D3) con query directa; nunca ventas/Kardex.
  - _Requisitos: 6.4_

- [x] 7. Tests ejemplo (`tests/Feature/Cuenta/Carrito*Test.php`)
  - Detalle/card muestran botón o `Agotado`; agregar válido/inválido; guest redirige sin persistir; actualizar/quitar/vaciar; ownership A vs B; header conteo guest/cliente; PDF descarga vs vacío; auditoría sin secretos + stock intacto.
  - _Requisitos: 1–6_
  - Hecho 2026-10-03: `CarritoTest` (14 tests) en verde.

  - [x] 7.1 Property tests (`CarritoPropertyTest.php`, tag `// Feature: carrito-publico, Property {N}`, faker ≥100 iter): P1 snapshot+topes, P2 totales, P3 precio estable, P4 ownership, P5 guest, P6 PDF, P7 stock intacto.
  - [ ]* 7.2 JS property (`tests/js/cuenta/carrito.property.test.js`, `fast-check`, `npm run test`) solo si hay lógica pura exportable; si no, justificar omisión.
  - Justificación 7.2: el módulo solo hace DOM/`fetch` (sin lógica pura en Node); omitido. `npm run test` (62 tests existentes) en verde.

- [x] 8. Checkpoint final
  - `composer run test` + `npm run test` (si aplica) + `vendor/bin/pint` verdes; `migrate:fresh --seed` ok; `GET /ping` ok; flujo manual guest→registro→agregar→carrito→PDF. Preguntar al usuario.
  - Resultado 2026-10-03: 18/18 carrito + `Publica*`/`Auth`/`Cuenta` en verde; `npm run test` 62/62; Pint limpio; `npm run build` regenerado (manifest con `carrito.js`); incidencias del camino: manifest desactualizado (500 en `@vite`, resuelto con build) y `aria-label` con nombre que rompía `assertDontSee` (generalizado). Fallos ajenos preexistentes ya documentados (`EgresoCajaTipoPago`, `ServicioWebProperties` p5).

- [x] 9. Envío por WhatsApp (alcance ampliado 2026-10-03, Req. 7)
  - `WHATSAPP_PEDIDOS` (`.env` + `.env.example`, `config/services.php whatsapp`), `CarritoService::enlaceWhatsApp()` (normaliza 9 dígitos → 51, PDF en `storage/app/public/carrito-pdfs/` un archivo por usuario, mensaje con resumen + URL, auditoría `carrito_whatsapp`), `GET /cuenta/carrito/whatsapp` (`throttle:10,1`, `redirect()->away`), botón verde con `target=_blank` (solo si hay número), `ReportePdfService::guardar()` (misma config que `descargar`), purga de PDFs > 7 días en scheduler, `CarritoWhatsAppTest` (6 tests) en verde. Límite honesto: wa.me solo transporta texto; el PDF viaja como enlace (en local, `localhost` no abre desde el teléfono; en producción con dominio público sí).

## Notas

- Tareas `*` opcionales para MVP rápido (mínimo: 0–6 + 8 con tests ejemplo).
- Trazabilidad: cada tarea cita requisitos; cada property cita `Property {N}` del `design.md`.
- Tiempos siempre relativos `now()` (Lima); nunca literales fijos ni `date()` crudo.
- Sin red real: el flujo no usa APIs externas ni Cloudinary (foto vía `foto_url` existente).
