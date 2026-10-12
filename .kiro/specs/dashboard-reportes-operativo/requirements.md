# Requisitos: evolución dashboard y reportes — métricas operativas

## Contexto

El dashboard presenta KPIs del día/mes, ingresos por fuente, métodos de pago,
productos más vendidos y stock bajo. El módulo de Reportes (permiso
`acceso-reportes`, solo lectura, filtros `desde/hasta`, CSV/PDF/impresión)
tiene 11 vistas: ingresos, ventas, lavados, cambio-aceite, inventario,
clientes, caja, personal, kardex, vehículo y compras-proveedor.

## Glosario

- **Atención**: lavado o cambio de aceite en estado `confirmado`.
- **Fecha efectiva**: lavados → `fecha`; cambios → `created_at`.
- **Participación**: presencia de un trabajador en el pivote del ticket.
- **Vehículo único**: `automotor_id` distinto y no nulo.
- **Tiempo de atención**: `tiempo_atencion_minutos`
  (`atencion_inicio_at` → `atencion_fin_at`).
- **Ticket operativo**: total (lavados + cambios confirmados) ÷ atenciones.

## Requisitos

### Requisito 1: Matriz de contraste

El análisis SHALL documentar por métrica: definición, fórmula, fuente de
datos, filtros, limitaciones y presentación (KPI / gráfico / reporte).

### Requisito 2: Tiempo promedio de atención

El sistema SHALL calcular el promedio de `tiempo_atencion_minutos` solo con
valor registrado, por tipo y global, en dashboard (rango del período) y en el
reporte operativo. Pendientes y nulos se excluyen y se informa la cobertura.

### Requisito 3: Tiempo de espera

El sistema SHALL NOT presentar tiempo de espera: no existe marca de llegada
separada del inicio del ticket. La vista operativa SHALL explicarlo en su
nota metodológica.

### Requisito 4: Servicios por trabajador (consolidado)

El reporte operativo SHALL mostrar participaciones por trabajador
(lavados + cambios), contando 1 por ticket en cada participante.

### Requisito 5: Vehículos atendidos por día

El dashboard SHALL mostrar atenciones y vehículos únicos del período; el
reporte operativo SHALL mostrar la serie diaria (atenciones + placas únicas)
con `sum(serie) == kpis.atenciones`.

### Requisito 6: Ticket promedio

No se crea indicador nuevo: el consolidado ya existe (dashboard + ingresos).
El reporte operativo SHALL mostrar el ticket operativo (sin ventas) con su
definición explícita.

### Requisito 7: Clientes recurrentes

El reporte operativo SHALL mostrar recurrentes (≥2 atenciones), total de
clientes y porcentaje. Las ventas de mostrador (sin `cliente_id`) se excluyen.

### Requisito 8: Servicios más solicitados

El reporte operativo SHALL reutilizar las agregaciones existentes
(servicios, tipos de vehículo, placas) sin duplicar reportes.

### Requisito 9: Horas pico

El reporte operativo SHALL mostrar la distribución por hora de
`atencion_inicio_at` (fallback `created_at`, hora de la app) y la hora pico.

### Requisito 10: Reglas transversales

Universo solo `confirmado`; importes con las reglas de caja/anulación
vigentes; fechas con `now()` (`America/Lima`) y `->toDateString()`;
permiso `acceso-reportes`; filtros `desde/hasta`; CSV (`;`, BOM) + PDF
(dompdf, sin gráficos) + impresión; `paginate(10)` donde haya detalle;
clases semánticas de modo oscuro; código y mensajes en español.
