# Feature: Borrado Total de Usuarios con Preservación de Trazabilidad

## Índice

1. [Resumen ejecutivo](#resumen-ejecutivo)
2. [Estado actual del sistema](#estado-actual-del-sistema)
3. [Decisiones confirmadas por el usuario](#decisiones-confirmadas-por-el-usuario)
4. [Análisis de panoramas posibles](#análisis-de-panoramas-posibles)
5. [Diseño técnico](#diseño-técnico)
6. [Plan de implementación](#plan-de-implementación)
7. [Riesgos y decisiones pendientes](#riesgos-y-decisiones-pendientes)
8. [Resultados esperados](#resultados-esperados)
9. [Criterios de aceptación](#criterios-de-aceptación)
10. [Verificación](#verificación)

---

## Resumen ejecutivo

Se documenta el diseño para permitir el **borrado total de un usuario** (`users`) aunque este ya haya registrado operaciones en el sistema, **sin activar cascada** y **sin recurrir a las inactivaciones** (funcionalidad que ya existe con el toggle de `activo`).

El problema actual: varias tablas referencian `users.id` mediante **FK con `ON DELETE RESTRICT`**, por lo que `UserController::destroy` lanza un `QueryException` 500 cuando el usuario tiene registros asociados.

La solución elegida tras el análisis:

1. **Cuenta "Sistema" dedicada**: un usuario genérico (`sistema@sistema.com`) que recibe la reasignación de todos los `user_id` de los registros del usuario eliminado.
2. **Snapshot por tabla**: columnas `nombre_usuario` / `email_usuario` en las tablas de negocio (ventas, cambio de aceite, lavados) para que la atribución histórica ("quién hizo la venta") sobreviva al borrado, de forma inmutable en el propio registro.
3. Eliminación del usuario + limpieza de pivotes de spatie, sesiones y protección de la cuenta Sistema.

---

## Estado actual del sistema

### Referencias a `users` (causa del error de integridad)

7 columnas **NOT NULL con FK a `users`** (todas con comportamiento `restrict`/no-action):

| Tabla | Columna | Definición | Ubicación |
| --- | --- | --- | --- |
| `ventas` | `user_id` | `constrained('users')->onDelete('restrict')` | `2024_01_01_000011_create_ventas_table.php` |
| `cambio_aceites` | `user_id` | `foreign('user_id')->references('id')->on('users')` | `2026_05_01_220001_add_precio_total_descripcion_user_id_to_cambio_aceites_table.php` |
| `lavados` | `user_id` | `constrained('users')` (heredado de `ingresos`) | `2026_05_01_211415_add_precio_total_user_id_to_ingresos_table.php` + rename `2026_08_29_000002` |
| `cajas` | `user_id` | `constrained('users')->onDelete('restrict')` | `2026_05_10_000001_create_cajas_table.php` |
| `egresos_caja` | `user_id` | `constrained('users')->onDelete('restrict')` | `2026_05_10_000002_create_egresos_caja_table.php` |
| `movimientos_kardex` | `usuario_id` | `constrained('users')->onDelete('restrict')` | `2026_08_30_000001_create_movimientos_kardex_table.php` |
| `registros_auditoria` | `usuario_id` | `constrained('users')->onDelete('restrict')` | `2026_08_30_000002_create_registros_auditoria_table.php` |

**Referencias sin FK** (quedan huérfanas si solo se borra la fila de `users`):

| Tabla | Columna | Nota |
| --- | --- | --- |
| `model_has_roles` | `model_id` + `model_type` | Pivote de spatie/laravel-permission; sin FK a `users`, hay que limpiarlas manualmente |
| `model_has_permissions` | `model_id` + `model_type` | Idem |
| `sessions` | `user_id` | Nullable, sin FK; limpieza opcional |

### Comportamiento actual del borrado

- Ruta: `DELETE /users/{user}` → `UserController::destroy` (`app/Http/Controllers/UserController.php:106`).
- Única validación: no permitir **autoborrado** (`$user->id === auth()->id()`).
- Si el usuario tiene registros, `$user->delete()` falla por la FK restrict → **HTTP 500** sin mensaje amigable.

### Dónde se asigna `user_id` hoy (puntos donde se insertará el snapshot)

| Controlador | Línea | Contexto |
| --- | --- | --- |
| `VentaController::store` | ~113 | `'user_id' => auth()->id()` |
| `CambioAceiteController::store` | ~146 | `'user_id' => auth()->id()` |
| `LavadoController` (alta/confirmación) | ~255 | `'user_id' => auth()->id()` |
| `CajaController::abrir` / `CajaService::abrirCaja` | ~117 / 39 | `user_id` de la caja |
| `CajaService::registrarEgreso` | ~87 | `user_id` del egreso |
| `KardexService` | ~103 | `usuario_id` del movimiento |

---

## Decisiones confirmadas por el usuario

| Decisión | Elección |
| --- | --- |
| Destino de los registros del usuario eliminado | **Cuenta "Sistema" dedicada** (no admin existente) |
| Preservación de atribución histórica | **Snapshot por tabla (B1)**: `nombre_usuario` / `email_usuario` en los registros de negocio |
| Cascada en FK | **No** |
| Inactivación (`activo` / toggle) como solución | **No** (ya existe) |

---

## Análisis de panoramas posibles

### A. Reasignación a un usuario "sistema"

Actualizar en una sola transacción los 7 campos FK a una cuenta genérica y luego borrar la fila de `users` + pivotes.

- **Ventajas**: integridad total; transaccional; indestructible; portátil (MySQL / Postgres / SQLite); no pierde registros; patrón ya usado (backfills de migraciones con `user_id = 1`).
- **Desventajas**: se pierde la atribución original si el destino es el admin; una caja **abierta** del usuario pasaría al destino (edge case); reasignar `registros_auditoria` degrada la cadena de auditoría.

### B. Reasignación + preservación de atribución (ELEGIDA)

Igual que A, más conservar "quién lo hizo":

- **B1 — Snapshot por tabla** (elegido): columnas `nombre_usuario` / `email_usuario` en las tablas de negocio; se llenan al alta y en backfill. Los reportes muestran el autor real aunque `user_id` apunte a Sistema.
- **B2 — Tabla de archivo** `usuarios_eliminados` + JOIN en reportes. No elegido: más JOINs; menos columnas nuevas.
- **Ventajas**: integridad **y** trazabilidad; robusto y auditable.
- **Desventajas**: migración nueva + lógica de snapshot + ajuste de reportes.

### C. Cambiar FKs a `ON DELETE SET NULL` + columnas nullable

- **Ventajas**: la BD lo resuelve sin código.
- **Desventajas**: es un modo de cascada funcional; pierde la atribución (NULL); requiere migrar las 7 columnas a nullable (drop/recreate de FK en MySQL/Postgres). Descartada.

### D. Soft delete (`deleted_at`)

- **Ventajas**: cero riesgo de integridad; atribución intacta.
- **Desventajas**: funcionalmente igual al toggle `activo` actual (rechazado por requisito); el `email unique` sigue ocupado. Descartada.

### E. Borrado en cascada de los registros de negocio del usuario

- **Ventajas**: único camino para "cero rastro".
- **Desventajas**: destruye históricos contables, kardex y auditoría. Descartada (y prohibida por requisito de no-cascada).

### F. Desactivar temporalmente las FKs (`SET FOREIGN_KEY_CHECKS=0`)

- **Ventajas**: fuerza el borrado.
- **Desventajas**: deja huérfanos en 7 tablas + pivotes; rompe reportes y auditoría silenciosamente. **Antipatrón**, descartado.

### G. Triggers de BD que reasignen al borrar

- **Ventajas**: automatización a nivel BD.
- **Desventajas**: lógica duplicada entre SQLite / MySQL / Postgres; difícil de probar con PHPUnit (migraciones in-memory); sin ventaja real sobre A/B. Sobrediseño, descartado.

---

## Diseño técnico

### 1. Migraciones (2 nuevas)

**`add_es_sistema_to_users_table`**
- Columna `es_sistema` (`boolean`, `default false`) en `users`.
- `down()`: `dropColumn('es_sistema')`.

**`add_snapshot_usuario_to_business_tables`**
- Columnas `nombre_usuario` (`string`, nullable) y `email_usuario` (`string`, nullable) en `ventas`, `cambio_aceites`, `lavados`.
- **Backfill** de filas existentes con `JOIN users ON users.id = <tabla>.user_id` (mismo patrón que las migraciones históricas de `ingresos`/`cambio_aceites`).
- `down()`: `dropColumn(['nombre_usuario', 'email_usuario'])` en las 3 tablas.

### 2. Cuenta "Sistema"

- En `database/seeders/AuthSeeder.php` (idempotente, `firstOrCreate`):
  - `email` → `sistema@sistema.com`
  - `name` → `Sistema`
  - `activo` → `false` (bloqueada por `CheckUserActivo`, no puede iniciar sesión)
  - `es_sistema` → `true`
  - `password` → `Hash::make(Str::random(64))` (credenciales inutilizables)
  - Sin rol asignado (no necesita permisos; solo recibe registros).
- `UserController::index`: excluir al usuario `es_sistema` del listado (`where('es_sistema', false)`).
- `UserController::update`, `UserToggleController::toggle` y `UserController::destroy`: rechazar operación sobre `es_sistema` (mensaje "usuario de sistema, no se puede modificar").

### 3. Snapshot al crear registros (B1)

En los puntos donde hoy se asigna `'user_id' => auth()->id()`:

- `VentaController::store`
- `CambioAceiteController::store`
- `LavadoController` (alta / confirmación)

Añadir también:

```php
'nombre_usuario' => auth()->user()->name,
'email_usuario'  => auth()->user()->email,
```

Y agregar ambos campos a `$fillable` en los modelos `Venta`, `CambioAceite`, `Lavado`.

### 4. Servicio `app/Services/EliminacionUsuarioService.php`

Método principal `eliminar(User $user): void` con guardas y transacción:

**Guardas (antes de tocar BD):**
1. Autoborrado (`$user->id === auth()->id()`).
2. `$user->es_sistema` → rechazar.
3. Último `Administrador` activo → rechazar (no dejar sistema sin administradores).
4. Usuario con **caja abierta** (`Caja::abierta()->where('user_id', $user->id)`) → bloquear con mensaje "cierre su caja antes de eliminar".

**Transacción:**
1. `UPDATE` reasigna `user_id` → cuenta Sistema en:
   - `ventas`
   - `cambio_aceites`
   - `lavados`
   - `cajas`
   - `egresos_caja`
   - `movimientos_kardex` (`usuario_id`)
   - `registros_auditoria` (`usuario_id`)
2. Borra pivotes huérfanos de spatie: `model_has_roles` y `model_has_permissions` donde `model_type = App\Models\User`.
3. Borra `sessions` del `user_id`.
4. `$user->delete()` (el `AuditModelObserver` deja constancia con el administrador que ejecuta).

### 5. `UserController::destroy`

- Delegar en el servicio dentro de `try/catch`.
- Éxito → `redirect()->route('users.index')->with('success', 'Usuario eliminado correctamente.')`.
- Error de guarda/integridad → `redirect()->back()->with('error', <mensaje claro>)` (sin 500).
- Inyectar `EliminacionUsuarioService` por constructor (patrón actual de `ProductoController`).

---

## Plan de implementación

1. **Migración**: `es_sistema` en `users`.
2. **Migración**: snapshot (`nombre_usuario` / `email_usuario`) en `ventas`, `cambio_aceites`, `lavados` + backfill.
3. **Modelos**: `$fillable` ampliado en `Venta`, `CambioAceite`, `Lavado`; contante/accessor auxiliar si se desea.
4. **Seeder**: cuenta Sistema en `AuthSeeder` (idempotente) y en `DatabaseSeeder` order.
5. **Controladores**: snapshot al alta en `VentaController`, `CambioAceiteController`, `LavadoController`.
6. **Servicio nuevo**: `EliminacionUsuarioService`.
7. **`UserController`**: excluir Sistema del listado y proteger update/toggle/destroy + delegar borrado.
8. **Documentación spec**: `.kiro/specs/borrado-usuario/{requirements,design,tasks}.md` (workflow requirements-first del repo).
9. **Tests** (ver §10).

---

## Riesgos y decisiones pendientes

- **Cadena de auditoría**: reasignar `registros_auditoria.usuario_id` degrada la atribución del actor en el historial de acciones. Mitigación: el snapshot en las tablas de negocio conserva la trazabilidad de las operaciones; la auditoría de acciones conserva fecha/datos y pasa a "Sistema". Confirmar si es aceptable.
- **Caja abierta**: se bloquea la eliminación si el usuario tiene caja abierta. Alternativa futura: cerrarla automáticamente al destino.
- **Reportes**: los listados/tickets podrán mostrar `nombre_usuario` en vez de la relación `user`; decidir cuáles se actualizan.
- **Último Administrador**: la guarda protege el rol, pero no romper a `admin@sistema.com`; se mantiene como requisito.
- **Cuenta Sistema en API externa**: no afecta DNI/placa ni Cloudinary.

---

## Resultados esperados

- Un usuario con operaciones se puede **eliminar de forma definitiva** desde `/users` con mensajes claros y sin error 500.
- Ningún registro de negocio se pierde ni se rompe la integridad referencial.
- La atribución histórica ("quién hizo cada venta/cambio/lavado") sigue disponible vía snapshot.
- La cuenta Sistema es invisible en el panel, no puede iniciar sesión ni ser modificada/eliminada.
- No se tocan las FKs (sin cascada) ni se usa la inactivación como mecanismo de borrado.

---

## Criterios de aceptación

1. Borrar un usuario **sin registros** → eliminado completo de `users` + pivotes + sesiones.
2. Borrar un usuario **con ventas/cambios/lavados/cajas/kardex/auditoría** → todas las referencias reasignadas a Sistema; fila eliminada; pivotes limpios.
3. El snapshot `nombre_usuario` / `email_usuario` refleja al autor original en registros creados antes y después de la migración.
4. La cuenta Sistema no aparece en `users.index`, no puede iniciar sesión, y `update`/`toggle`/`destroy` la rechazan.
5. No se puede eliminar el último `Administrador` activo, la cuenta Sistema ni a uno mismo.
6. Con caja abierta del usuario, el borrado se bloquea con mensaje claro.
7. Ningún caso anterior produce HTTP 500.

---

## Verificación

- `vendor/bin/pint` (formato).
- `composer run test` — suite completa en verde (178 tests actuales + nuevos).
- Pruebas nuevas:
  - **`tests/Feature/EliminarUsuarioServiceTest.php`** (o similar):
    - borrado total con registros en las 7 tablas → reasignación a Sistema,
    - limpieza de pivotes y sesiones,
    - guardas: autoborrado, Sistema, último admin, caja abierta,
    - cuenta Sistema intacta.
  - **Test de propiedades** (invariante): para cualquier usuario con cantidades aleatorias de ventas/cambios/lavados/cajas/kardex, tras `eliminar()` → el registro no existe, todo `user_id` apuntan a Sistema y ninguna FK se rompe.
  - Tests de controllers: destroy con y sin registros; índice sin el usuario Sistema.