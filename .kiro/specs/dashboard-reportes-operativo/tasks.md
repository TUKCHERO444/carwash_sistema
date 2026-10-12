# Tareas: métricas operativas

- [x] 1. Servicio `ReporteOperativoService` (kpis, serie, trabajador,
  servicio, tipo vehículo, placas, hora, tiempos, filasSerie)
  - _Requirements: 2, 4, 5, 7, 8, 9, 10_
- [x] 2. Dashboard: `resumenOperativo()` + 3 tarjetas del período
  - _Requirements: 2, 5_
- [x] 3. Ruta `reportes.operativo` + `ReporteController::operativo`
  (HTML/CSV/PDF) + tarjeta en índice
  - _Requirements: 10_
- [x] 4. Vistas HTML + PDF + nota metodológica (incluye espera no disponible)
  - _Requirements: 3, 6, 10_
- [x] 5. JS `operativo.js` + entrada Vite
  - _Requirements: 5, 9_
- [x] 6. Tests: `ReporteOperativoTest` (fórmulas, filtros, estados,
  sin datos, serie == kpis, CSV/PDF) + listas de acceso/PDF +
  `operativo.property.test.js` (Properties 10–11)
  - _Requirements: 1–10_
- [ ] 7. Verificación: `vendor/bin/pint`, `composer run test`,
  `npm run test`, `npm run build`
