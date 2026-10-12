# Diseño: métricas operativas

## Decisión de distribución

- **Dashboard**: 3 tarjetas nuevas (`Operativa del período`) con el rango del
  `?periodo` seleccionado: atenciones, tiempo promedio, vehículos únicos.
  Delega en `DashboardService::resumenOperativo()`, que reutiliza
  `ReporteOperativoService::kpis()` (una sola fuente de fórmulas).
- **Reportes**: UNA ruta nueva `GET /reportes/operativo`
  (`reportes.operativo`, grupo `acceso-reportes`) con
  `ReporteOperativoService`. Sin módulo independiente, sin permiso nuevo,
  sin migraciones. Reutiliza `DateRangeFiltro`, `filtrosExtras`,
  `nombreArchivo`, `ReporteCsvService`, `ReportePdfService`, parciales
  `filtros`/`acciones`, `print.js` y tarjeta en `reportes.index`.
- **JS nuevo**: `reportes/operativo.js` (barras: serie diaria + por hora,
  mismo patrón que `ingresos.js`), registrado en `vite.config.js` y cargado
  con `@vite` + `window.operativoSerie/Horas` (único inline permitido).

## Componentes

1. `app/Services/Reportes/ReporteOperativoService.php`
   - `kpis()`: atenciones, lavados/cambios, vehículos únicos
     (distinct union PHP), atenciones/día, tiempo promedio + cobertura,
     ticket operativo, recurrencia.
   - `serieDiaria()`: días sin huecos (`DateRangeFiltro::diasEntre`),
     atenciones + placas únicas por día (agrupación PHP, agnóstico a BD).
   - `porTrabajador()`: dos agregados por pivote + merge PHP por id.
   - `porServicio()` / `porTipoVehiculo()` / `topPlacas(10)`.
   - `porHora()`: `Carbon::parse(atencion_inicio_at ?? created_at)->hour`,
     24 posiciones + pico.
   - `tiempos()`: promedios por tipo + global.
   - `filasSerie()`: filas para CSV.
2. `DashboardService::resumenOperativo($desde, $hasta)` (delega en `kpis()`).
3. `ReporteController::operativo()` + vistas `reportes/operativo.blade.php`
   y `reportes/pdf/operativo.blade.php` (KPIs, gráficos, tablas, nota
   metodológica). CSV = serie diaria.

## No implementado (y por qué)

- **Tiempo de espera**: sin `llegada_at` / `inicio_servicio_at` no hay
  fórmula fiable. Alternativa mínima futura: dos columnas nuevas en
  `lavados`/`cambio_aceites` (`llegada_at`, `inicio_servicio_at`) fijadas en
  `store()` y al iniciar el trabajo; entonces
  `espera = inicio_servicio − llegada`, `atención = fin − inicio_servicio`.
- **Ticket promedio nuevo**: ya existe el consolidado; solo se añade el
  operativo con definición distinta y explícita.
