# Diseño: Métodos de pago escalables (`metodos_pago` + `pagos`)

> Estado: IMPLEMENTADO (2026-10-10, migraciones `2026_10_11_000003/000004`). `mixto` como valor desapareció; el filtro de reportes lo conserva como "más de 1 línea".

## 1. Problema

`ventas`, `lavados` y `cambio_aceites` repiten 4 columnas (`metodo_pago`, `monto_efectivo`, `monto_yape`, `monto_izipay`; migración `2026_05_01_230000_add_metodo_pago_to_ventas_cambio_aceites_ingresos.php`). `metodo_pago` es `enum('efectivo','yape','izipay','mixto')`. Agregar un medio nuevo (ej. Plin) hoy exige: migración en 3 tablas + ~15 archivos (3 modelos, 3 controladores con reglas en ~7 métodos, `CajaService::calcularResumen`, `DashboardService::metodoPago/acumularMetodoPago`, `ReporteVentasService::porMetodo`, filtros de `ReporteController`, `DevolucionVentaController` con mapeo `izipay→tarjeta`, ~10 vistas Blade con radios/bloque mixto/labels, JS por módulo). Además hay una inconsistencia: egresos usan `transferencia/tarjeta` mientras ingresos usan `izipay`.

Semántica actual: pago simple → `total` con montos en null; `mixto` → desglose en hasta 3 montos (la igualdad suma==total solo se valida en JS, no en servidor).

## 2. Propuesta: dos tablas nuevas

### 2.1 `metodos_pago` (catálogo gestionable por el dueño, CRUD Administrador)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | PK autoincremental | |
| `codigo` | string único | clave estable (`efectivo`, `yape`, `izipay`, `plin`); la usan código/reportes; inmutable tras crear |
| `nombre` | string(50) | etiqueta visible |
| `activo` | boolean default true | se desactiva, no se elimina (preserva historial) |
| `es_efectivo` | boolean | solo un método en true; lo usa el arqueo para el conteo físico |
| `orden` | integer | orden en formularios |
| `timestamps` | | |

Seed: efectivo, yape, izipay. Permiso `acceso-metodos-pago`; eliminar bloqueado si tiene pagos (restrict, mismo patrón que clientes/vehículos).

### 2.2 `pagos` (líneas por ticket; hace de pivote/detalle polimórfico)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | PK | |
| `pagable_type` | string | `Venta`, `Lavado` o `CambioAceite` |
| `pagable_id` | unsigned | id del ticket |
| `metodo_pago_id` | FK → `metodos_pago` (restrict) | |
| `monto` | decimal(10,2) | |
| `user_id`, `caja_id` | FK/nullable | trazabilidad y caja |
| `timestamps` | | |

Índice compuesto (`pagable_type`, `pagable_id`). Una sola tabla evita 3 pivotes idénticas (`venta_pagos`, `lavado_pagos`, `cambio_aceite_pagos`).

## 3. Cómo se maneja el mixto (flujo ocasional del negocio)

El valor `mixto` desaparece como método: un pago mixto son 2–3 filas en `pagos`. Ejemplo: lavado #15 de S/ 100 con S/ 40 efectivo + S/ 60 yape → filas `(Lavado,15,efectivo,40)` y `(Lavado,15,yape,60)`. El origen queda en cada fila (`pagable_type/id`), así que no se triplica nada: el ticket muestra su desglose, la caja suma por método cruzando los 3 orígenes (ya itera las 3 fuentes) y los reportes agrupan por método con el origen como dato. Pago simple = 1 fila. El formulario no cambia para el operador (mismo bloque de medios+montos); el servidor valida que la suma de líneas == `total` (hoy solo lo valida el JS).

## 4. Qué cambia en el código existente

- Tablas `ventas/lavados/cambio_aceites`: pierden las 4 columnas (migración + backfill: 1 línea por ticket simple, hasta 3 si era mixto; verificación al céntimo de totales y caja).
- Modelos: `pagos(): MorphMany` en los 3; `metodoPago()` deja de ser atributo.
- Controladores: reglas `pagos required|array|min:1`, `pagos.*.metodo_pago_id exists:metodos_pago,id`, `pagos.*.monto gt:0` + suma==total; `sync/crear` de líneas tras cada `create/update`.
- Un solo componente Blade/JS de líneas de pago reutilizado en los 3 módulos (reemplaza radios fijos + bloque mixto triplicados).
- `CajaService::calcularResumen`, `DashboardService::metodoPago`, `porMetodo`, filtros y devoluciones (mapeo a `tipo_pago` de egresos) agrupan por `metodo_pago_id`; unificar `izipay/tarjeta/transferencia` en el catálogo.
- Auditoría: registrar `metodos_pago` como módulo; los `pagos` se auditan vía sus tickets.

## 5. Fases recomendadas

- **Fase 1 (barata, sin migrar datos):** catálogo único en código (`App\Support\MetodosPago`: códigos, etiquetas, colores) + validación/Blade/JS compartidos + validación servidor de suma mixto. Reduce "agregar método" de ~15 archivos a ~4–5.
- **Fase 2 (este diseño, cuando llegue el 4to método o mixto de N vías):** tablas + backfill + CRUD.

## 6. Decisiones abiertas

1. ¿`pagos` polimórfica (propuesta) o 3 pivotes separadas?
2. ¿Bandera `es_efectivo` o el arqueo sigue hardcodeando `efectivo`?
3. ¿Unificar ya `izipay/tarjeta/transferencia` en el seed?
4. ¿Tope de líneas por ticket o medios restringidos por tipo de ticket?
