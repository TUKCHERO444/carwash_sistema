# Alcance: flujo mixto de registro de clientes y vehículos desde lavados y cambio de aceite

- **Estado:** ✅ IMPLEMENTADO (implícito en backend, sin UI de alta explícita)
- **Fecha:** 2026-09-22
- **Módulos:** `lavados`, `cambio-aceite`, `clientes`, `automotores`
- **Specs de referencia:** `.kiro/specs/ingresos-module`, `.kiro/specs/ingresos-confirmacion`, `.kiro/specs/cambio-aceite`, `.kiro/specs/cambio-aceite-confirmacion`, `.kiro/specs/clientes-crud`, `docs/feature-automotores.md`

## 1. Objetivo

Permitir registrar clientes y vehículos (automotores) **sin salir de los paneles de lavado y cambio de aceite**, es decir, un flujo mixto donde el operador da de alta o actualiza un cliente/vehículo al crear el ticket, en lugar de obligarlo a ir al panel dedicado de `clientes` o `automotores`.

## 2. Veredicto

**Ya está hecho.** Ambos paneles de tickets capturan cliente y vehículo como texto libre y el backend hace un **upsert automático** al guardar, mediante el servicio compartido `app/Services/ClienteAutomotorService.php`. No hace falta implementar nada nuevo para que el registro funcione; solo se documenta aquí su alcance y sus límites.

## 3. Cómo funciona hoy

### 3.1 Paneles dedicados (alta formal)

| Panel | Ruta / permiso | Alta de qué | Búsqueda externa |
|---|---|---|---|
| `clientes` | `resource clientes` (`permission:acceso-clientes`) | Cliente completo (DNI, nombre, apellidos, teléfono) | `clientes.consultarDni` → local → `DniApiService` |
| `automotores` | `resource automotores` (`permission:acceso-automotores`) | Vehículo físico: placa (PK) + `cliente_id` + marca/modelo/color/... | `automotores.consultarPlaca` → local → `AutomotorApiService` |
| `vehiculos` | `resource vehiculos` (`permission:acceso-vehiculos`) | **Catálogo de tipos** de vehículo con precio (sedán, camioneta…) — no es el vehículo del cliente | — |

### 3.2 Paneles de tickets (flujo mixto)

**Formularios** (`lavados/create|edit|confirmar` y `cambio-aceite/create|edit|confirmar`) — campos idénticos en ambos módulos:

1. `select#vehiculo_id` — tipo de vehículo del catálogo (obligatorio, `exists:vehiculos,id`).
2. `input#placa` — obligatoria, 6–7 alfanuméricos.
3. `input#nombre`, `input#dni` (8 d.), `input#telefono` (9 d.) — opcionales.
4. `div#cliente-summary-container` — tarjeta "Cliente Frecuente" que llena el buscador.

**No hay** `<select>` de clientes existentes, ni botón "nuevo cliente", ni modal de alta: la alta es implícita al guardar.

### 3.3 Mecanismo de alta implícita

- **Búsqueda en vivo por placa:** `resources/js/buscador-placa.js` (debounce 500 ms) llama a `GET /clientes/buscar-por-placa` (`ClienteController::buscarPorPlaca`, `permission:acceso-ventas`). Si el automotor existe, autocompleta `#nombre`/`#telefono` **solo si están vacíos** y muestra el resumen con `lavados_count` y `cambios_aceite_count`.
- **Upsert al guardar:** `LavadoController` y `CambioAceiteController` llaman dentro de `DB::transaction`:
  - `ClienteAutomotorService::obtenerCliente($request)` (`app/Services/ClienteAutomotorService.php:26-48`): si viene DNI, busca por DNI y **actualiza**; si no existe o no hay DNI, **crea** el cliente (incluso solo con nombre — `clientes.dni` es nullable en BD).
  - `ClienteAutomotorService::obtenerAutomotor($placa, $cliente->id)` (`:55-79`): normaliza la placa a mayúsculas, **upsert por placa** (PK string) y lo vincula al cliente.
- **Puntos donde aplica:** `LavadoController@store` (:232-233), `@update` (:324-325), `@procesarConfirmacion` (:138-139, con `$conApi = true` para enriquecer marca/modelo/vin desde la API) y, en cambio de aceite, `@store` (:124-125), `@update` (:257-258), `@procesarConfirmacion` (:530-531), `@actualizarTicket` (:650-651).

### 3.4 Flujo de punta a punta

1. Operador escribe la placa → a los 500 ms el buscador consulta y, si hay match, rellena nombre/teléfono y muestra la tarjeta de cliente frecuente.
2. Selecciona tipo de vehículo (catálogo) y servicios/productos; el precio se calcula server-side.
3. Al guardar (`pendiente`): `obtenerCliente` + `obtenerAutomotor` crean o actualizan cliente y automotor; el ticket guarda `cliente_id` y `automotor_id` (FK a `automotores.placa`).
4. Al confirmar (`confirmado`, requiere caja activa vía `CajaService`): mismo upsert con `$conApi = true`, que enriquece el automotor con datos de `AutomotorApiService` si respondió.
5. Si el cliente/vehículo ya existía en los paneles dedicados, simplemente se **actualiza** (no se duplica).

## 4. Cobertura (qué sí cubre el flujo mixto)

- Alta de **cliente** desde cualquiera de los 6 formularios de tickets (3 de lavado + 3 de cambio de aceite), con o sin DNI.
- Alta de **vehículo físico (automotor)** por placa, vinculado automáticamente al cliente del ticket.
- Reutilización de registros existentes: busca por DNI (cliente) y por placa (automotor) → update, no duplicados.
- Enriquecimiento opcional con APIs externas (`DniApiService` / `AutomotorApiService`), resilientes: si fallan, el formulario nunca se bloquea.
- Autocompletado y aviso de "Cliente Frecuente" sin salir del formulario.
- Los paneles dedicados siguen siendo la vía de alta/editación formal (con consultas DNI/placa) y de borrado controlado (bloqueado si hay lavados/automotores/cambioAceites).

## 5. Límites (qué NO cubre hoy)

1. **Sin UI explícita de alta:** no hay modal ni botón "Nuevo cliente/vehículo" que muestre lo que se va a crear; el operador no ve confirmación de "cliente nuevo" antes de guardar.
2. **Campos reducidos en tickets:** desde lavado/cambio-aceite solo se envían `nombre`, `dni`, `telefono`. Los `apellido_paterno`/`apellido_materno` (ya existentes en `clientes`) **no se capturan** en estos formularios; solo se conservan si el cliente venía de un alta formal.
3. **Sin badge de estado en vivo:** el buscador no informa "placa no registrada → se creará un cliente nuevo".
4. **Sin selector de clientes existentes por nombre/DNI:** la única entrada es placa o DNI escrito a mano; no hay listado buscable de clientes dentro del ticket.
5. **Catálogo de tipos de vehículo no es editable desde el ticket:** `vehiculo_id` exige un `Vehiculo` ya creado en el panel `vehiculos` (coherente: es administración, no operación).
6. **Roadmap pendiente relacionado** (`docs/feature-automotores.md`): consultas a la API de placa con `TODO(api)`, permisos V7 y limpieza de "clientes fantasma" siguen abiertos.

## 6. Verificación

- `tests/Feature/LavadoCloudinaryTest.php` — `store`/`update` con payload de placa/nombre sin `cliente_id` → ejercita el upsert implícito de Cliente y Automotor.
- `tests/Feature/CambioAceite/StoreTest.php`, `ConfirmarTest.php`, `ActualizarTicketTest.php` — ídem para cambio de aceite.
- `tests/Feature/AutomotorCrudTest.php` / `AutomotorConsultaPlacaTest.php` — alta y consulta en el panel dedicado.
- `tests/Feature/ClienteConsultaDniTest.php` — consulta DNI (local → API) del panel dedicado.

## 7. Conclusión

El flujo mixto **ya opera** en los seis formularios de tickets: el operador registra cliente y vehículo directamente desde lavados o cambio de aceite, y `ClienteAutomotorService` garantiza el upsert sin duplicados, con las APIs externas como enriquecimiento opcional y nunca bloqueante. Si se desea evolucionar hacia una **alta explícita con UI** (modal, badge "nuevo", captura de apellidos), eso sí requeriría un plan nuevo aprobado por el usuario.
