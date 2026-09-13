# Plan de Implementación: módulo de reportes

## Descripción general

Implementar el módulo de reportes de forma incremental: primero esquema y permisos (migración `pago_diario` + seeder/factory + modelo + formas de trabajador + `PermissionSeeder`), luego backend de reportes (helper de rango + servicios de agregación + `ReporteController` + rutas), después frontend (vista índice, vistas por reporte, filtros reutilizables, sidebar/vite/JS de Chart.js e impresión), y finalmente las pruebas de propiedad que garantizan los invariantes de suma, partición y acceso.

Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP. Cada tarea referencia los requisitos para trazabilidad. Los property tests se etiquetan `// Feature: reportes, Property N: <descripción>` y **Valida:** en docblock, con mínimo 100 iteraciones.

## Requisitos cubiertos (mapa rápido)

- R1: acceso desde Gestión Administrativa · R2: permiso `acceso-reportes` · R3: índice · R4: filtros de rango · R5–R14: los 9 reportes · R15: `pago_diario` · R16: CSV · R17: impresión · R18: fechas/zona horaria.

## Tareas

- [ ] 1. Esquema y permisos base
  - [ ] 1.1 Crear `database/migrations/2026_09_12_000002_add_pago_diario_to_trabajadores_table.php`
    - `$table->decimal('pago_diario', 8, 2)->default(50)->after('foto');`
    - _Requirements: 15.1_

  - [ ] 1.2 Actualizar `app/Models/Trabajador.php`
    - Añadir `'pago_diario'` a `$fillable` y `'pago_diario' => 'decimal:2'` a `$casts`.
    - _Requirements: 15.4_

  - [ ] 1.3 Actualizar `database/seeders/TrabajadorSeeder.php`
    - Incluir `'pago_diario' => 50` en el `Trabajador::create([...])` de cada trabajador.
    - _Requirements: 15.2_

  - [ ] 1.4 Actualizar `database/factories/TrabajadorFactory.php`
    - Añadir `'pago_diario' => 50` al `definition()`.
    - _Requirements: 15.3_

  - [ ] 1.5 Actualizar `app/Http/Controllers/TrabajadorController.php` y las vistas `resources/views/trabajadores/{create,edit}.blade.php`
    - Regla `pago_diario` → `nullable|numeric|min:0`.
    - Input numérico decimal (paso 0.50) con `value="{{ old('pago_diario', $trabajador->pago_diario ?? null) }}"` y ayuda "Déjelo vacío si no aplica".
    - _Requirements: 15.5, 15.6_

  - [ ] 1.6 Añadir `'acceso-reportes'` en `database/seeders/PermissionSeeder.php` (bloque `// Reportes`).
    - El rol Administrador lo recibe vía `AuthSeeder::syncPermissions(Permission::all())`. No tocar `AuthSeeder`.
    - _Requirements: 2.1, 2.2, 2.3_

  - [ ] 1.7 Checkpoint esquema
    - `php artisan migrate`; `vendor/bin/pint` sobre los archivos modificados.
    - _Requirements: 15_

- [ ] 2. Helper de rango y servicios de agregación
  - [ ] 2.1 Crear `app/Services/Reportes/DateRangeFiltro.php`
    - `aplicar(?string $desde, ?string $hasta): array{desde:CarbonImmutable, hasta:CarbonImmutable, etiqueta:string}` con default últimos 30 días (hoy incluido) usando `now()`.
    - `diasEntre(CarbonImmutable, CarbonImmutable): string[]` (serie 'Y-m-d' sin huecos).
    - `mes(?string $mes): array{desde:CarbonImmutable, hasta:CarbonImmutable}` para el reporte de personal.
    - _Requirements: 4, 18_

  - [ ] 2.2 Crear `app/Services/Reportes/ReporteIngresosService.php`
    - `consolidado($desde, $hasta)`: ventas + lavados confirmados (por `fecha`) + cambios confirmados (por `created_at`), operaciones y ticket promedio; método de pago consolidando `mixto`.
    - `serieDiaria($desde, $hasta)` (reutilizar lógica de `DashboardService::ingresosPorDia`).
    - `diasTop($desde, $hasta, $limite=10)` con `actividad_dominante` (≥50% del día).
    - `metodoPago($desde, $hasta)`.
    - _Requirements: 5, 6_

  - [ ] 2.3 Crear `app/Services/Reportes/ReporteVentasService.php`
    - `kpis`, `porUsuario`, `porMetodo`, `detalle` (paginate 10), `detalleColeccion` (CSV). Filtros: `user_id`, `metodo_pago`, `correlativo` (contiene).
    - _Requirements: 7_

  - [ ] 2.4 Crear `app/Services/Reportes/ReporteLavadosService.php`
    - `kpis`, `porVehiculo`, `porServicio`, `porTrabajador`, `detalle`, `detalleColeccion`. Universo confirmados; rango por `fecha`; trabajador vía `lavado_trabajadores`.
    - _Requirements: 8_

  - [ ] 2.5 Crear `app/Services/Reportes/ReporteCambioAceiteService.php`
    - `kpis`, `porProducto`, `porTrabajador`, `detalle`, `detalleColeccion`. Universo confirmados; rango por `created_at`; producto vía `cambio_productos`.
    - _Requirements: 9_

  - [ ] 2.6 Crear `app/Services/Reportes/ReporteInventarioService.php`
    - `topPorCantidad` / `topPorIngreso` (combina `detalle_ventas` + `cambio_productos` con rango), `stockActual` (stock, inventario, % consumido, `esta_en_alerta`, valorizado), `resumenCategorias`, `resumenMarcas`.
    - _Requirements: 10_

  - [ ] 2.7 Crear `app/Services/Reportes/ReporteClientesService.php`
    - `topClientes` (gasto = lavados+cambios confirmados, visitas, automotores, visitas por mes), `topAutomotores`, `detalle` paginado.
    - _Requirements: 11_

  - [ ] 2.8 Crear `app/Services/Reportes/ReporteCajaService.php`
    - `kpis`, `detalle` (Caja cerrada por `fecha_cierre` + `CajaService::calcularResumen`), `egresos`, `egresosPorDescripcion`.
    - _Requirements: 12_

  - [ ] 2.9 Crear `app/Services/Reportes/ReportePersonalService.php`
    - `resumen(string $mes, ?int $trabajadorId)`: asistencias, % sobre días con marca, hora promedio, `total_pago = asistencias × (pago_diario ?? 0)`, `total_pago_general`, flag `sin_jornal`.
    - _Requirements: 13_

  - [ ] 2.10 Crear `app/Services/Reportes/ReporteKardexService.php`
    - `agregado` (entradas/salidas/saldo_neto por producto) y `detalle` paginado; filtros `producto_id`, `tipo`.
    - _Requirements: 14_

  - [ ] 2.11 Crear `app/Services/Reportes/ReporteCsvService.php`
    - `descargar(string $nombreArchivo, array $cabeceras, array|Collection $filas): StreamedResponse`; BOM UTF-8 + `;` + comillas; `Content-Disposition: attachment`.
    - _Requirements: 16_

  - [ ] 2.12 Checkpoint backend de servicios
    - Escribir un `tinker`/test rápido que llame a `consolidado` y `diasTop` con datos del seeder y verifique sumas.
    - _Requirements: 5, 6, 10, 13_

- [ ] 3. Controlador y rutas
  - [ ] 3.1 Crear `app/Http/Controllers/ReporteController.php`
    - Métodos: `index`, `ingresos`, `ventas`, `lavados`, `cambioAceite`, `inventario`, `clientes`, `caja`, `personal`, `kardex`.
    - Validación inline (reglas de rango + selects con `exists:`); si `export=csv`, devolver `ReporteCsvService::descargar(...)` con 422 JSON en validación fallida.
    - _Requirements: 2.4, 4, 16_

  - [ ] 3.2 Rutas en `routes/web.php`
    - Grupo `middleware(['auth','permission:acceso-reportes'])` → `prefix('reportes')` → `name('reportes.')`.
    - Todas estáticas, sin parámetros. `use App\Http\Controllers\ReporteController;`.
    - _Requirements: 2.4, 2.7_

- [ ] 4. Menú y navegación
  - [ ] 4.1 En `resources/views/layouts/app.blade.php`:
    - `$gestionAdministrativaActive` → añadir `'reportes.*'`.
    - Dropdown "Gestión Administrativa": `@canany([..., 'acceso-reportes'])`.
    - Dentro del menú (escritorio y móvil): separador + encabezado "Reportes" + enlace a `reportes.index` (y los 9 reportes), resaltado con `request()->routeIs('reportes.*')`, protegido con `@can('acceso-reportes')`.
    - Clases `print:hidden` en `aside` desktop y `nav` móvil para la impresión (Requisito 17).
    - _Requirements: 1, 17.2_

- [ ] 5. Vistas del módulo
  - [ ] 5.1 Crear `resources/views/reportes/index.blade.php`
    - Tarjetas-responsive con título/descripción por reporte, enlaces a cada ruta.
    - _Requirements: 3_

  - [ ] 5.2 Crear `resources/views/reportes/partials/filtros.blade.php`
    - Barra con `desde`/`hasta` (`type="date"`), slot/`@stack` para selects contextuales, botones Filtrar y Limpiar (GET).
    - _Requirements: 4_

  - [ ] 5.3 Crear `resources/views/reportes/partials/acciones.blade.php`
    - Botón "Imprimir" (`data-imprimir-reporte`) y enlace "Exportar CSV" (`?export=csv`), con `print:hidden`.
    - _Requirements: 16, 17_

  - [ ] 5.4 Crear `resources/views/reportes/ingresos.blade.php`
    - Header con rango + acciones; KPIs; `#chart-reporte-ingresos` + `#chart-reporte-metodo-pago`; tabla "Días de mayor ingreso" con badge de actividad dominante.
    - `window.reportesIngresos` / `window.reportesMetodoPago` ANTES de `@vite('resources/js/reportes/ingresos.js')`.
    - _Requirements: 5, 6, 17_

  - [ ] 5.5 Crear las 8 vistas restantes
    - `ventas`, `lavados`, `cambio-aceite`, `inventario`, `clientes`, `caja`, `personal` (`mes` + selects de trabajador + tablas de asistencias y resumen de pago con total general), `kardex`.
    - Estructura común: header + acciones + filtros + KPIs + tablas de agregados + detalle `paginate(10)` con `->links()` + estados vacíos.
    - _Requirements: 7–14, 17_

  - [ ] 5.6 Crear `resources/js/reportes/ingresos.js`
    - Chart.js barras apiladas + doughnut método de pago (mismo patrón que `dashboard.js`); exporta funciones puras `formatoSoles`, `etiquetaDia`, `actividadDominante`.
    - _Requirements: 5_

  - [ ] 5.7 Crear `resources/js/reportes/print.js`
    - `window.print()` al hacer clic en `[data-imprimir-reporte]`.
    - _Requirements: 17_

  - [ ] 5.8 Actualizar `vite.config.js`
    - Añadir `'resources/js/reportes/ingresos.js'` y `'resources/js/reportes/print.js'` al `input` (bloque `// Reportes`).
    - _Requirements: convención Vite_

  - [ ] 5.9 Validación `composer run dev` / build
    - `npm run build` compila; cada vista carga sus `@vite` sin 404.
    - _Requirements: 3–17_

- [ ] 6. Tests Feature (PHPUnit)
  - [ ] 6.1 `tests/Feature/Reportes/ReporteAccesoTest.php`
    - Redirect `/login` sin sesión; 403 sin permiso; 200 con permiso (index e ingresos).
    - _Requirements: 2_

  - [ ] 6.2 `tests/Feature/Reportes/ReporteIngresosTest.php`
    - KPIs con datos conocidos; exclusión de lavados/cambios pendientes; método mixto; días top ordenados; CSV.
    - _Requirements: 5, 6_

  - [ ] 6.3 `tests/Feature/Reportes/ReporteVentasTest.php`
    - Filtros usuario/método/correlativo; agregados; detalle paginado; CSV sin paginar.
    - _Requirements: 7_

  - [ ] 6.4 `tests/Feature/Reportes/ReporteLavadosTest.php`
    - Universo confirmado; agregados por vehículo/servicio/trabajador; CSV.
    - _Requirements: 8_

  - [ ] 6.5 `tests/Feature/Reportes/ReporteCambioAceiteTest.php`
    - Universo confirmado; agregados por producto/trabajador; CSV.
    - _Requirements: 9_

  - [ ] 6.6 `tests/Feature/Reportes/ReporteInventarioTest.php`
    - Top por cantidad/ingreso con rango; stock con alerta; resúmenes por categoría/marca; CSV.
    - _Requirements: 10_

  - [ ] 6.7 `tests/Feature/Reportes/ReporteClientesTest.php`
    - Gasto = lavados+cambios; orden desc; top automotores; CSV.
    - _Requirements: 11_

  - [ ] 6.8 `tests/Feature/Reportes/ReporteCajaTest.php`
    - Solo cerradas, por rango de `fecha_cierre`; balance cuadra con `CajaService`; egresos por descripción; CSV.
    - _Requirements: 12_

  - [ ] 6.9 `tests/Feature/Reportes/ReportePersonalTest.php`
    - Asistencias y %; pago con y sin jornal; acotación por trabajador; CSV.
    - _Requirements: 13, 15_

  - [ ] 6.10 `tests/Feature/Reportes/ReporteKardexTest.php`
    - Agregados entradas/salidas; saldo neto; filtro tipo; CSV.
    - _Requirements: 14_

  - [ ] 6.11 `tests/Feature/Reportes/TrabajadorPagoDiarioTest.php`
    - Crear/editar con `pago_diario`; validación `min:0`; default del factory/seeder.
    - _Requirements: 15_

- [ ] 7. Property tests PHP — `tests/Feature/Reportes/` (100 iteraciones)
  - [ ]* 7.1 Property 1: normalización de rango estable y acotada
    - Pares aleatorios válidos e inválidos; sin parámetros → 1–30 días terminando hoy; idempotente.
    - **Valida: Requisitos 4**

  - [ ]* 7.2 Property 2: la serie diaria suma exactamente el consolidado
    - Operaciones aleatorias en las 3 fuentes (Faker); `sum(serie.totals) == consolidado.total` (±0.01) y consistencia por día.
    - **Valida: Requisitos 5, 6**

  - [ ]* 7.3 Property 3: gasto por cliente == suma de sus operaciones
    - Clientes con lavados/cambios aleatorios; verificar `gasto`, orden desc y `visitas`.
    - **Valida: Requisitos 11**

  - [ ]* 7.4 Property 4: resumen de pago == asistencias × jornal
    - Asistencias y jornales aleatorios; suma general.
    - **Valida: Requisitos 13**

  - [ ]* 7.5 Property 5: asistencias acotadas por el mes y porcentaje en [0,100]
    - Fechas relativas a `now()` (nunca literales).
    - **Valida: Requisitos 13, 18**

  - [ ]* 7.6 Property 6: rutas requieren autenticación (sin sesión → redirect `/login`)
    - Para cada ruta del módulo con parámetros válidos.
    - **Valida: Requisitos 2.5**

  - [ ]* 7.7 Property 7: rutas requieren `acceso-reportes` (403) incluyendo el rol Vendedor
    - Para cada ruta del módulo.
    - **Valida: Requisitos 2.3, 2.6**

- [ ] 8. Property tests JS — `tests/js/reportes/ingresos.property.test.js` (fast-check, 100 runs)
  - [ ]* 8.1 Property 8: `actividadDominante` consistente (fuente ≥ 50% ⇔ dominante) y formato de moneda/labels en valores límite
    - `fc.record({ventas: fc.nat(), lavados: fc.nat(), cambios: fc.nat()})`.
    - **Valida: Requisitos 6.3**

- [ ] 9. Formateo y chequeos de calidad
  - `vendor/bin/pint` sobre los archivos nuevos/modificados.
  - `composer run test` (suite completa, sin tocar redes).
  - `npm run test` (tests JS).
  - `npm run build` (Vite compila).
  - _Requirements: 1–18_

## Checkpoints

1. **Esquema listo** (tareas 1): `pago_diario` migrado y `acceso-reportes` creado; se puede asignar jornal a trabajadores.
2. **Backend listo** (tareas 2–3): los servicios devuelven agregados correctos y las rutas responden con permisos; CSV descargable por terminal.
3. **Frontend operativo** (tareas 4–5): navegación desde Gestión Administrativa, filtros funcionales, gráficos e impresión.
4. **Cierre** (tareas 6–9): suite verde y propiedades de agregación/acceso demostradas.

## Notas

- Las tareas con `*` son opcionales (property tests). El MVP mínimo cubre tareas 1–6 y 9.
- Convención obligatoria: rutas estáticas (sin parámetros) dentro del grupo `reportes.*`; no hay conflictos de binding.
- El módulo es de solo lectura: **no** tocar `AuditServiceProvider`, `AuditService` ni `CajaService` (solo se reutiliza `calcularResumen`).
- Ningún test debe tocar redes (Cloudinary/DNI): el módulo no usa APIs externas.
- Fechas siempre con `now()`/Carbon (`America/Lima`) y `->toDateString()` en comparaciones; verificar con tiempos relativos, no literales.
- Si se decide dar lectura de reportes al rol Vendedor más adelante, basta con agregar `acceso-reportes` a su `syncPermissions`; el diseño ya lo aísla en un solo permiso.