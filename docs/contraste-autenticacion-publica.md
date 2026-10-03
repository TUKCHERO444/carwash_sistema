# Contraste: autenticación pública propuesta vs estado real del sistema

> Solo análisis. No se implementó nada. Documento para decidir alcance real sin romper lo actual.
> Fecha: 2026-10-02. Stack: Laravel 12 / PHP 8.2 / Blade + Tailwind 4 / SQLite dev / session driver `database`.

Propuesta contrastada: `resumen_requerimientos_arquitectura_autenticacion_publica.md` (§1-13, IAM con `auth_users`, credenciales separadas, identidades externas, `auth_sessions` opacas stateful, `customer_account_links/memberships`, `auth_one_time_tokens`, `auth_audit_events`, guards `public/customer` vs `admin`, cookies separadas, Google OIDC Code+PKCE).

## 1. Mapa del estado actual (evidencia)

### 1.1 Identidad y Cliente

| Entidad | Archivo | Estado real |
|---|---|---|
| `User` | `app/Models/User.php:12-27,34-51` | `Authenticatable`, `HasRoles`, fillable `name,email,password,activo`. casts `password hashed`. Sin `MustVerifyEmail` (comentado), sin `HasApiTokens`, sin FK a cliente. |
| `users` tabla | `database/migrations/0001_01_01_000000_create_users_table.php:14-22` + `2026_05_03_144109_add_activo_to_users_table.php` | PK `bigint autoincrement`, `email unique`, `password`, `rememberToken`. + `activo boolean`. |
| `Cliente` | `app/Models/Cliente.php:9-19,31-63` | `Model` plano, **no autenticable**. fillable `dni,nombre,apellido_paterno,apellido_materno,telefono`. Sin `email/password`. Relaciones `hasMany Automotor/Lavado/CambioAceite`. |
| `clientes` tabla | `2024_01_01_000001_create_clientes_table.php` + evoluciones `2026_05_01_211035`, `2026_05_05_002656`, `2026_08_22_000004`, `2026_08_28_000003` | PK `bigint`. Hoy: `dni(8,nullable,sin unique)`, `nombre(50,nullable)`, apellidos nullable, `telefono(20,nullable)`. `placa` eliminada. Sin email/password/FK a users. |
| `Automotor` | `2026_08_28_000001_create_automotores_table.php:15-16`, `app/Models/Automotor.php:15-19` | PK `placa string(7)`, `cliente_id FK -> clientes.id cascade`. |
| `Venta` | `app/Models/Venta.php:12-23` | Solo `user_id,caja_id`. Sin `cliente_id` (venta mostrador anónima). |
| Cardinalidad cuenta-cliente | — | **0. No existe vínculo.** Sin FK ni pivote `users <-> clientes`. Cliente nunca se autentica. |

Conclusión §1 propuesta: `Cliente` es **persona operativa que deja el vehículo** (datos comerciales: DNI, nombre, teléfono, vehículos/placas, lavados, cambios de aceite), no persona-cuenta. Separar IAM de Cliente es correcto y obligatorio aquí.

### 1.2 Autenticación, guards, sesiones

| Aspecto | Evidencia | Estado |
|---|---|---|
| Guard | `config/auth.php:18-21,40-45,64-68` único `web => session + provider users (User::class)` | Solo `web`. No existe `public/customer/admin`. |
| Password broker | `config/auth.php:95-101` `users => password_reset_tokens, expire 60, throttle 60` | Tabla existe pero **sin rutas que lo usen**. |
| Sesión | `config/session.php:21,35,89,130-133,172,185,202` driver `database`, tabla `sessions`, lifetime `120`, cookie `SESSION_COOKIE`, `secure=null`, `http_only=true`, `same_site=lax` | Sesión nativa Laravel. Tabla `sessions: id string PK, user_id nullable index (sin FK), payload, last_activity index` (`0001_01_01_000000:30-37`). No hay `auth_sessions` custom, ni `token_hash`, ni `idle/absolute_expires`, ni `revoked_at`. |
| Login/logout | `app/Http/Controllers/Auth/LoginController.php:38-59,71-84`, `routes/web.php:89-97` | `Auth::attempt` guard `web` + `session()->regenerate()`, check `activo`, `AuditService::registrarSesion`. Logout `Auth::logout + invalidate + regenerateToken`. Sin registro, sin reset, sin verify. |
| Rutas | `routes/web.php:32-86` públicas landing; `:89-92 guest /login`; `:95-97 POST /logout (auth)`; `:100-102 /dashboard (auth)`; `:105+ auth+permission:*` | Sin `/cuenta/*`, sin `/api/public/*`, sin `/api/*` (no hay `routes/api.php`), sin `register/password/reset/verify/social/google`. Sin prefijo `/admin/*` (panel es `/dashboard` + recursos planos). |
| Frontend auth | `resources/views/auth/login.blade.php` + `layouts/auth.blade.php` | Solo login operadores (`email/password` + `@csrf`). Sin `register.blade.php`, sin cuenta-cliente. `localStorage` solo para `theme` (`partials/theme-script.blade.php:3,28,31`). Sin JWT. CSRF vía `@csrf`. |
| Cookies separadas | — | No existen `public_session/admin_session`. Una sola cookie de sesión. |
| OAuth/Google | `composer.json:8-15`, `config/services.php:1-48`, `.env.example:67-72` | **Nada**: sin `sanctum/socialite/jetstream/breeze/fortify/passport/google/apiclient`. `services.php` solo `vehicle_api/dni_api`. Sin `GOOGLE_CLIENT_ID/SECRET/REDIRECT`. |

### 1.3 Autorización y auditoría

| Aspecto | Evidencia | Estado |
|---|---|---|
| RBAC | `database/seeders/PermissionSeeder.php:15-51` 17 permisos `guard web`; `AuthSeeder.php:23-46` roles `Administrador (all)` / `Vendedor (ventas,caja,clientes,vehiculos,automotores)` + demo `admin@sistema.com/password`; `AppServiceProvider.php:31-33` `Gate::before Administrador => true` | Solo roles operadores. Sin rol `Cliente`, sin permisos `acceso-cuenta`, sin MFA. |
| Ownership | grep `authorize|can:|Auth::id` en `app/` = 0 salvo `auth()->id()` auditoría; sin `app/Policies/*`; `ClienteController` solo `permission:acceso-clientes` | **Sin autorización por recurso.** Cualquier `auth` entra a `/dashboard` (`LoginTest.php:232-239`). IDOR/BOLA no cubierto. |
| Auditoría | `AuditServiceProvider.php:44-65` 20 modelos + `AuditService.php:39-81,181-212`, tabla `registros_auditoria` (`2026_08_30_000002`, `auditable_id` pasado a string por PK placa) | Audita CRUD + `registrarSesion(user, 'inicio/cierre de sesión', modulo='sesiones')`. Sensibles excluidos `password,remember_token`. No hay `auth_audit_events` (fallidos, revocación, recuperación, vinculación Google, anomalías). Tabla `sessions` no se audita. |
| Rate limiting / bloqueo | `LoginController` | Sin throttle ni `failed_attempts/locked_until`. |
| Hash password | `User.php:44-51` `password hashed` (bcrypt default) | Sin Argon2id explícito. |
| Web pública | `resources/views/publica/*` + `layouts/publica.blade.php`, `vite.config.js:12-14` | Landing informativa + placeholders (`href="#"`, `Futuro: carrito/búsqueda/reserva/boletín`). Sin sesión cliente. |

## 2. Matriz de brechas (propuesta §2-9 vs real)

Estado: `Existe / Parcial / No existe`.

| Requisito propuesta | Estado | Evidencia / riesgo |
|---|---|---|
| §1 IAM separado, `auth_users.id` estable raíz | **No existe** | Solo `users` (operadores) + `clientes` (negocio) sin vínculo. Crear `auth_users` UUID como propone rompería convención `bigint` y duplicaría `users`. Alcance real: reutilizar/extender `users` o crear `customer_users` `bigint`, no `auth_users` UUID desde cero. |
| §4.1 `auth_users` (status, verified) | **Parcial** | `users` tiene `email unique, email_verified_at, activo, timestamps`. Falta `status (PENDING/SUSPENDED/DISABLED)`. No crear tabla nueva sin decidir destino de `users` actuales. |
| §4.2 `auth_password_credentials` 1:1 | **No existe** | `password` vive en `users`. Separarlo exige migración con backfill + doble escritura; beneficio bajo para este tamaño. Alcance real: mantener `password` en `users` + columnas `password_changed_at/failed_attempts/locked_until` si se quiere bloqueo. |
| §4.3 `auth_external_identities` UNIQUE(provider,subject) | **No existe** | Sin tabla, sin Socialite, sin `provider/sub`. Diseño válido a futuro; hoy 0 dependencias. No vincular por email (propuesta §8 lo prohíbe, correcto). |
| §4.4 `auth_sessions` stateful opacas | **No existe (y duplica)** | Existe `sessions` nativa Laravel (payload opaco, `last_activity`, `user_id`). Crear `auth_sessions` paralela con `token_hash/guard/audience/idle/absolute/revoked` implica abandonar `Auth::attempt/session()->regenerate()` o mantener dos fuentes de verdad. Alcance real: extender nativa (eventos, limpieza, `Auth::logoutOtherDevices`, timeout por guard) antes de tabla custom. |
| §4.5 `customer_account_links / memberships` | **No existe** | Sin FK/pivote. Además `clientes` no tiene `email` único (dni nullable sin unique), no puede ser clave de cuenta. Requiere definir cardinalidad (ver §5) + backfill manual. |
| §4.6 `auth_one_time_tokens` | **No existe** | Existe `password_reset_tokens (email PK, token, created_at)` sin uso. Propuesta exige `token_hash/purpose/expires/consumed`. Migración aditiva posible, pero hoy sin rutas que lo consuman. |
| §4.7 `auth_audit_events` inmutable | **Parcial** | `registros_auditoria` cubre login/logout exitosos + CRUD. Falta: fallidos, revocación, recuperación, vinculación Google, anomalías, reautenticación. Extender `AuditService::registrarSesion` + `SENSIBLES`, no tabla nueva al inicio. |
| §6 guards `public/customer` vs `admin`, cookies `public_session/admin_session`, audiencias | **No existe** | Un guard `web`, una cookie, sin `/admin/*` ni `/cuenta/*`. Separar cookies host-only en Laravel monolito = 2 session drivers/dominios o auth custom; costo alto, rompe `permission:*` actuales (`guard web`). Alcance real: mismo guard `web` + rol `Cliente` + middleware `cliente`/`admin` y prefijos de ruta, no guards/cookies separadas en fase 1. |
| §6 autorización: ownership/membresía vs RBAC admin | **No existe (lado público)** / **Parcial (admin)** | Admin: `permission:*` + bypass Administrador. Público: nada. Requiere Policies (`ClientePolicy`, `AutomotorPolicy`) + scoping por `customer_id/membresía`. |
| §6 CSRF/CORS/Origin, rotación tras login/elevación | **Parcial** | `@csrf` + `regenerate()` en login / `regenerateToken()` en logout existen. Falta rotación en elevación/vinculación/cambio sensible, validación `Origin/Referer` estricta, `SESSION_SECURE_COOKIE` en prod (hoy `null`). |
| §6 expiración idle + absoluta diferenciada, logout actual + global | **Parcial** | `lifetime 120` global. Sin idle vs absoluta, sin timeout menor admin, sin revocación global. `Auth::logoutOtherDevices` no usado. |
| §7 registro/verify/login/recuperación, Argon2id, anti-enumeración, throttle/bloqueo | **No existe** | Sin rutas/controladores/mails. `LoginController` revela `email inexistente vs password incorrecta` (tests `LoginTest.php:119-195` lo asertan) — cambiarlo rompe tests actuales. Decidir política antes. |
| §8 Google OIDC Code+PKCE, state/nonce, validación JWT, alta/vinculación | **No existe** | Sin librería, sin `client_id/secret/redirect URIs`, sin `state/nonce/code_verifier`. Requiere `laravel/socialite` (+ driver OIDC) o `thenetworg/oauth2-azure`-like para Google; `Socialite` solo OAuth2, validación `id_token` debe añadirse (`firebase/php-jwt` o `google/apiclient`). Política email-coincidente pendiente (propuesta: no auto-vincular). |
| §9 HTTPS/HSTS, rate limiting, no-secretos, MFA admin, reautenticación, retención/limpieza, IDOR tests | **Parcial / No existe** | HTTPS/HSTS fuera del repo (infra). Rate limiting solo throttle default passwords sin uso. `SENSIBLES` ok. Sin MFA, sin reautenticación, sin `schedule:prune` sesiones/tokens, sin tests IDOR/expiración/CSRF/ownership. |
| Convenciones BD (UUID/ULID, CITEXT, JSONB, tz) | **N/A** | SQLite/MySQL-compat: PK `bigint`, `json nullable` en auditoría, `timestamp fecha_movimiento`, timezone app `America/Lima` (`config/app.php`, `now()`/Carbon). Propuesta UUID/JSONB/CITEXT es Postgres-céntrica; mapear a `bigint + collation/email normalizado en DataNormalizer + json + timestamps Lima`. |

## 3. Alcance real sin romper lo actual (qué sí / qué no)

### 3.1 Lo que NO se debe hacer tal cual propone el doc

1. **No crear `auth_users` UUID como tabla paralela a `users` en fase 1.** Duplica identidad (operadores `admin@sistema.com` quedarían huérfanos), invalida `spatie guard web`, `Gate::before`, 17 permisos, seeders, `registros_auditoria.usuario_id FK -> users`, y `CajaService`/ventas (`user_id`). Si a futuro se quiere IAM puro, es migración con backfill y cambio de `AUTH_MODEL`, no aditiva.
2. **No crear `auth_sessions` custom que sustituya `sessions` de Laravel.** Se pierde `Auth::attempt`, `session()->regenerate()`, `remember_token`, `database` driver, tests `LoginTest/SesionesTest`. La revocación/expiración se logra con nativa + `logoutOtherDevices` + listener `Login/Logout` + `last_activity`.
3. **No separar `public_session/admin_session` (cookies host-only distintas) en monolito.** Laravel usa una `SESSION_COOKIE`; dos sesiones paralelas requieren doble driver/dominio o `Set-Cookie` manual + `Auth::viaRequest`. Rompe `auth` middleware actual. Aislamiento real fase 1 = prefijos de ruta + middleware + rol, no cookies distintas.
4. **No renombrar a `/admin/*` ni mover `/dashboard`.** Todas las rutas protegidas, `@can`, tests y `LoginController redirect /dashboard` dependen de URLs actuales. Los prefijos nuevos (`/cuenta/*`) son aditivos.
5. **No incrustar `email/password` en `clientes`.** `clientes.dni` es nullable no-unique y no hay email; mezclar credenciales en entidad comercial viola §1 y `DataNormalizer`. Vínculo vía tabla pivote, no columnas auth en `clientes`.
6. **No imponer Argon2id / CITEXT / UUID / JSONB ya.** `password hashed` (bcrypt) + `DataNormalizer` + `bigint` + `json` son la convención; cambiar driver de hash invalida logins o exige rehash coordinado.

### 3.2 Modelo de datos final adaptado (aditivo, `bigint`, Lima)

Mantener `users` como identidad. Añadir solo lo necesario, todo nullable/aditivo:

- `users`: añadir `status varchar(30) default 'ACTIVE'` (mapea `PENDING_EMAIL_VERIFICATION/SUSPENDED/DISABLED` + `activo` legacy), `password_changed_at nullable`, `failed_attempts int default 0`, `locked_until nullable`. Activar `MustVerifyEmail` + `email_verified_at` ya existente. Índice `status`.
- `customer_user_links` (nombre real, no `customer_account_links` genérico): `id bigint PK`, `user_id FK -> users.id cascade unique` (si regla 1:1) o `unique(user_id,cliente_id)` (si N:M futuro), `cliente_id FK -> clientes.id cascade`, `estado`, `rol/alcance nullable`, timestamps. Decisión 1:1 vs membresía bloquea esta migración (ver §5).
- `social_identities` (en vez de `auth_external_identities`): `id bigint PK`, `user_id FK cascade`, `provider varchar(20)`, `provider_subject varchar(255)`, `provider_email nullable`, `provider_email_verified bool nullable`, `linked_at`, `last_login_at`, `UNIQUE(provider,provider_subject)`, índice `user_id`. Solo cuando se apruebe Google.
- Reutilizar `password_reset_tokens` para recuperación; si se necesita verificación/cambio-email, crear `one_time_tokens: id bigint, user_id FK, purpose, token_hash unique, expires_at, consumed_at nullable, created_at`, con limpieza programada. No guardar secretos en claro.
- Sesiones: **no nueva tabla**. Añadir auditoría (`registrarSesion` extendido: fallidos, revoke global, cambio password) + `php artisan session:prune-expired` (o `auth:clear-resets`) en scheduler. `auth_audit_events` solo si `registros_auditoria` se queda corta (hoy `modulo/accionauditable_type/id/datos json/usuario_id/fecha` basta).
- Índices: `users(email,status)`, `social_identities(provider,provider_subject)`, `customer_user_links(user_id,cliente_id)`, `one_time_tokens(token_hash,expires_at)`.

### 3.3 Guards y autorización (sin romper `web`)

- **Fase 1-2:** seguir con guard `web`. Nuevas rutas `GET/POST /cuenta/registro, /cuenta/login, POST /cuenta/logout, /cuenta/verificacion/*, /cuenta/recuperacion/*` con `middleware guest/customer` propio (rol `Cliente`), y grupo `auth + can:acceso-cuenta` para `/cuenta/*` privadas. Admin sigue `auth + permission:*`. Prohibir que rol `Cliente` tenga permisos `acceso-*` y que `permission:*` autorice `/cuenta/*` (tests de separación).
- **Fase 3+:** si se exige aislamiento fuerte, evaluar segundo guard `customer` con mismo provider `users` + `SESSION_COOKIE` distinta por subdominio (`cuenta.dominio` vs `admin.dominio`) o `Sanctum` para `/api/*`. No hacerlo en monolito single-domain.
- Autorización pública por **ownership**: `CustomerUserLinkPolicy` + `AutomotorPolicy/LavadoPolicy` (`user->clientes` vs recurso `cliente_id`). Admin mantiene RBAC + `Gate::before`. Añadir tests IDOR (cliente A no ve `/cuenta/vehiculos/{placa de B}`).
- Sesión/cookies: mantener `HttpOnly, SameSite=lax, Secure=true` en prod (`SESSION_SECURE_COOKIE=true`), `Path=/`, sin `Domain` compartido. Rotar en login/logout ya; añadir en cambio password/vinculación Google. CSRF `@csrf` en todo POST `/cuenta/*`.

### 3.4 Flujos (textual, adaptado a Laravel)

- **Registro local:** `POST /cuenta/registro (guest, throttle)` → validate (`name,email unique, password confirmed`, `DataNormalizer` email) → `DB::transaction: users(status=PENDING, password Hash) + customer_user_links(user,cliente nuevo o existente por DNI/telefono?) + one_time_token(purpose=verify)` → mail verificación → `AuditService (modulo=cuenta, accion=registro)`. Login bloqueado hasta `email_verified_at`.
- **Verificación:** `GET /cuenta/verificacion/{hash} (signed)` → consume token (`consumed_at`, expira) → `email_verified_at=now(), status=ACTIVE`.
- **Login local:** `POST /cuenta/login (guest, throttle 5/min)` → check `status/activo/locked_until` → `Auth::attempt + regenerate` → `failed_attempts=0 / last_login` → sesión `web` con `auth_method=password, auth_time`. Fallo: `failed_attempts++`, `locked_until` progresivo, respuesta genérica anti-enumeración (cambia tests actuales — decisión pendiente).
- **Recuperación:** `POST /cuenta/recuperacion (throttle)` → siempre `200` genérico → si email existe, `password_reset_tokens/one_time_tokens(purpose=recovery, corta vida)` + mail → `POST /cuenta/recuperacion/{token}` → `Hash::make` + `password_changed_at=now()` + `Auth::logoutOtherDevices` + revocar otras sesiones + auditoría.
- **Login Google (solo fase 3):** `GET /cuenta/auth/google/redirect` → guarda `state/nonce/code_verifier` en sesión/caché corta → redirect `openid email profile` Code+PKCE → `GET /cuenta/auth/google/callback` → valida `state`, canje con `code_verifier`, valida `id_token (iss,aud,exp,nonce,firma)` → busca `social_identities(provider=google, subject=sub)` → hit: login; miss: si `provider_email` coincide con `users.email` → **no auto-vincular**, exigir login local previo o token de vinculación; si no existe → crear `users + link + social_identities` bajo política de alta. Auditoría + notificación. Nunca quitar último método activo.
- **Logout:** `POST /cuenta/logout (auth)` → `registrarSesion(cierre)` → `Auth::logout + invalidate + regenerateToken`. **Logout global:** `POST /cuenta/sesiones/revocar-todas` → `logoutOtherDevices + delete sessions where user_id` + auditoría.

## 4. Plan incremental (dependencias, pruebas, aceptación)

| Fase | Contenido | Depende de | Pruebas exigidas | Criterio aceptación |
|---|---|---|---|---|
| 0 Decisiones | Resolver §5 (1:1 vs N:M, email-coincidente, duraciones sesión, alta Google, migración clientes existentes, anti-enumeración) | — | — | Actas firmadas; sin código hasta cerrar 1:1 vs N:M |
| 1 Base cuenta local | `users.status/failed/locked` + `customer_user_links` + rol `Cliente` + `PermissionSeeder(acceso-cuenta)` + rutas `/cuenta/registro|login|logout` + `throttle` + `DataNormalizer` + auditoría extendida | Fase 0 | Feature: registro/login/logout, throttle, `Cliente` no accede a `permission:*`, admin no accede a `/cuenta/*` privada ajena | Visitante crea cuenta, inicia/cierra sesión pública; sesión admin no abre `/cuenta/*` y viceversa |
| 2 Verificación + recuperación | `MustVerifyEmail` + mails + `one_time_tokens` + `password_reset_tokens` en uso + `logoutOtherDevices` + scheduler limpieza | Fase 1 | Property 100 iter: tokens un solo uso, expiración, consumo doble falla, cambio password revoca otras sesiones | Cuenta no verificada no entra; recuperación de vida corta revoca sesiones viejas |
| 3 Google OIDC | `composer require laravel/socialite + firebase/php-jwt (o google/apiclient)` + `social_identities` + `config/services.php google` + secretos por entorno + `redirect URIs` exactas + validación `iss/aud/exp/nonce` + política no-auto-vínculo | Fases 1-2 | Mock OIDC: callback válido/inválido (`state`, `nonce`, `exp`, `aud`), `provider+sub` vincula, email-coincidente no secuestra | Login Google Code+PKCE vincula por `provider+sub`; email igual sin prueba no vincula |
| 4 Autorización + sesiones | Policies + scoping `cliente_id` + `failed/locked` + idle/absoluta (middleware `last_seen`) + revocación global + `SESSION_SECURE_COOKIE=true` prod | Fases 1-2 | IDOR/BOLA por objeto, expiración idle/absoluta, cuenta suspendida, CSRF en `POST /cuenta/*` | Sesión válida no abre datos de otro cliente; revocada/expirada se rechaza aunque token sea correcto |
| 5 Endurecimiento admin | MFA admin (TOTP o IdP corporativo), reautenticación acciones críticas, rate limiting callback/verificación, métricas (login ok/fail, callback fail, sesiones activas, bloqueos), HSTS/HTTPS prod | Fases 1-4 | e2e MFA, reauth, concurrencia, rollback migraciones | Rutas `permission:*` rechazan sesión pública aunque sea misma `user_id`; MFA exigible a `Administrador` |

Despliegue: migraciones aditivas + idempotentes (`firstOrCreate` permisos, `nullable` columnas), `migrate:fresh --seed` en dev, `data:clean` si se normalizan emails/DNI, rollback por fase (dropear `social_identities/one_time_tokens` no afecta `users/clientes`).

## 5. Supuestos y decisiones pendientes (bloquean §4.5 y §8)

1. ¿`Cliente` es persona, organización o ambos? Hoy persona sin email. ¿Puede un cliente tener **varios usuarios** (familia/empresa) o un usuario **varias fichas cliente** (duplicados por `dni` nullable no-unique)? → define `UNIQUE(user_id)` vs `UNIQUE(user_id,cliente_id)`.
2. ¿Una persona puede ser **cliente + administrativo** (dos filas `users` o una con dos roles)? Propuesta lo permite vía vínculo; hoy `users` 1 fila por email. Decidir: ¿rol dual o cuentas separadas?
3. ¿Admin seguirá en mismo `users/guard web` o IdP corporativo separado? Propuesta sugiere IdP separado alto riesgo; costo alto.
4. Vinculación email-coincidente Google↔local: ¿auto-vincular si `provider_email_verified=true`, exigir password local, o token manual? (propuesta: no auto-vincular).
5. Duraciones: ¿idle/absoluta pública vs admin? (hoy `120` global). ¿Recuperación corta cuánto? ¿Verificación cuánto?
6. Recuperación cuentas solo-Google: ¿permitir crear password después o solo re-vincular Google?
7. Migración clientes existentes (miles sin email): ¿alta asistida en mostrador (Vendedor crea link), auto-registro con match DNI+teléfono+placa, o sin migración (solo cuentas nuevas)? Riesgo duplicados por `dni` no-unique.
8. Anti-enumeración: ¿respuestas genéricas (rompe `LoginTest:119-195` actuales) o mensajes específicos actuales?
9. Regulatorio/retención/residencia (Perú): ¿plazo borrado `social_identities/one_time_tokens/sessions`, anonimización `clientes` sin romper historial ventas/lavados?
10. Email transaccional: `services.php` solo `postmark/resend/ses/slack` ¿cuál envía verificación/recuperación? ¿plantillas Blade?

## 6. Criterios de aceptación mínimos (mapeados)

| Criterio propuesta §12 | Veredicto hoy | Qué falta para cumplirlo |
|---|---|---|
| Registro→verificación→login/logout público | No existe | Fases 1-2 |
| Google OIDC Code+PKCE por `provider+sub` | No existe | Fase 3 |
| Sin secuestro por email coincidente | No aplica (sin Google) | Decisión §5.4 + tests |
| Admin rechaza sesión pública misma identidad | No existe (una identidad) | Fase 1 (rol `Cliente` sin `permission:*`) + Fase 5 (MFA/guard separado si se exige) |
| Ownership/membresía, no solo sesión | No existe | Fase 4 Policies |
| Sesiones revocables, secreto no en claro, cookies seguras | Parcial (nativa + `HttpOnly/Lax`, sin revocación global ni `Secure` prod) | Fases 2+4 (`logoutOtherDevices`, `Secure=true`) |
| Cambio/deshabilitación/revocación invalida sesiones | No existe | Fases 2+4 |
| Tests rutas críticas, horizontal, expiración, OIDC | Parcial (solo login admin + auditoría) | Cada fase suma Feature + Property (100 iter `fast-check`/Faker) |
| Auditoría sin secretos | Parcial | Extender `registrarSesion`, nunca loggear `password/token/code/id_token` |

---
*Fuentes: `config/auth.php`, `config/session.php`, `config/services.php`, `app/Models/User.php`, `app/Models/Cliente.php`, `routes/web.php`, `Auth/LoginController.php`, `PermissionSeeder.php`, `AuthSeeder.php`, `AuditService/Provider.php`, migraciones `users/clientes/sessions/password_reset_tokens/registros_auditoria`, `resources/views/publica/*`, `auth/login.blade.php`, `composer.json`, `.env.example`, `tests/Feature/Auth/LoginTest.php`, `LoginPropertyTest.php`, `Auditoria/SesionesTest.php`.*
