# Feature: Integración API Consulta de Placa (api.json.pe)

## Índice

1. [Resumen ejecutivo](#resumen-ejecutivo)
2. [Estado actual del sistema](#estado-actual-del-sistema)
3. [Requisitos técnicos de la nueva feature](#requisitos-técnicos-de-la-nueva-feature)
4. [Diseño técnico](#diseño-técnico)
5. [Plan de implementación en 4 unidades de trabajo](#plan-de-implementación-en-4-unidades-de-trabajo)
6. [Integración con la API REST](#integración-con-la-api-rest)
7. [Resultados esperados](#resultados-esperados)
8. [Criterios de aceptación](#criterios-de-aceptación)
9. [Verificación](#verificación)

---

## Resumen ejecutivo

La feature **Automotores** quedó implementada con un servicio de integración `AutomotorApiService` **stubeado** (interfaz + fallback), a la espera de conectar una API real que devuelva los datos del vehículo por placa.

Ahora se tiene la documentación del proveedor **api.json.pe**:

- `POST https://api.json.pe/api/placa`
- Header de autenticación: `Authorization: Bearer <token>`
- Body JSON: `{ "placa": "F3H792" }`
- Respuesta 200 (JSON): `{ success: boolean, message: string, data: { placa, marca, modelo, serie, color, motor, vin } }`

Esta feature **conecta dicha API** para:

1. **Enriquecer automáticamente** el registro del vehículo al **confirmar** un ticket de **ingreso** o **cambio de aceite** (flujo ya cableado vía `ClienteAutomotorService::obtenerAutomotor($placa, $clienteId, true)`), agilizando el alta de los vehículos atendidos en el local.
2. **Autocompletar el formulario CRUD de automotores** (crear/editar) con un botón "Consultar placa", evitando el tipeo manual de marca, modelo, serie, color y motor.
3. Ampliar el modelo de datos con la columna **`vin`** (la respuesta distingue `serie` y `vin`, hoy `automotores` solo tiene `serie`).
4. Agregar cobertura de **tests unitarios/feature** para la integración y su fallback (RNF-02).

Mantiene el comportamiento resiliente definido en la feature automotores: si la API no responde, no encuentra el vehículo o no está configurada, el flujo **no se bloquea** y el automotor se registra al menos con la placa.

---

## Estado actual del sistema

### Contrato del servicio stubeado

`app/Services/AutomotorApiService.php` define:

- Método `buscarPorPlaca(string $placa): array` que devuelve `['marca','modelo','serie','color','motor']` o `[]`.
- Lee el endpoint desde `config('services.vehicle_api.url') ?? env('VEHICLE_API_URL')`.
- Si no hay endpoint configurado, loguea y devuelve `[]`.
- El bloque de llamada HTTP está como comentario `TODO(api)` (líneas 41-55).

`config/services.php` **no contiene** la entrada `vehicle_api` → hoy siempre se devuelve `[]` (fallback silencioso).

### Punto de consumo (confirmación de tickets)

`app/Services/ClienteAutomotorService::obtenerAutomotor()`:

```php
if ($conApi) {
    $apiDatos = $this->automotorApiService->buscarPorPlaca($placa);
    foreach (['marca', 'modelo', 'serie', 'color', 'motor'] as $campo) {
        if (! empty($apiDatos[$campo])) {
            $datos[$campo] = $apiDatos[$campo];
        }
    }
}
```

Se invoca con `$conApi = true` en:

- `IngresoController::procesarConfirmacion()` (línea 112).
- `CambioAceiteController::procesarConfirmacion()` (línea 472).

Es decir, la confirmación ya está preparada para enriquecer el automotor; solo falta que `buscarPorPlaca()` ejecute la llamada real.

### CRUD de automotores

- `app/Http/Controllers/AutomotorController.php`: CRUD completo (`index/create/store/edit/update/destroy`).
- Vistas: `resources/views/automotores/create.blade.php` y `edit.blade.php` (validación JS en `resources/js/automotores/validate.js`).
- Campos del formulario: `placa, cliente_id, marca, modelo, serie, color, motor`.
- Rutas: `Route::resource('automotores', ...)` bajo `permission:acceso-automotores` en `routes/web.php`.

### Modelo de datos vigente

```
automotores
  placa        string(7)  PK / UNIQUE
  cliente_id   FK → clientes (cascade)
  marca        string(100) nullable
  modelo       string(100) nullable
  serie        string(100) nullable   ← "Serie/N° de chasis o vin"
  color        string(50)  nullable
  motor        string(100) nullable
  timestamps
```

### Archivos clave del estado actual

| Archivo | Rol |
|---|---|
| `app/Services/AutomotorApiService.php` | Servicio stubeado de integración con la API vehicular |
| `app/Services/ClienteAutomotorService.php` | Upsert de cliente/automotor; orquesta la consulta en confirmación |
| `app/Http/Controllers/IngresoController.php` / `CambioAceiteController.php` | Confirmación de tickets; invocan `obtenerAutomotor(..., true)` |
| `app/Http/Controllers/AutomotorController.php` | CRUD de automotores |
| `config/services.php` | Config de servicios terceros (falta `vehicle_api`) |
| `resources/views/automotores/create.blade.php` / `edit.blade.php` | Formularios de automotores |
| `database/seeders/AutomotorSeeder.php` | Seed de automotores |
| `tests/Feature/AutomotorCrudTest.php` | Tests del CRUD |

---

## Requisitos técnicos de la nueva feature

### Requisitos funcionales

| ID | Requisito |
|----|-----------|
| RF-01 | Configurar el endpoint y token de la API en `.env` (`VEHICLE_API_URL`, `VEHICLE_API_TOKEN`) leídos vía `config/services.php`. |
| RF-02 | Implementar `AutomotorApiService::buscarPorPlaca()` con la llamada HTTP `POST` real (header `Authorization: Bearer`, body `{"placa": ...}`). |
| RF-03 | Mapear la respuesta `data` a los campos: `marca, modelo, serie, color, motor` y **`vin`**. |
| RF-04 | Agregar la columna `vin` (nullable) a `automotores` y al modelo, formularios, seeder y tests del CRUD. |
| RF-05 | En la confirmación de tickets (ingreso/cambio de aceite), el automotor queda enriquecido con los datos de la API (incluido `vin`). |
| RF-06 | Agregar un endpoint interno `GET /automotores/consultar-placa?placa=...` que devuelva los datos del vehículo (`{success, data}`) para autocompletar el formulario CRUD. |
| RF-07 | Agregar botón "Consultar placa" en crear/editar de automotores que autocompleta marca, modelo, serie, color, motor y vin. |
| RF-08 | Si la placa ya existe en la BD, el autocompletado devuelve los datos locales (coherencia). |
| RF-09 | Ampliar el seeder de automotores con `vin`. |

### Requisitos no funcionales

| ID | Requisito |
|----|-----------|
| RNF-01 | **Resiliencia (fallback)**: si la API no responde, responde con error, no encuentra el vehículo o no está configurada → `[]` y log, **sin bloquear** la confirmación ni el autocompletado (se mantiene el RNF-02 de la feature automotores). |
| RNF-02 | **Timeout** acotado (5 s) en la llamada HTTP para no degradar la confirmación de tickets. |
| RNF-03 | **Normalización** de placa a mayúsculas y sin espacios antes de consultar. |
| RNF-04 | **Idempotencia**: sigue siendo upsert por `placa` (no duplica automotores). |
| RNF-05 | El token **nunca** se versiona (solo en `.env`, placeholder en `.env.example`). |
| RNF-06 | Cobertura de tests unitarios (`AutomotorApiService`) y feature (CRUD de automotores) actualizadas. |

---

## Diseño técnico

### 1. Configuración de la API

#### `config/services.php` (modificado)

```php
'vehicle_api' => [
    'url'   => env('VEHICLE_API_URL'),
    'token' => env('VEHICLE_API_TOKEN'),
],
```

#### `.env` y `.env.example` (modificados)

```ini
VEHICLE_API_URL=https://api.json.pe/api/placa
VEHICLE_API_TOKEN=
```

> `VEHICLE_API_TOKEN` queda con valor vacío (placeholder). El sistema funciona en modo fallback hasta que el operador lo configure.

### 2. Esquema de base de datos

#### Tabla modificada `automotores`

Se agrega una nueva migración (la tabla ya existe desde la feature automotores):

| Columna | Tipo | Restricciones | Descripción |
|---------|------|---------------|-------------|
| `vin` | varchar(100) | nullable | Nº de identificación vehicular (VIN), distinto de `serie` |

```
automotores (existente) + ADD vin (nullable)
```

### 3. Modelos

#### `app/Models/Automotor.php` (modificado)

- Agregar `'vin'` al array `$fillable`.

### 4. Servicio de integración — llamada real

#### `app/Services/AutomotorApiService.php` (modificado)

Reemplaza el bloque `TODO(api)` por la implementación real:

```php
public function buscarPorPlaca(string $placa): array
{
    $placa = Automotor::normalizarPlaca($placa);
    $url   = config('services.vehicle_api.url');
    $token = config('services.vehicle_api.token');

    if (empty($url) || empty($token)) {
        Log::info('AutomotorApiService: API vehicular no configurada, se omite consulta.', ['placa' => $placa]);
        return [];
    }

    try {
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(5)
            ->post($url, ['placa' => $placa]);

        if (! $response->ok()) {
            Log::warning('AutomotorApiService: respuesta no exitosa de la API.', [
                'placa' => $placa,
                'status' => $response->status(),
            ]);
            return [];
        }

        $payload = $response->json();

        if (($payload['success'] ?? false) !== true || empty($payload['data'])) {
            Log::info('AutomotorApiService: vehículo no encontrado en la API.', ['placa' => $placa]);
            return [];
        }

        $dato = $payload['data'];

        return array_filter([
            'marca'  => $dato['marca'] ?? null,
            'modelo' => $dato['modelo'] ?? null,
            'serie'  => $dato['serie'] ?? null,
            'color'  => $dato['color'] ?? null,
            'motor'  => $dato['motor'] ?? null,
            'vin'    => $dato['vin'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    } catch (\Throwable $e) {
        Log::error('AutomotorApiService: no se pudo obtener datos del vehículo.', [
            'placa' => $placa,
            'error' => $e->getMessage(),
        ]);
        return [];
    }
}
```

**Comportamiento**:
- Sin config → log + `[]`.
- HTTP no 2xx → log + `[]`.
- `success: false` o sin `data` → log + `[]`.
- Éxito → mapa de campos (filtra vacíos).
- Excepción de red/tiempo → log + `[]`.

### 5. Flujo de confirmación de tickets

#### `app/Services/ClienteAutomotorService.php` (modificado)

En `obtenerAutomotor()`, agregar `'vin'` al bucle de enriquecimiento:

```php
foreach (['marca', 'modelo', 'serie', 'color', 'motor', 'vin'] as $campo) {
    if (! empty($apiDatos[$campo])) {
        $datos[$campo] = $apiDatos[$campo];
    }
}
```

No se tocan los controladores de ingreso/cambio de aceite: ya invocan `obtenerAutomotor($placa, $clienteId, true)` en la confirmación.

### 6. Autocompletado en el CRUD de automotores

#### `routes/web.php` (modificado)

Dentro del grupo `permission:acceso-automotores`, **antes** del `Route::resource` (para evitar que Laravel interprete `consultar-placa` como `{automotor}`):

```php
Route::get('/automotores/consultar-placa', [AutomotorController::class, 'consultarPlaca'])
    ->name('automotores.consultarPlaca');
```

#### `app/Http/Controllers/AutomotorController.php` (modificado)

- Agregar regla de placa para la consulta (coincide con la del ticket):

```php
private const PLACA_RULE = 'regex:/^[A-Z0-9-]{6,7}$/i';
```

- Agregar método:

```php
public function consultarPlaca(Request $request): JsonResponse
{
    $request->validate(['placa' => ['required', 'string', 'max:7', self::PLACA_RULE]]);

    $placa = Automotor::normalizarPlaca($request->placa);

    $automotor = Automotor::where('placa', $placa)->first();
    if ($automotor) {
        return response()->json([
            'success' => true,
            'data' => [
                'placa'  => $automotor->placa,
                'marca'  => $automotor->marca,
                'modelo' => $automotor->modelo,
                'serie'  => $automotor->serie,
                'color'  => $automotor->color,
                'motor'  => $automotor->motor,
                'vin'    => $automotor->vin,
            ],
        ]);
    }

    return response()->json([
        'success' => true,
        'data' => array_merge(['placa' => $placa], $this->automotorApiService->buscarPorPlaca($placa)),
    ]);
}
```

> Nota: aunque la API devuelva `[]`, `success` es `true` con solo la placa, y el front no autocompleta campos vacíos.

- Agregar `vin` a las reglas de validación (`store`/`update`) con `self::CODIGO_RULE` y al `create` de `store()`/`update()`.

#### Vistas `create.blade.php` / `edit.blade.php` (modificadas)

- Botón **"Consultar placa"** junto al campo `placa`: invoca el endpoint, muestra estado de carga y autocompleta los campos vacíos.
- Campo **`vin`** (máx. 100, `data-filter="alphanumeric"`) entre `motor` y el bloque de submit.

#### `resources/js/automotores/validate.js` (modificado)

- Registra el evento `click` del botón.
- Lee la placa; si tiene 6-7 caracteres, hace `fetch('/automotores/consultar-placa?placa=' + placa)`.
- Con `data.success` rellena los inputs `marca, modelo, serie, color, motor, vin` solo si están vacíos.
- Manejo de errores silencioso (consola + estado del botón).

### 7. Seeders

#### `database/seeders/AutomotorSeeder.php` (modificado)

- Agregar `'vin' => $faker->bothify('VIN##########')` al array de `firstOrCreate`.

### 8. Tests

#### `tests/Unit/AutomotorApiServiceTest.php` (nuevo)

Con `Http::fake()` se cubre:

| Caso | Expectativa |
|------|-------------|
| Respuesta 200 con `data` completo | Mapea `marca, modelo, serie, color, motor` y `vin` |
| Respuesta 200 con `success: false` | `[]` |
| Respuesta 200 con `data` vacío | `[]` |
| HTTP 404 / 500 | `[]` |
| Sin config (`VEHICLE_API_URL`/`VEHICLE_API_TOKEN`) | `[]` |
| Excepción de red (`Http::fake` con throw) | `[]` |

Nota de configuración de pruebas: el servicio lee `config('services.vehicle_api.*')`; en los tests se setean valores vía `config([...])` antes de cada caso.

#### `tests/Feature/AutomotorCrudTest.php` (modificado)

- Agregar `'vin' => 'VIN12345'` al `validPayload()`.
- Agregar caso: `vin` se guarda/edita correctamente.
- Si corresponde, caso: `store` con placa existente + autocompletado local (opcional).

---

## Plan de implementación en 4 unidades de trabajo

### Unidad 1 — Configuración, esquema y modelo (fundamento)

**Objetivo:** preparar config y BD para recibir los datos de la API.

| # | Tarea |
|---|-------|
| 1.1 | Agregar `vehicle_api` a `config/services.php`. |
| 1.2 | Agregar `VEHICLE_API_URL` y `VEHICLE_API_TOKEN` (placeholder) a `.env` y `.env.example`. |
| 1.3 | Crear migración `add_vin_to_automotores` (`vin` nullable). |
| 1.4 | Agregar `vin` al `$fillable` de `Automotor`. |
| 1.5 | **Verificación Unidad 1:** `php artisan migrate` OK. |

### Unidad 2 — Servicio API (comportamiento)

**Objetivo:** conectar la llamada real y su fallback resiliente.

| # | Tarea |
|---|-------|
| 2.1 | Implementar `AutomotorApiService::buscarPorPlaca()` (POST, Bearer, timeout, mapeo, logs). |
| 2.2 | Agregar `vin` al enriquecimiento en `ClienteAutomotorService::obtenerAutomotor()`. |
| 2.3 | Escribir `tests/Unit/AutomotorApiServiceTest.php`. |
| 2.4 | **Verificación Unidad 2:** `php artisan test --filter=AutomotorApiServiceTest` verde. |

### Unidad 3 — Autocompletado CRUD (entrega funcional)

**Objetivo:** acelerar el registro manual desde el módulo automotores.

| # | Tarea |
|---|-------|
| 3.1 | Agregar método `consultarPlaca()` y reglas `vin` en `AutomotorController`. |
| 3.2 | Registrar ruta `automotores.consultarPlaca` (antes del resource). |
| 3.3 | Agregar botón "Consultar placa" + campo `vin` en `create.blade.php` y `edit.blade.php`. |
| 3.4 | Implementar autocompletado en `resources/js/automotores/validate.js`. |
| 3.5 | Actualizar `AutomotorSeeder` con `vin`. |
| 3.6 | Actualizar `tests/Feature/AutomotorCrudTest.php` (vin en payload). |
| 3.7 | **Verificación Unidad 3:** `npm run build`, pruebas feature verdes. |

### Unidad 4 — Validación final (entrega)

**Objetivo:** garantizar no regresiones.

| # | Tarea |
|---|-------|
| 4.1 | `php artisan migrate` (aplicar migración vin en entorno real). |
| 4.2 | `php artisan test` (suite completa). |
| 4.3 | `vendor/bin/pint --test` (formato). |
| 4.4 | `php artisan config:clear` y `php artisan route:list` (verificar ruta nueva). |

---

## Integración con la API REST

| Aspecto | Detalle |
|---------|---------|
| Proveedor | api.json.pe |
| Endpoint | `POST https://api.json.pe/api/placa` |
| Autenticación | Header `Authorization: Bearer <token>` |
| Body (JSON) | `{ "placa": "F3H792" }` |
| Respuesta 200 | `{ success: boolean, message: string, data: { placa, marca, modelo, serie, color, motor, vin } }` |
| Campos mapeados | `marca, modelo, serie, color, motor, vin` |
| Configuración | `VEHICLE_API_URL`, `VEHICLE_API_TOKEN` (`.env`) → `config('services.vehicle_api.*')` |
| Mecanismo HTTP | `Illuminate\Support\Facades\Http::withToken(...)->acceptJson()->timeout(5)->post(...)` |
| Normalización | Placa a mayúsculas y sin espacios antes de enviar |
| Fallback | API no configurada / error / no encontrada → `[]` + log, flujo no se bloquea (RNF-02 automotores) |
| Idempotencia | Upsert por `placa` (RNF-04 automotores) |

---

## Resultados esperados

### Datos

- `automotores.vin` guarda el VIN devuelto por la API (además de `serie`).
- Un automotor confirmado vía ticket queda con `marca, modelo, serie, color, motor, vin` si la API los provee.
- Si la API falla, el automotor se registra igualmente con la placa (fallback).

### Flujo

- La **confirmación** de ingresos/cambio de aceite enriquece el automotor con datos reales del vehículo.
- El formulario **crear/editar automotor** dispone del botón "Consultar placa" que autocompleta los campos sin tipeo manual.
- El autocompletado prioriza datos locales si la placa ya está registrada.

### Calidad

- `AutomotorApiService` con tests unitarios (6+ casos, incluido fallback).
- CRUD de automotores con `vin` cubierto por tests.
- Sin cambios de comportamiento en el flujo de confirmación que no sea el enriquecimiento.

---

## Criterios de aceptación

1. `config/services.php` expone `vehicle_api.url` y `vehicle_api.token`; `.env.example` documenta `VEHICLE_API_URL` y `VEHICLE_API_TOKEN` sin valores reales.
2. `automotores.vin` (nullable) existe tras `php artisan migrate`.
3. `AutomotorApiService::buscarPorPlaca()` realiza el `POST` con Bearer token, mapea `data` y devuelve `[]` ante cualquier fallo.
4. Confirmar un ingreso/cambio de aceite con placa enriquece el automotor (incluido `vin`) y, si la API no responde, **no falla** la confirmación.
5. Existe `GET /automotores/consultar-placa` bajo `acceso-automotores` que devuelve datos locales o de la API.
6. Los formularios crear/editar de automotores incluyen el botón "Consultar placa" y el campo `vin`.
7. `php artisan test`, `vendor/bin/pint --test` y `npm run build` pasan sin errores.

---

## Verificación

| Comando | Propósito |
|---------|-----------|
| `php artisan migrate` | Aplicar columna `vin` |
| `php artisan test --filter=AutomotorApiServiceTest` | Tests del servicio API |
| `php artisan test` | Suite completa (sin regresiones) |
| `vendor/bin/pint --test` | Formato de código |
| `npm run build` | Compilar assets (Vite) |
| `php artisan route:list` | Validar ruta `automotores.consultarPlaca` |
| `php artisan config:clear` | Refrescar config tras editar `.env` |

---

## Historial de cambios

| Fecha | Descripción |
|-------|-------------|
| 2026-08-28 | Creación del documento con el plan de integración de la API de consulta de placa (api.json.pe). |