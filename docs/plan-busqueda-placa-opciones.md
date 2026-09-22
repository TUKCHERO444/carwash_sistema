# Plan: botones de opción "Buscar cliente / Consultar API" en la placa de lavados y cambio de aceite

- **Estado:** 🟡 PROPUESTA — pendiente de aprobación del usuario (no implementar hasta recibir la orden)
- **Fecha:** 2026-09-22
- **Módulos:** `lavados`, `cambio-aceite` (6 formularios), `buscador-placa.js`, `clientes`, `automotores`
- **Contexto previo:** `docs/alcance-flujo-mixto-clientes-vehiculos.md` (el flujo mixto por upsert ya está implementado)

## 1. Objetivo

Ahorrar registro/escritura del operador: al escribir la **placa** en los tickets de lavado y cambio de aceite aparezcan **botones de opción**, con el primero — **"Buscar cliente"** — siempre desbloqueado:

1. **Buscar cliente** (consulta local a BD): si la placa ya tiene un `Automotor` registrado, **jala al formulario los datos del vehículo verificado y del cliente asociado** (autofill completo). Si no existe, se indica y se continúa el **flujo normal de registro actual**.
2. Solo en el caso "no encontrado" se pasaría al flujo normal, donde **recién ahí** se consume la API de consulta de placa y procede el registro actual (upsert al guardar + enriquecimiento en confirmación).

Todo **sin dañar los procesos ya funcionales**: el backend de `store/update/procesarConfirmacion/actualizarTicket` y `ClienteAutomotorService` **no se tocan**.

## 2. Contexto actual (verificado en código)

### 2.1 Frontend

- `resources/js/buscador-placa.js` → `initBuscadorPlaca()`: con `#placa` ≥ 3 caracteres (debounce 500 ms) hace `fetch('/clientes/buscar-por-placa')` **automáticamente**, autocompleta `#nombre`/`#telefono` solo si están vacíos y muestra la tarjeta "Cliente Frecuente" en `#cliente-summary-container` **solo si tiene servicios** (> 0).
- Se inicializa desde los 6 módulos: `resources/js/lavados/{create,edit,confirmar}.js` y `resources/js/cambio-aceite/{create,edit,confirmar}.js` → **punto único de integración**.
- Vistas con `#placa` + `#cliente-summary-container`: `lavados/{create,edit,confirmar}.blade.php`, `cambio-aceite/{create,edit,confirmar}.blade.php`.

### 2.2 Backend

| Ruta (name) | Permiso | Qué hace | La usan hoy los tickets |
|---|---|---|---|
| `clientes.buscar-por-placa` (`ClienteController::buscarPorPlaca`, :138-175) | `acceso-ventas` | Busca `Automotor` local por placa → devuelve cliente + automotor + conteos; `success:false` si no existe | ✅ sí (`buscador-placa.js:32`) |
| `automotores.consultarPlaca` (`AutomotorController::consultarPlaca`, :151-183) | `acceso-automotores` | Local → si no existe, `AutomotorApiService` (api.json.pe); resiliente (si la API falla, devuelve solo la placa) | ❌ no (y **podría dar 403** a operadores sin `acceso-automotores`) |

### 2.3 Huecos detectados

1. La respuesta de `buscar-por-placa` **no incluye `dni`** → aunque el cliente esté registrado, el DNI no se puede jalar al formulario (y sin DNI, `obtenerCliente` no puede vincular por DNI al guardar).
2. El autofill actual es **parcial** (solo nombre/teléfono, solo si vacíos, solo si tiene servicios previos se muestra algo) → no hay indicador de "placa verificada".
3. No hay botones: la búsqueda es 100 % implícita (auto-debounce).
4. Permiso: la consulta a la API (`automotores.consultar-placa`) no es alcanzable con `acceso-ventas`.

## 3. Alcance propuesto

### 3.1 UX: máquina de estados de los botones

Junto al input `#placa` (o bajo `#cliente-summary-container`), un grupo `#placa-opciones` **oculto** hasta que la placa tenga formato válido (6–7 alfanuméricos, normalizada):

| Botón | Desbloqueo | Acción | Resultado |
|---|---|---|---|
| **1. Buscar cliente** | **Siempre** (placa válida) | `GET clientes/buscar-por-placa` (local, sin API) | **Encontrado** → autofill completo + badge "Vehículo verificado" + resumen (vehículo + cliente + conteos). **No encontrado** → badge "No registrado — complete los datos" y **se desbloquea el Botón 2** |
| **2. Consultar datos (API)** | Solo tras "no encontrado" en Botón 1 | Nueva ruta bajo `acceso-ventas` → lógica de `AutomotorController::consultarPlaca` (local→API) | Muestra marca/modelo/color/motor en el resumen para orientar la elección en el `select` de tipo de vehículo. **Display-only: no crea ni persiste nada.** API caída → mensaje "sin datos externos", nunca bloquea |
| **3. Continuar registro** | Tras Botón 1 (encontrado) o Botón 2 | Cierra el grupo / deja el formulario listo | El alta la hace el **upsert actual al guardar** (sin cambios) |

- **Botón 1 "Encontrado"** rellena: `#placa` (normalizada), `#nombre`, `#dni` (**nuevo**), `#telefono`, y pintar en el resumen: placa, nombre completo, DNI, teléfono, lavados, cambios de aceite, marca, modelo, color, motor.
- El resumen ya **no exige `totalServicios > 0`**: una placa registrada con 0 servicios también se muestra como verificada.
- El autofill **no pisa** campos con contenido del operador (misma regla actual: solo llena vacíos) — decisión de UX a confirmar (ver §6).

### 3.2 Cambios backend (aditivos, de bajo riesgo)

1. **`ClienteController::buscarPorPlaca`**: añadir `'dni' => $cliente->dni` al array `cliente` (línea ~163). Aditivo; los consumidores actuales no se rompen (solo ignoran el campo). Test existente `ClienteConsultaDniTest`/`StoreTest` no lo validan exhaustivamente → añadir assetción.
2. **Nueva ruta de API para tickets** (réplica accesible con `acceso-ventas`), dentro del grupo `permission:acceso-ventas` y **antes** de los resources (convención AGENTS.md):
   ```
   Route::get('consulta-placa-api', [AutomotorController::class, 'consultarPlaca'])
       ->name('tickets.consultarPlacaApi');
   ```
   Reutiliza el método existente tal cual (sin duplicar lógica). `AutomotorApiService` se sigue mockeando en tests (nunca redes reales).
3. **Sin cambios** en `ClienteAutomotorService`, `LavadoController`, `CambioAceiteController`, `store/update/procesarConfirmacion/actualizarTicket`, esquema ni migraciones.

### 3.3 Cambios frontend

1. **Refactor de `resources/js/buscador-placa.js`** (mismo export `initBuscadorPlaca()` → los 6 módulos JS **no cambian**):
   - Extraer lógica pura testeable a funciones exportadas, p. ej. `placaValida(placa)`, `estadoBotones(placa, resultadoBusqueda)` → `{buscar: 'habilitado', api: 'bloqueado'|'habilitado', ...}`, `construirResumen(data)` (en vez de template inline sin testear).
   - El trigger pasa de auto-debounce a **clic en Botón 1** (opción A, recomendada) manteniendo el listener de input para mostrar/ocultar y habilitar el grupo. La **opción B** conserva el auto-debounce además de los botones (ver §6).
   - `fetch` a la nueva ruta `tickets.consultarPlacaApi` desde el Botón 2 (con `X-Requested-With` + `Accept: application/json`).
2. **6 vistas** (`lavados/{create,edit,confirmar}`, `cambio-aceite/{create,edit,confirmar}`): añadir el bloque `#placa-opciones` con los 2–3 botones + `#placa-estado` (badges "verificado"/"no registrado"). Estilo con clases semánticas del dark mode (`.input-main`, `.border-main`, badges como en `servicios/index`).
3. `vite.config.js`: **sin cambios** (no hay nuevas entradas; se modifica el helper importado).

## 4. Qué NO cambia (protección de procesos funcionales)

- `ClienteAutomotorService::obtenerCliente/obtenerAutomotor` — intacto.
- Alta/actualización de tickets (`store`, `update`, `procesarConfirmacion`, `actualizarTicket`) — intactos; el upsert sigue siendo el mecanismo de alta.
- Enriquecimiento con API en confirmación (`obtenerAutomotor(..., true)`) — intacto.
- Paneles dedicados `clientes` / `automotores` / `vehiculos` — intactos.
- Permisos existentes — no se crean ni se reasignan; solo se añade una ruta bajo el grupo `acceso-ventas` ya existente.
- Rutas estáticas antes de resources — se respeta.

## 5. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Romper el autofill actual de 6 formularios | Un solo archivo (`buscador-placa.js`) + mantener la firma `initBuscadorPlaca()`; suite PHPUnit completa + property tests JS |
| 403 en Botón 2 para operadores sin `acceso-automotores` | Nueva ruta `tickets.consultarPlacaApi` bajo `acceso-ventas` (el permiso que ya protege estos formularios) |
| API externa caída | Se reutiliza `AutomotorApiService` (resiliente: `[]`, log, nunca bloquea) — igual que hoy |
| Duplicar cliente si el operador edita DNI tras verificar | Ya lo cubre el upsert actual (busca por DNI → update); no se toca |
| Autofill pisa datos escritos por el operador | Regla actual "solo llena vacíos" se conserva (opción A/B en §6) |

## 6. Decisiones a aprobar

- **A (recomendada) — Botón como único disparador:** se elimina el auto-debounce; el operador escribe placa → aparece el grupo → clic "Buscar cliente". Menos peticiones, control explícito, cero sorpresas.
- **B — Auto-debounce + botones:** se conserva la búsqueda automática además de los botones (redundante: hasta 2 fetch por placa).
- **Autofill:** mantener "solo llena campos vacíos" (recomendado) vs "sobreescribe siempre con los datos verificados".
- Mostrar DNI en la tarjeta de resumen: sí (recomendado) / no.

## 7. Tareas (tras aprobación)

1. [ ] Backend: añadir `dni` a la respuesta de `ClienteController::buscarPorPlaca` + asertación en test existente.
2. [ ] Rutas: `tickets.consultarPlacaApi` (`GET consulta-placa-api`) bajo `permission:acceso-ventas`, antes de los resources.
3. [ ] Tests Feature: `tests/Feature/TicketsConsultaPlacaApiTest.php` — auth/permiso `acceso-ventas`, mock de `AutomotorApiService`, local vs API vs API-caída.
4. [ ] JS: refactor `buscador-placa.js` con funciones puras (`placaValida`, `estadoBotones`, `construirResumen`) + autofill de `#dni` + badge de estado + botón 2.
5. [ ] JS: property tests `tests/js/buscar-placa.property.test.js` (`// Feature: flujo-mixto-placa, Property N: ...`).
6. [ ] Vistas: bloque `#placa-opciones` en los 6 formularios (lavados y cambio de aceite).
7. [ ] Verificación: `npm run test`, `npm run build`, `composer run test` (349+ deben seguir verdes), `vendor/bin/pint`.

## 8. Criterios de aceptación

1. Placa registrada → clic "Buscar cliente" rellena placa, nombre, DNI, teléfono y muestra el resumen completo (vehículo + cliente + conteos) en los 6 formularios.
2. Placa no registrada → badge "No registrado", Botón 2 habilitado, y al guardar funciona exactamente igual que hoy (upsert crea cliente + automotor).
3. API caída/mocking `[]` → nunca bloquea el formulario ni el guardado.
4. Sin `acceso-ventas` → 403/redirect igual que el resto de rutas del grupo; sin sesión → login.
5. Todas las suites existentes siguen en verde (ningún proceso funcional dañado).
