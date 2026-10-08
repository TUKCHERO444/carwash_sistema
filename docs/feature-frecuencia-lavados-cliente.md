# Frecuencia de lavados por cliente (modal en Clientes)

## Objetivo

Mostrar en el listado de Clientes un botón **“Lavados”** que abre un modal
con el resumen de lavados confirmados del cliente, como base de recurrencia
para una futura fidelización. Solo consulta; sin puntos, descuentos,
promociones ni CRM.

## Alcance

- Endpoint `GET /clientes/{cliente}/resumen-lavados`
  (`clientes.resumenLavados`, antes del resource, grupo `acceso-clientes`):
  reutiliza `Cliente->lavados()` + scope `confirmados()`. Solo 3 consultas
  agregadas (conteo, último, placas agrupadas); sin cargar el historial.
- Modal (`x-modal`, sistema global `modal.js`): nombre completo, DNI,
  teléfono, total confirmados, último lavado (fecha, placa, vehículo) y
  vehículos con su conteo. Se abre sin recargar ni perder la paginación.
- JS `resources/js/clientes/index.js` (en `vite.config.js`, `@vite` en el
  índice; `renderVehiculosHTML` pura exportada para tests).

## Archivos clave

- `ClienteController@resumenLavados`; `resources/views/clientes/index.blade.php`
  (botón + modal); `resources/js/clientes/index.js`.
- Tests: `tests/Feature/ClienteResumenLavadosTest.php` (solo confirmados
  del cliente, ceros sin lavados, botón/modal, acceso) +
  `tests/js/clientes/index.property.test.js` (2 props × 100 runs).

## Reglas

- Universo confirmado (misma regla de reportes/estadísticas). Sin
  contadores manuales ni nuevas entidades.

## Verificación

- Tests en verde + `npm run test`. Requiere manifest Vite construido
  (`npm run build`) para las vistas con `@vite`.
