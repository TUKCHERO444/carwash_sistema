# Feature: Gestión de Automotores (Vehículos Físicos por Cliente)

## Índice

1. [Resumen ejecutivo](#resumen-ejecutivo)
2. [Estado actual del sistema](#estado-actual-del-sistema)
3. [Vulnerabilidades y deuda técnica identificadas](#vulnerabilidades-y-deuda-técnica-identificadas)
4. [Requisitos técnicos de la nueva feature](#requisitos-técnicos-de-la-nueva-feature)
5. [Diseño técnico](#diseño-técnico)
6. [Plan de implementación en 3 unidades de trabajo](#plan-de-implementación-en-3-unidades-de-trabajo)
7. [Integración con la API REST](#integración-con-la-api-rest)
8. [Resultados esperados](#resultados-esperados)
9. [Criterios de aceptación](#criterios-de-aceptación)
10. [Verificación](#verificación)

---

## Resumen ejecutivo

Se implementa el módulo **Automotores**: la tabla que almacena los **vehículos físicos** de cada cliente, identificados por su **placa** (PK). Esto corrige una confusión de diseño del sistema actual, donde:

- La placa vive como atributo único del cliente (1 cliente ⇔ 1 placa), lo que impide modelar que **un cliente posee varios vehículos**.
- La tabla `vehiculos` no contiene vehículos reales, sino un **catálogo de servicios con precio** (nombre, descripcion, precio) usado para calcular el importe del ticket de lavado.

La feature:

1. Crea la tabla `automotores` con `placa` (char 7, UNIQUE) como PK y los campos `marca, modelo, serie, color, motor`.
2. Establece la relación **1:N** `clientes → automotores` (1 cliente posee N automotores; 1 automotor pertenece a 1 cliente), respetando la integridad referencial.
3. **Elimina la placa** del formulario y del modelo `clientes`.
4. En el flujo de **ingreso / cambio de aceite**, la placa sigue siendo **obligatoria** en el ticket; al **confirmar** el ticket se emite el registro final del automotor: se consultan los datos del vehículo por **API REST** (pendiente de implementar) y se crea/actualiza el automotor vinculado al cliente.
5. El cliente en el ticket se registra **solo con nombre** (DNI y apellidos opcionales; DNI ya es nullable en BD).
6. Se refactorizan los **seeders** de las entidades involucradas.

---

## Estado actual del sistema

### Modelo de datos vigente

```
clientes
  id            bigint PK
  dni           string(8)   nullable (antes unique not null)
  nombre        string(50)  nullable
  apellido_paterno string(50) nullable
  apellido_materno string(50) nullable
  telefono      string(20)  nullable
  placa         string(7)   NOT NULL  ← la placa vive en el cliente
  created_at / updated_at

vehiculos                        ← CATÁLOGO DE SERVICIOS (no vehículos reales)
  id            bigint PK
  nombre        string(100)
  descripcion   text
  precio        decimal(10,2)
  timestamps

ingresos
  id            bigint PK
  cliente_id    FK → clientes (cascade)
  vehiculo_id   FK → vehiculos (catálogo de servicio/precio)
  fecha, foto, estado
  precio, total, user_id, caja_id
  metodo_pago, monto_efectivo, monto_yape, monto_izipay

cambio_aceites
  id            bigint PK
  cliente_id    FK → clientes (cascade)
  trabajador_id FK → trabajadores
  fecha, descripcion, foto, estado
  precio, total, user_id, caja_id
  metodo_pago, monto_efectivo, monto_yape, monto_izipay
```

### Flujo actual del ticket (Ingreso y Cambio de Aceite)

En `store()` (ticket **pendiente**) y `procesarConfirmacion()` (ticket **confirmado**) ambos controladores ejecutan lo mismo:

```php
$cliente = Cliente::updateOrCreate(
    ['placa' => $request->placa],
    ['nombre' => $request->nombre, 'telefono' => $request->telefono, 'dni' => $request->dni]
);
```

Es decir, **la placa es la clave de búsqueda/creación del cliente**, y cada placa genera (o reutiliza) un único cliente. `ingresos` y `cambio_aceites` conservan el `cliente_id` resultante.

### Autocompletado por placa

- `resources/js/buscador-placa.js` consulta `GET /clientes/buscar-por-placa?placa=...`.
- `ClienteController::buscarPorPlaca()` busca en `clientes.placa` y devuelve datos + `withCount(['ingresos','cambioAceites'])`.

### Archivos clave del estado actual

| Archivo | Rol |
|---|---|
| `database/migrations/2024_01_01_000001_create_clientes_table.php` + 3 migraciones posteriores | Esquema de clientes (incluye `placa`, apellidos, telefono, nulidad DNI) |
| `app/Models/Cliente.php` | Modelo con `ventas()`, `ingresos()`, `cambioAceites()` |
| `app/Http/Controllers/IngresoController.php` | Ticket de lavado/ingreso |
| `app/Http/Controllers/CambioAceiteController.php` | Ticket de cambio de aceite |
| `app/Http/Controllers/ClienteController.php` | CRUD + `buscarPorPlaca` |
| `app/Models/Vehiculo.php` | Catálogo de servicios con precio |
| `resources/views/clientes/create.blade.php` / `edit.blade.php` | Formularios con campo `placa` |
| `database/seeders/ClienteSeeder.php`, `IngresoSeeder.php`, `CambioAceiteSeeder.php` | Seeders con placa en cliente |

### Nulidad de DNI (estado verificado)

- En BD: `clientes.dni` ya es **nullable** (migración `2026_05_01_211035`). ✅
- En el flujo de tickets: `dni` y `telefono` ya se validan como `nullable`. ✅
- En el CRUD de clientes: `ClienteController::validationRules()` **aún exige `dni`, `apellido_paterno`, `apellido_materno` como `required`** ❌ → contradice la realidad de negocio "registro solo con nombre".

---

## Vulnerabilidades y deuda técnica identificadas

### V1 — Relación huérfana `ventas()` (bug de eliminación de clientes)

- `app/Models/Cliente.php` define `ventas(): HasMany`, pero la tabla `ventas` **no tiene columna `cliente_id`** (no existe en ninguna migración).
- Consecuencia: `ClienteController::destroy()` (línea 108) ejecuta `$cliente->ventas()->exists()`, que lanza `SQLSTATE[HY000]: no such column: ventas.cliente_id`. **El borrado de cualquier cliente falla con excepción.**
- **Mitigación:** revisar/corregir esta relación en el marco de la feature (decisión: no introducir `cliente_id` en ventas por ahora; eliminar la relación huérfana o protegerla).

### V2 — Confusión semántica entre `vehiculos` (catálogo) y vehículos reales

- El nombre/modelo `Vehiculo` se usa para un catálogo de precios de servicio, y no existe un modelo de vehículo físico por cliente. Esto generó el diseño defectuoso de "1 placa por cliente".

### V3 — Validación CRUD de clientes incoherente con la realidad de negocio

- `validationRules()` exige `dni`, apellidos y `placa` como obligatorios mientras la BD los permite nulos.
- `placa` es `required` pero será eliminada de `clientes`.

### V4 — Multiplicidad de placa por cliente inexistente

- Hoy un cliente solo puede tener **una** placa (atributo único), impidiendo el modelo real 1:N cliente→vehículo. El `updateOrCreate` por `placa` además genera **clientes fantasma** cuando solo se escribe la placa sin nombre.

### V5 — Sin manejo de error en carga de foto / dependencias externas (Cloudinary)

- El `store()` de tickets envuelve toda la transacción en `catch (\Throwable)` y responde un mensaje genérico "Intente nuevamente", sin loguear el error real (operación opaca ante fallos).

### V6 — Dependencia del autocompletado por `clientes.placa`

- `buscar-por-placa` depende de la columna `placa` que se eliminará; debe migrar a buscar en `automotores.placa`.

### V7 — Permisos/rutas de la nueva feature pendientes

- No existe permiso ni grupo de rutas para gestionar automotores. Debe decidirse si se crea un permiso nuevo (`acceso-automotores`) o se reutiliza uno existente.

---

## Requisitos técnicos de la nueva feature

### Requisitos funcionales

| ID | Requisito |
|----|-----------|
| RF-01 | Crear la tabla `automotores` con **placa (`char(7)`)** como **PK** y **UNIQUE**. |
| RF-02 | `automotores` debe contener `marca, modelo, serie, color, motor`. |
| RF-03 | Establecer relación **1:N** `clientes → automotores` (FK `automotor.cliente_id → clientes.id`), validando que un automotor pertenece a un único cliente. |
| RF-04 | **Eliminar** el campo `placa` de `clientes` (formulario + modelo + migración). |
| RF-05 | En el ticket de **ingreso** y de **cambio de aceite**, la placa es **obligatoria**; se captura en el registro del ticket. |
| RF-06 | Al **confirmar** el ticket (ingreso o cambio de aceite), se emite el registro final del automotor: se consulta la **API REST** por placa, se crea/actualiza el `automotor` y se le vincula el `cliente_id`. |
| RF-07 | El `cliente` en el flujo de ticket se registra **solo con nombre** (DNI y apellidos opcionales, respetando la nulidad ya existente). |
| RF-08 | Establecer **FK opcional `automotor_id`** en `ingresos` y `cambio_aceites` para registrar qué vehículo físico se atendió. |
| RF-09 | Mantener la tabla `vehiculos` (catálogo de servicios) separada y conservar `ingresos.vehiculo_id`. |
| RF-10 | Refactorizar los **seeders** de las entidades involucradas (clientes, automotores, ingresos, cambio_aceites, database seeder). |

### Requisitos no funcionales

| ID | Requisito |
|----|-----------|
| RNF-01 | **Integridad referencial** garantizada por FK en BD (migraciones). |
| RNF-02 | Manejo resiliente de la **API REST**: si la API no responde, el flujo de confirmación no debe bloquearse; se registra el automotor al menos con la placa (fallback) y se loguea el error. |
| RNF-03 | Compatibilidad con el catálogo `vehiculos` (no se rompe el cálculo de precio). |
| RNF-04 | Idempotencia: confirmar un ticket no debe duplicar automotores (upsert por placa). |
| RNF-05 | Backward-compatibilidad de vista: el ticket pendiente conserva el campo placa obligatorio. |
| RNF-06 | Cobertura de tests unitarios para la nueva lógica (servicio API + upsert de automotor + vinculación). |

---

## Diseño técnico

### 1. Esquema de base de datos

#### Nueva tabla `automotores`

| Columna | Tipo | Restricciones | Descripción |
|---------|------|---------------|-------------|
| `placa` | char(7) | **PK**, UNIQUE, not null | Identificador del vehículo |
| `cliente_id` | bigint | FK → `clientes.id` (cascade) | Propietario del vehículo |
| `marca` | varchar(100) | nullable | Marca del vehículo |
| `modelo` | varchar(100) | nullable | Modelo del vehículo |
| `serie` | varchar(100) | nullable | Serie/N° de chasis o vin |
| `color` | varchar(50) | nullable | Color |
| `motor` | varchar(100) | nullable | N° de motor |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

```
clientes (1) ──────── (N) automotores
    │ id                     ├─ placa (PK, UNIQUE)
    └─ (se elimina placa)    ├─ cliente_id (FK → clientes)
                             ├─ marca, modelo, serie, color, motor
```

#### Tablas modificadas

| Tabla | Cambio |
|-------|--------|
| `clientes` | `DROP COLUMN placa` |
| `ingresos` | `ADD automotor_id` FK → `automotores.placa` (nullable, `onDelete set null`) |
| `cambio_aceites` | `ADD automotor_id` FK → `automotores.placa` (nullable, `onDelete set null`) |

> Nota: la FK apunta a la PK `automotores.placa` (string). En Laravel el `constrained('automotores')` se resolverá con la columna asociada; se definirá explícitamente `references('placa')->on('automotores')`.

### 2. Migraciones a crear

| Migración | Contenido |
|-----------|-----------|
| `2026_08_28_000001_create_automotores_table` | Crear `automotores`; PK `placa`; FK `cliente_id` (cascade). |
| `2026_08_28_000002_add_automotor_id_to_ingresos_y_cambio_aceites_table` | FK `automotor_id` nullable en `ingresos` y `cambio_aceites`. |
| `2026_08_28_000003_drop_placa_from_clientes_table` | `DROP COLUMN placa` en `clientes`. |

### 3. Modelos

#### `app/Models/Automotor.php` (nuevo)

```php
class Automotor extends Model
{
    protected $primaryKey = 'placa';   // PK string
    public $incrementing = false;       // no autoincrement
    protected $keyType = 'string';      // PK tipo string

    protected $fillable = [
        'placa', 'cliente_id', 'marca', 'modelo', 'serie', 'color', 'motor',
    ];

    // Relación 1:N -> automotor pertenece a un cliente
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
```

#### `app/Models/Cliente.php` (modificado)

- Eliminar `placa` del `$fillable`.
- Agregar `automotores(): HasMany` → `$this->hasMany(Automotor::class, 'cliente_id', 'id')`.
- **Corregir/eliminar la relación huérfana `ventas()`** (V1).
- La PK de `automotores` es `placa`, por lo que la FK en `ingresos`/`cambio_aceites` apuntará a `placa`.

#### `app/Models/Ingreso.php` y `app/Models/CambioAceite.php` (modificados)

- Agregar `automotor_id` al `$fillable`.
- Agregar relación `automotor(): BelongsTo` → `$this->belongsTo(Automotor::class, 'automotor_id', 'placa')`.

### 4. Servicio de integración API REST

#### `app/Services/AutomotorApiService.php` (nuevo)

Interfaz estable para consultar la API REST de datos vehiculares (aún **no implementada / pendiente de conexión**).

```php
class AutomotorApiService
{
    // Endpoint configurable, p.ej. env(VEHICLE_API_URL)
    public function buscarPorPlaca(string $placa): ?array
    // Devuelve ['marca','modelo','serie','color','motor'] o null si no consta.
}
```

**Comportamiento resiliente (RNF-02):**
- Si la API responde → mapear campos.
- Si la API no responde / error / vehículo no encontrado → devolver `[]` y loguear (`Log::error`).
- El llamador hace *upsert* del automotor con los datos obtenidos; si no hay datos, crea el automotor **solo con la placa** (fallback) para no bloquear el flujo de confirmación.

### 5. Controladores

#### `IngresoController` y `CambioAceiteController` (modificados)

En todos los puntos donde hoy se hace `Cliente::updateOrCreate(['placa' => ...])` (`store`, `update`, `procesarConfirmacion`, `actualizarTicket`), reemplazar por la creación de cliente **solo con nombre**, identificando al cliente por un criterio estable:

- Si `dni` está presente → `updateOrCreate(['dni' => $request->dni], [...])`.
- Si no → `Cliente::create([...])` con nombre (los apellidos/teléfono pueden opcionalmente complementarse).

En `procesarConfirmacion()` (ingreso y cambio de aceite), **al confirmar**, ejecutar la emisión del automotor:

```php
$placa = strtoupper(trim($request->placa));      // normalizar
$datos = $this->automotorApiService->buscarPorPlaca($placa);

$automotor = Automotor::updateOrCreate(
    ['placa' => $placa],
    array_merge(['cliente_id' => $cliente->id], $datos)  // $datos puede ser []
);

$ticket->update(['automotor_id' => $automotor->placa, ...]);
```

Reglas de validación de `placa` en tickets: `required, string, regex:/^[A-Z0-9-]{6,7}$/i` (normalizar a mayúsculas).

#### `ClienteController` (modificado)

- `validationRules()`: **nombre obligatorio**; `apellido_paterno`, `apellido_materno`, `dni`, `telefono` opcionales (con validación de formato solo si se ingresan); **eliminar** regla de `placa`.
- `store()`/`update()`: quitar `placa` de `$request->only(...)`.
- `destroy()`: eliminar/quitar el chequeo de `ventas()` huérfano (V1) y **agregar** chequeo de `$cliente->automotores()->exists()`.
- `buscarPorPlaca()`: buscar ahora por `automotores.placa` (join con cliente) y devolver datos del automotor + cliente + conteos.

#### Controlador/resource de Automotores (opcional)

- Decidir si se expone un CRUD de automotores (`AutomotorController`) o solo se gestionan desde el ticket. Recomendación: al menos un listado/consulta para visualizar los vehículos de un cliente.

### 6. Rutas

- Ajustar `routes/web.php`:
  - Migrar `GET /clientes/buscar-por-placa` para consultar automotores.
  - Opcional: `Route::resource('automotores', AutomotorController::class)` bajo permiso (ver V7).
  - Definir/decidir el permiso `acceso-automotores` o reutilizar `acceso-clientes`.

### 7. Vistas y JS

| Vista / JS | Cambio |
|------------|--------|
| `resources/views/clientes/create.blade.php` / `edit.blade.php` | **Eliminar el bloque del campo `placa`**; ajustar marcadores de obligatorio (solo nombre). |
| `resources/js/clientes/validate.js` + `resources/js/utils/validation.js` | Remover dependencias del campo placa en los formularios de cliente. |
| `resources/js/buscador-placa.js` | Apuntar el endpoint al nuevo lookup por automotores. |
| Views `ingresos` y `cambio-aceite` (`create`, `edit`, `confirmar`, `show`, `pendientes`, `confirmados`, `index`, `ticket`) | Mostrar placa/marca/modelo del **automotor**; en el ticket pendiente conservar campo placa obligatorio. |

### 8. Seeders

#### `database/seeders/ClienteSeeder.php` (modificado)

- Quitar la generación y el campo `placa` de los clientes.

#### `database/seeders/AutomotorSeeder.php` (nuevo)

- Crear automotores vinculados a clientes existentes.
- Placa única (formato `ABC123`, `ABC-123`, etc.), `marca/modelo/serie/color/motor` con faker.
- 1 cliente puede tener 1..N automotores (respetando 1:N).

#### `database/seeders/IngresoSeeder.php` y `CambioAceiteSeeder.php` (modificados)

- Asignar `automotor_id` = placa de un automotor **perteneciente al mismo cliente** que el ticket (coherencia 1:N).

#### `database/seeders/DatabaseSeeder.php` (modificado)

- Registrar `AutomotorSeeder` en el orden correcto (después de `ClienteSeeder`, antes de `IngresoSeeder`/`CambioAceiteSeeder`).

---

## Plan de implementación en 3 unidades de trabajo

### Unidad 1 — Esquema, modelos y servicio (fundamento de datos)

**Objetivo:** sentar la base de datos y el modelo de la nueva entidad sin romper el sistema.

| # | Tarea |
|---|-------|
| 1.1 | Crear migración `create_automotores` (PK placa, FK cliente_id cascade, marca/modelo/serie/color/motor). |
| 1.2 | Crear migración `add_automotor_id` en `ingresos` y `cambio_aceites` (nullable, FK → `automotores.placa`). |
| 1.3 | Crear migración `drop_placa_from_clientes`. |
| 1.4 | Crear modelo `Automotor` (PK string, no incrementing). |
| 1.5 | Modificar `Cliente` (quitar `placa` de fillable, agregar `automotores()`, corregir `ventas()` huérfana). |
| 1.6 | Modificar `Ingreso` y `CambioAceite` (agregar `automotor_id` fillable + relación `automotor()`). |
| 1.7 | Crear servicio `AutomotorApiService` con método `buscarPorPlaca()` y manejo de fallback. |
| 1.8 | **Verificación Unidad 1:** `php artisan migrate:fresh` correcto; modelos resolvieron relaciones (`php artisan tinker` para inspeccionar). |

### Unidad 2 — Lógica de tickets y CRUD de clientes (comportamiento)

**Objetivo:** cambiar el flujo de creación/confirmación de tickets y ajustar el CRUD de clientes.

| # | Tarea |
|---|-------|
| 2.1 | En `IngresoController` y `CambioAceiteController`: reemplazar `updateOrCreate` por placa → cliente **solo con nombre** (criterio DNI si existe, si no crear nuevo). |
| 2.2 | En `procesarConfirmacion()` de ambos: invocar `AutomotorApiService`, *upsert* del automotor vinculado al cliente y asignar `automotor_id` al ticket. |
| 2.3 | Ajustar validación de `placa` (normalizar mayúsculas) y asegurar que es obligatoria en el ticket. |
| 2.4 | Modificar `ClienteController`: validaciones (nombre obligatorio, resto opcional, sin placa), `store/update`, `destroy` (quitar `ventas()` huérfana, agregar chequeo de automotores), `buscarPorPlaca` (por automotores). |
| 2.5 | Ajustar rutas de `web.php` (lookup por automotores; decidir resource automotores + permiso). |
| 2.6 | **Verificación Unidad 2:** flujo end-to-end de creación + confirmación de un ticket crea/vincula el automotor; CRUD de clientes funciona sin placa y sin romper por `ventas()`. |

### Unidad 3 — Vista, seeders y cobertura de tests (entrega)

**Objetivo:** UI, datos de ejemplo y garantía de calidad.

| # | Tarea |
|---|-------|
| 3.1 | Eliminar campo `placa` de `clientes/create` y `clientes/edit`; ajustar `validate.js`. |
| 3.2 | Actualizar `buscador-placa.js` y las vistas de `ingresos`/`cambio-aceite` para mostrar el automotor. |
| 3.3 | Refactorizar `ClienteSeeder` (sin placa); crear `AutomotorSeeder`; ajustar `IngresoSeeder`/`CambioAceiteSeeder` y `DatabaseSeeder`. |
| 3.4 | Escribir/ajustar **tests unitarios** (phpunit) para: `AutomotorApiService` (responses/fallback), upsert de automotor idempotente, vinculación 1:N, y validación del CRUD de clientes. |
| 3.5 | **Verificación Unidad 3:** `php artisan migrate:fresh --seed`, `php artisan test`, `npm run build` (Vite), `vendor/bin/pint` (formato). |

---

## Integración con la API REST

| Aspecto | Detalle |
|---------|---------|
| Estado | **Pendiente de implementar** (endpoint externo aún no existe). |
| Contrato esperado | Endpoint que dado una `placa` devuelva `{ marca, modelo, serie, color, motor }`. |
| Configuración | URL/token en `.env` (p. ej. `VEHICLE_API_URL`, `VEHICLE_API_KEY`) leídos por `AutomotorApiService`. |
| Fallback | Si la API falla o no encuentra el vehículo → crear automotor solo con placa, loguear error, **no bloquear** la confirmación del ticket (RNF-02). |
| Idempotencia | `upsert` por `placa` (RNF-04). |

> La feature queda **lista para conectar la API real**: se implementa el servicio con interfaz y fallback. Cuando la API exista, solo se configura el endpoint/cliente HTTP y se mapea la respuesta.

---

## Resultados esperados

### Datos

- Una placa identifica un **automotor** (PK/UNIQUE), no un cliente.
- Un cliente puede tener **1..N automotores**; un automotor pertenece a **1 cliente** (1:N verificado).
- `ingresos` y `cambio_aceites` registran qué automotor se atendió (`automotor_id`).
- `clientes` ya no contiene `placa`.

### Flujo

- Al **confirmar** un ticket (ingreso/cambio de aceite), la placa consulta la API REST y el automotor queda registrado y vinculado al cliente.
- El cliente se crea **solo con nombre** en el ticket (DNI opcional).
- El CRUD de clientes permite guardar clientes con solo nombre (sin placa, sin DNI obligatorio).

### Calidad

- Se **corrige el bug V1** (borrado de clientes que fallaba por la relación huérfana `ventas()`).
- El autocompletado por placa funciona vía automotores.
- Seeders coherentes con la nueva integridad 1:N.
- Tests unitarios verdes y build de Vite OK.

---

## Criterios de aceptación

1. La tabla `automotores` existe con `placa` char(7) `PK`/`UNIQUE` y los campos `marca, modelo, serie, color, motor`.
2. `clientes` no tiene columna `placa` y el formulario de cliente no la muestra; un cliente se puede guardar solo con nombre.
3. Al confirmar un ticket de ingreso o cambio de aceite con placa, se crea/actualiza el automotor y queda vinculado al cliente id; el ticket guarda `automotor_id`.
4. Un cliente puede acumular varios automotores; un automotor tiene un único `cliente_id`.
5. Si la API REST no responde, la confirmación no falla y el automotor se registra al menos con placa.
6. `ingresos.vehiculo_id` (catálogo de servicios) sigue funcionando para el precio.
7. `php artisan migrate:fresh --seed` y `php artisan test` pasan sin errores.

---

## Verificación

| Comando | Propósito |
|---------|-----------|
| `php artisan migrate:fresh --seed` | Validar esquema + seeders |
| `php artisan test` | Ejecutar suite de tests (incluye nuevos) |
| `vendor/bin/pint --test` | Formato de código |
| `npm run build` | Compilar assets (Vite) |
| `php artisan route:list` | Validar rutas nuevas/ajustadas |

> Comandos para el agente build. Debe validarse que `migrate:fresh` es seguro en el entorno de desarrollo actual (SQLite en `database/database.sqlite`).
