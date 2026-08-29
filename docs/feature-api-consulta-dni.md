# Feature: Integración API Consulta de DNI (api.json.pe)

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

Tras integrar la API de **consulta de placa** (`AutomotorApiService`), se dispone de la documentación del mismo proveedor **api.json.pe** para la **consulta de DNI**:

- `POST https://api.json.pe/api/dni`
- Header de autenticación: `Authorization: Bearer <token>`
- Body JSON: `{ "dni": "27427864" }`
- Respuesta 200 (JSON): `{ success: boolean, message: string, data: { numero, codigo_verificacion, nombres, apellido_paterno, apellido_materno, nombre_completo, direccion, direccion_completa, ubigeo_reniec, ubigeo_sunat } }`

El token de autenticación **es el mismo** que el de la API de placa (`VEHICLE_API_TOKEN`), por lo que no se introduce una nueva credencial.

Esta feature **conecta dicha API** para **autocompletar los formularios CRUD de los módulos de clientes y trabajadores** (crear/editar) con un botón "Consultar DNI", evitando el tipeo manual de nombres y apellidos al registrar personas.

Replica el comportamiento resiliente de la feature de placa (RNF-02): si la API no responde, no encuentra la persona o no está configurada, el formulario **no se bloquea** y se rellena solo lo que esté disponible.

**Alcance:** módulos `clientes` y `trabajadores` (formularios CRUD). El flujo de confirmación de tickets (ingreso/cambio de aceite), que crea clientes con DNI opcional capturado manualmente en el ticket, queda **fuera de alcance** de esta feature.

---

## Estado actual del sistema

### Servicio de integración existente (patrón a replicar)

`app/Services/AutomotorApiService.php` define el contrato resiliente que se tomará como referencia:

- Método `buscarPorPlaca(string $placa): array` que devuelve `['marca','modelo','serie','color','motor','vin']` o `[]`.
- Lee `config('services.vehicle_api.url')` / `config('services.vehicle_api.token')`.
- Sin config / HTTP no 2xx / `success:false` / `data` vacío / excepción de red → log + `[]`.

### Configuración vigente

`config/services.php`:

```php
'vehicle_api' => [
    'url'   => env('VEHICLE_API_URL'),
    'token' => env('VEHICLE_API_TOKEN'),
],
```

`.env` (con token real configurado) y `.env.example` (placeholder):

```ini
VEHICLE_API_URL=https://api.json.pe/api/placa
VEHICLE_API_TOKEN=
```

### Módulos de clientes y trabajadores

- `app/Http/Controllers/ClienteController.php`: CRUD (`index/create/store/edit/update/destroy`) + `buscarPorPlaca()` (JSON, para ventas). Campos: `dni, nombre, apellido_paterno, apellido_materno, telefono`.
- `app/Http/Controllers/TrabajadorController.php`: CRUD + `toggleStatus()`. Campos: `dni, nombre, apellido_paterno, apellido_materno, foto, estado`.
- Vistas: `resources/views/clientes/create.blade.php` / `edit.blade.php` (JS `resources/js/clientes/validate.js`) y `resources/views/trabajadores/create.blade.php` / `edit.blade.php` (JS `resources/js/trabajadores/create.js` / `edit.js`).
- Rutas: `Route::resource('clientes', ...)` y `Route::resource('trabajadores', ...)` bajo `permission:acceso-clientes` / `permission:acceso-trabajadores` en `routes/web.php`.
- Modelos `app/Models/Cliente.php` y `app/Models/Trabajador.php`: guardan `nombre` (nombres de pila), `apellido_paterno`, `apellido_materno`. El DNI es `unique` en ambas tablas (nullable en `clientes`, obligatorio en `trabajadores`).

> La consulta de placa en automotores ya estableció el patrón de autocompletado: endpoint interno `GET` + botón "Consultar placa" + JS que rellena solo campos vacíos (`resources/js/automotores/validate.js`). Esta feature lo replica para DNI.

### Archivos clave del estado actual

| Archivo | Rol |
|---|---|
| `app/Services/AutomotorApiService.php` | Servicio de integración con la API vehicular (patrón a replicar) |
| `app/Http/Controllers/ClienteController.php` / `TrabajadorController.php` | CRUD de los módulos objetivo |
| `resources/views/{clientes,trabajadores}/create.blade.php` / `edit.blade.php` | Formularios a enriquecer con el botón "Consultar DNI" |
| `resources/js/clientes/validate.js` / `resources/js/trabajadores/{create,edit}.js` | Frontend de validación/foto a extender con la consulta |
| `config/services.php` | Config de servicios terceros (se agrega `dni_api`) |
| `routes/web.php` | Rutas de los módulos (se agregan los endpoints de consulta) |

---

## Requisitos técnicos de la nueva feature

### Requisitos funcionales

| ID | Requisito |
|----|-----------|
| RF-01 | Configurar el endpoint de la API DNI en `.env` (`DNI_API_URL`) reutilizando el token de placa (`VEHICLE_API_TOKEN`), leído vía `config/services.php`. |
| RF-02 | Implementar `DniApiService::buscarPorDni()` con la llamada HTTP `POST` real (header `Authorization: Bearer`, body `{"dni": ...}`). |
| RF-03 | Mapear la respuesta `data` a los campos: `numero, codigo_verificacion, nombres, apellido_paterno, apellido_materno, nombre_completo, direccion, direccion_completa, ubigeo_reniec, ubigeo_sunat` (filtrando vacíos). |
| RF-04 | Agregar endpoints internos `GET /clientes/consultar-dni?dni=...` y `GET /trabajadores/consultar-dni?dni=...` que devuelvan `{success, data}` para autocompletar los formularios CRUD. |
| RF-05 | Si el DNI ya existe en la BD (`clientes`/`trabajadores`), el autocompletado devuelve los **datos locales** (coherencia, igual que en placa). |
| RF-06 | Agregar el botón "Consultar DNI" en crear/editar de clientes y trabajadores, que autocompleta nombres y apellidos (solo campos vacíos). |

### Requisitos no funcionales

| ID | Requisito |
|----|-----------|
| RNF-01 | **Resiliencia (fallback)**: si la API no responde, responde con error, no encuentra la persona o no está configurada → `[]` y log, **sin bloquear** el formulario (RNF-02 de la feature de placa). |
| RNF-02 | **Timeout** acotado (5 s) en la llamada HTTP para no degradar la UX del formulario. |
| RNF-03 | **Validación** del DNI: string numérico de exactamente 8 dígitos (`^[0-9]{8}$`) antes de consultar. |
| RNF-04 | **Idempotencia**: la consulta es solo lectura; no se modifica ningún registro. |
| RNF-05 | El token **nunca** se versiona (reutiliza `VEHICLE_API_TOKEN`, placeholder en `.env.example`; no se agrega credencial nueva). |
| RNF-06 | Cobertura de tests unitarios (`DniApiService`) y feature (endpoints de consulta). |

---

## Diseño técnico

### 1. Configuración de la API

#### `config/services.php` (modificado)

```php
'dni_api' => [
    'url'   => env('DNI_API_URL', 'https://api.json.pe/api/dni'),
    'token' => env('VEHICLE_API_TOKEN'),
],
```

> Se reutiliza el token de placa: el proveedor y la autenticación son los mismos, por lo que no se introduce una nueva variable de token (única fuente de verdad).

#### `.env` y `.env.example` (modificados)

```ini
# API de consulta de datos DNI (api.json.pe) - reutiliza VEHICLE_API_TOKEN
DNI_API_URL=https://api.json.pe/api/dni
```

### 2. Servicio de integración

#### `app/Services/DniApiService.php` (nuevo)

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DniApiService
{
    public function buscarPorDni(string $dni): array
    {
        $dni   = trim($dni);
        $url   = config('services.dni_api.url');
        $token = config('services.dni_api.token');

        if (empty($url) || empty($token)) {
            Log::info('DniApiService: API DNI no configurada, se omite consulta.', ['dni' => $dni]);

            return [];
        }

        if (! preg_match('/^[0-9]{8}$/', $dni)) {
            Log::info('DniApiService: DNI inválido, se omite consulta.', ['dni' => $dni]);

            return [];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(5)
                ->post($url, ['dni' => $dni]);

            if (! $response->ok()) {
                Log::warning('DniApiService: respuesta no exitosa de la API.', [
                    'dni' => $dni,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $payload = $response->json();

            if (($payload['success'] ?? false) !== true || empty($payload['data'])) {
                Log::info('DniApiService: persona no encontrada en la API.', ['dni' => $dni]);

                return [];
            }

            $dato = $payload['data'];

            return array_filter([
                'numero' => $dato['numero'] ?? null,
                'codigo_verificacion' => $dato['codigo_verificacion'] ?? null,
                'nombres' => $dato['nombres'] ?? null,
                'apellido_paterno' => $dato['apellido_paterno'] ?? null,
                'apellido_materno' => $dato['apellido_materno'] ?? null,
                'nombre_completo' => $dato['nombre_completo'] ?? null,
                'direccion' => $dato['direccion'] ?? null,
                'direccion_completa' => $dato['direccion_completa'] ?? null,
                'ubigeo_reniec' => $dato['ubigeo_reniec'] ?? null,
                'ubigeo_sunat' => $dato['ubigeo_sunat'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
        } catch (\Throwable $e) {
            Log::error('DniApiService: no se pudo obtener datos de la persona.', [
                'dni' => $dni,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
```

**Comportamiento**:
- Sin config → log + `[]`.
- DNI sin formato de 8 dígitos → log + `[]`.
- HTTP no 2xx → log + `[]`.
- `success: false` o sin `data` → log + `[]`.
- Éxito → mapa de campos (filtra vacíos).
- Excepción de red/tiempo → log + `[]`.

> El mapeo de la API (`nombres`) al modelo (`nombre`) se resuelve en el controlador/frontend al autocompletar el campo "Nombres".

### 3. Endpoints internos de consulta

#### `app/Http/Controllers/ClienteController.php` (modificado)

- Agregar `use App\Services\DniApiService;` e inyección por constructor:

```php
public function __construct(private DniApiService $dniApiService) {}
```

- Agregar método:

```php
public function consultarDni(Request $request): JsonResponse
{
    $request->validate([
        'dni' => ['required', 'string', 'digits:8', 'regex:/^[0-9]{8}$/'],
    ]);

    $cliente = Cliente::where('dni', $request->dni)->first();

    if ($cliente) {
        return response()->json([
            'success' => true,
            'data' => [
                'numero' => $cliente->dni,
                'nombres' => $cliente->nombre,
                'apellido_paterno' => $cliente->apellido_paterno,
                'apellido_materno' => $cliente->apellido_materno,
            ],
        ]);
    }

    return response()->json([
        'success' => true,
        'data' => $this->dniApiService->buscarPorDni($request->dni),
    ]);
}
```

> Nota: aunque la API devuelva `[]`, `success` es `true`, y el front solo autocompleta los campos vacíos disponibles.

#### `app/Http/Controllers/TrabajadorController.php` (modificado)

- Misma inyección de `DniApiService` y método `consultarDni()` con la búsqueda local en `Trabajador::where('dni', ...)`.

#### `routes/web.php` (modificado)

Dentro del grupo `permission:acceso-clientes`, **antes** del `Route::resource` (para evitar que Laravel interprete `consultar-dni` como `{cliente}`):

```php
Route::get('/clientes/consultar-dni', [ClienteController::class, 'consultarDni'])
    ->name('clientes.consultarDni');
```

Dentro del grupo `permission:acceso-trabajadores`, **antes** del `Route::resource` (y del `toggle-status`):

```php
Route::get('/trabajadores/consultar-dni', [TrabajadorController::class, 'consultarDni'])
    ->name('trabajadores.consultarDni');
```

### 4. Autocompletado en los formularios

#### Vistas `clientes/create.blade.php`, `clientes/edit.blade.php`, `trabajadores/create.blade.php`, `trabajadores/edit.blade.php` (modificadas)

- Envolver el input `dni` en un contenedor `flex` (mismo patrón del campo placa en `automotores/create.blade.php`) y agregar junto a él el botón:

```html
<div class="flex gap-2">
    <input id="dni" name="dni" ... class="flex-1 ..." placeholder="12345678">
    <button
        type="button"
        id="btn-consultar-dni"
        aria-label="Consultar DNI"
        class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
        </svg>
        <span id="label-consultar-dni">Consultar DNI</span>
    </button>
</div>
```

> La vista `edit` conserva `value="{{ old('dni', $cliente->dni) }}"` / `$trabajador->dni` en el input.

#### `resources/js/utils/consultarDni.js` (nuevo)

Util reutilizable (patrón de `resources/js/productos/shared.js`) que:
- Recibe config: `{ btnId, dniId, labelId, endpoint }`.
- Al hacer clic, si el DNI tiene exactamente 8 dígitos, hace `fetch(endpoint + '?dni=' + dni)` con headers `X-Requested-With` / `Accept: application/json`.
- Muestra el estado "Consultando..." en el botón y lo deshabilita durante la petición.
- Con `data.success`, autocompleta `nombre`, `apellido_paterno`, `apellido_materno` **solo si están vacíos** (coincide con el comportamiento de placa).
- Manejo de errores silencioso (consola + restauración del botón).

#### `resources/js/clientes/validate.js` (modificado)

- Al `DOMContentLoaded`, invocar `initConsultarDni({ btnId: 'btn-consultar-dni', dniId: 'dni', labelId: 'label-consultar-dni', endpoint: '/clientes/consultar-dni' })` además de la validación actual. Cubre create y edit (mismo formulario `#form-cliente`).

#### `resources/js/trabajadores/create.js` / `edit.js` (modificados)

- Invocar `initConsultarDni({ ..., endpoint: '/trabajadores/consultar-dni' })` junto a `initFotoPreview`.

### 5. Tests

#### `tests/Unit/DniApiServiceTest.php` (nuevo)

Con `Http::fake()` se cubre (espejo de `AutomotorApiServiceTest`):

| Caso | Expectativa |
|------|-------------|
| Respuesta 200 con `data` completo | Mapea todos los campos de la API |
| Respuesta 200 con `success: false` | `[]` |
| Respuesta 200 con `data` vacío | `[]` |
| HTTP 404 / 500 | `[]` |
| Sin config (`DNI_API_URL` / token) | `[]` |
| DNI con formato inválido | `[]` |
| Excepción de red (`Http::fake` con throw) | `[]` |

Nota de configuración de pruebas: se setean `config(['services.dni_api.url' => ...])` y `config(['services.dni_api.token' => ...])` antes de cada caso.

#### `tests/Feature/ClienteConsultaDniTest.php` (nuevo)

| Caso | Expectativa |
|------|-------------|
| DNI ya registrado en `clientes` | Responde datos locales (`success:true`) |
| DNI no registrado | Mock de `DniApiService` → datos de la API |
| DNI inválido | Error de validación (`assertSessionHasErrors`) |
| Sin sesión de usuario | Redirect a `login` |

#### `tests/Feature/TrabajadorConsultaDniTest.php` (nuevo)

- Mismos casos de `ClienteConsultaDniTest` sobre el endpoint de trabajadores.

---

## Plan de implementación en 4 unidades de trabajo

### Unidad 1 — Configuración y servicio API (fundamento)

**Objetivo:** preparar config y el servicio resiliente de consulta DNI.

| # | Tarea |
|---|-------|
| 1.1 | Agregar `dni_api` a `config/services.php` (reutiliza `VEHICLE_API_TOKEN`). |
| 1.2 | Agregar `DNI_API_URL` a `.env` y `.env.example`. |
| 1.3 | Crear `app/Services/DniApiService.php` con `buscarPorDni()`. |
| 1.4 | Escribir `tests/Unit/DniApiServiceTest.php`. |
| 1.5 | **Verificación Unidad 1:** `php artisan test --filter=DniApiServiceTest` verde. |

### Unidad 2 — Endpoints internos (backend)

**Objetivo:** exponer la consulta a los formularios con prioridad local.

| # | Tarea |
|---|-------|
| 2.1 | Agregar `consultarDni()` e inyección de `DniApiService` en `ClienteController` y `TrabajadorController`. |
| 2.2 | Registrar rutas `clientes.consultarDni` y `trabajadores.consultarDni` (antes de los resources). |
| 2.3 | Escribir `tests/Feature/ClienteConsultaDniTest.php` y `TrabajadorConsultaDniTest.php`. |
| 2.4 | **Verificación Unidad 2:** `php artisan test --filter=ConsultaDni` verde. |

### Unidad 3 — Autocompletado en formularios (entrega funcional)

**Objetivo:** acelerar el alta/edición de clientes y trabajadores.

| # | Tarea |
|---|-------|
| 3.1 | Agregar botón "Consultar DNI" en `create.blade.php`/`edit.blade.php` de clientes y trabajadores. |
| 3.2 | Crear `resources/js/utils/consultarDni.js`. |
| 3.3 | Conectar el util en `resources/js/clientes/validate.js` y `resources/js/trabajadores/{create,edit}.js`. |
| 3.4 | **Verificación Unidad 3:** `npm run build` y prueba manual de autocompletado. |

### Unidad 4 — Validación final (entrega)

**Objetivo:** garantizar no regresiones.

| # | Tarea |
|---|-------|
| 4.1 | `php artisan test` (suite completa). |
| 4.2 | `vendor/bin/pint --test` (formato). |
| 4.3 | `php artisan config:clear` y `php artisan route:list` (verificar nuevas rutas). |
| 4.4 | `npm run build` (assets compilados). |

---

## Integración con la API REST

| Aspecto | Detalle |
|---------|---------|
| Proveedor | api.json.pe |
| Endpoint | `POST https://api.json.pe/api/dni` |
| Autenticación | Header `Authorization: Bearer <token>` (mismo token que placa: `VEHICLE_API_TOKEN`) |
| Body (JSON) | `{ "dni": "27427864" }` (string numérico de 8 dígitos) |
| Respuesta 200 | `{ success: boolean, message: string, data: { numero, codigo_verificacion, nombres, apellido_paterno, apellido_materno, nombre_completo, direccion, direccion_completa, ubigeo_reniec, ubigeo_sunat } }` |
| Campos mapeados | `numero, codigo_verificacion, nombres, apellido_paterno, apellido_materno, nombre_completo, direccion, direccion_completa, ubigeo_reniec, ubigeo_sunat` |
| Configuración | `DNI_API_URL` (`.env`) + `VEHICLE_API_TOKEN` → `config('services.dni_api.*')` |
| Mecanismo HTTP | `Illuminate\Support\Facades\Http::withToken(...)->acceptJson()->timeout(5)->post(...)` |
| Validación | DNI numérico de exactamente 8 dígitos (`^[0-9]{8}$`) |
| Fallback | API no configurada / DNI inválido / error / no encontrada → `[]` + log, formulario no se bloquea (RNF-02) |
| Prioridad local | Si el DNI ya existe en `clientes`/`trabajadores`, se responde con datos locales (RNF/RF-05) |

---

## Resultados esperados

### Datos

- `DniApiService::buscarPorDni()` devuelve los datos reales de la persona cuando la API los provee.
- Un cliente/trabajador registrado con su DNI ya cargado responde desde la BD local (coherencia).
- Si la API falla, el formulario se queda tal cual: no hay pérdida de datos ni bloqueo.

### Flujo

- Los formularios **crear/editar** de clientes y trabajadores disponen del botón "Consultar DNI" que autocompleta nombres, apellido paterno y apellido materno sin tipeo manual.
- El autocompletado solo rellena campos vacíos y prioriza datos locales si el DNI ya está registrado.

### Calidad

- `DniApiService` con tests unitarios (7+ casos, incluido fallback).
- Endpoints de consulta con tests feature (local-first y API).
- Sin cambios de comportamiento en el flujo de confirmación de tickets ni en el CRUD existente más allá del autocompletado.

---

## Criterios de aceptación

1. `config/services.php` expone `dni_api.url` y `dni_api.token` (token reutilizado de placa); `.env.example` documenta `DNI_API_URL` sin valores reales de token.
2. `DniApiService::buscarPorDni()` realiza el `POST` con Bearer token, valida el DNI, mapea `data` y devuelve `[]` ante cualquier fallo.
3. Existen `GET /clientes/consultar-dni` y `GET /trabajadores/consultar-dni` bajo sus permisos respectivos, que devuelven datos locales o de la API.
4. Los formularios crear/editar de clientes y trabajadores incluyen el botón "Consultar DNI" que autocompleta nombre y apellidos (solo campos vacíos).
5. Un DNI ya registrado devuelve los datos locales; uno no registrado consulta la API; si la API no responde, el formulario no se bloquea.
6. `php artisan test`, `vendor/bin/pint --test` y `npm run build` pasan sin errores.

---

## Verificación

| Comando | Propósito |
|---------|-----------|
| `php artisan test --filter=DniApiServiceTest` | Tests del servicio API |
| `php artisan test --filter=ConsultaDniTest` | Tests de los endpoints de consulta |
| `php artisan test` | Suite completa (sin regresiones) |
| `vendor/bin/pint --test` | Formato de código |
| `npm run build` | Compilar assets (Vite) |
| `php artisan route:list` | Validar rutas `clientes.consultarDni` / `trabajadores.consultarDni` |
| `php artisan config:clear` | Refrescar config tras editar `.env` |

---

## Historial de cambios

| Fecha | Descripción |
|-------|-------------|
| 2026-08-28 | Creación del documento con el plan de integración de la API de consulta de DNI (api.json.pe). |