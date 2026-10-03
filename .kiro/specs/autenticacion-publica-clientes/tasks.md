# Plan de Implementación — Autenticación pública de clientes (`/cuenta/*`)

## Visión General

Implementación aditiva por fases sin romper el panel operativo: base cuenta local → verificación/recuperación → zona privada con ownership → Google OIDC → endurecimiento. Todo sobre guard `web` + `users` (bigint) + `sessions` nativa + rol `Cliente` + pivote `customer_user_links`. Nada de `auth_users/auth_sessions`/cookies separadas/renombrar `/dashboard`.

Convenciones obligatorias: `America/Lima` (`now()`/Carbon), `DataNormalizer` en vez de regex duplicados, rutas estáticas antes que resource, cada vista con su `resources/js/cuenta/*.js` en `vite.config.js input` + `@vive` al final de `@section('content')` sin inline salvo `window.*=@json`, clases dark `.bg-surface/.input-main/...`, `paginate(10)`, `overflow-x-auto`, `composer run test` + `npm run test` cuando toque JS, `vendor/bin/pint`, permisos con `Permission::firstOrCreate + givePermissionTo` en tests, mocks de APIs/Cloudinary/OIDC (nunca red real).

> No empezar código hasta cerrar D1–D9 de `requirements.md` (mínimo D1 cardinalidad, D4 duraciones/vidas, D8 mailer).

## Tareas

- [x] 0. Decisiones y preparación (bloqueante)
  - Acta 2026-10-03 (implementado): D1 = 1:1 (`UNIQUE(user_id)` en `customer_user_links`); D2 = cuentas separadas en la práctica, dual permitido (cada zona exige lo suyo: rol `Cliente` vs `permission:acceso-*`); D3 = no auto-vincular Google por email (fase 11, pendiente); D4 = verificación 24h, recuperación 60min, bloqueo 5 fallos → 15min, sesión nativa `SESSION_LIFETIME`; D5 = pendiente (solo hay cuentas locales); D6 = sin migración de legados (solo cuentas nuevas con ficha nueva); D7 = **no** se toca el login operativo (mensajes de `LoginTest` intactos); D8 = mailer `log` dev / `array` tests + Mailables propios; D9 = limpieza programada diaria (tokens + sesiones expiradas), retención legal pendiente.
  - _Requisitos: todos (D1–D9)_

- [x] 1. Migraciones + modelos base (aditivos)
  - Crear `xxxx_add_cuenta_fields_to_users_table` (`status default ACTIVE`, `password_changed_at/failed_attempts default 0/locked_until/last_login_at nullable`, índice `status`; backfill `activo=false→SUSPENDED`).
  - Crear `xxxx_create_customer_user_links_table` (`user_id FK cascade UNIQUE` —o compuesta si D1=N:M—, `cliente_id FK cascade`, `estado default ACTIVO`, índices).
  - Crear `xxxx_create_one_time_tokens_table` (`purpose, token_hash unique, expires_at, consumed_at`, índice).
  - Modelos `CustomerUserLink/OneTimeToken` + relaciones en `User/Cliente`; `User` activa `MustVerifyEmail` (verificado que `email_verified_at` ya existe).
  - Registrar en `AuditServiceProvider::$modelos` + `AuditService::AUDITABLE` (respetando `SENSIBLES/EXCLUIDOS`).
  - _Requisitos: 1.3, 2, 4, 5, 9.4_
  - Nota: no se registraron modelos nuevos en auditoría (pivote/tokens efímeros, según `EXCLUIDOS`); se amplió `AuditService::registrar/registrarSesion` con `$actorId` opcional para flujos guest (retrocompatible, sin firmas rotas).

- [x] 2. RBAC + middleware zona cuenta
  - `PermissionSeeder`: `firstOrCreate('acceso-cuenta','web')`; `AuthSeeder`/seeder: rol `Cliente` (`guard web`) sin permisos; asignar en registro.
  - Crear `app/Http/Middleware/EnsureCliente.php` (`auth + hasRole Cliente + status ACTIVE + activo + verified`); registrar alias `cliente` en `bootstrap/app.php`; grupo `/cuenta/*` privada `['auth','cliente','can:acceso-cuenta']`.
  - Tests: solo-`Cliente` → 403 en `/dashboard` y rutas `permission:*`; operador sin vínculo → 403 en `/cuenta/*` privada.
  - _Requisitos: 1.6, 6_
  - Nota: el rol `Cliente` lleva únicamente `acceso-cuenta` (puerta, no operativo); guest en `/cuenta/*` va a `cuenta.login` vía handler `AuthenticationException` en `bootstrap/app.php` (el middleware `auth` tiene prioridad de ejecución). Ajuste 2026-10-03 tras reporte: solo-`Cliente` en `/dashboard` o `/login` operativo redirige a `cuenta.panel` con aviso (en vez del 403 original) para no dejar callejón sin salida con la sesión compartida `web`; las rutas `permission:*` sí mantienen 403.

- [x] 3. Registro público
  - `Cuenta\RegistroController@create/store` + `POST /cuenta/registro` (`guest`, `throttle:6,1`); validación ES + `DataNormalizer`; `DB::transaction` (users PENDING + clientes/link + token verificación hash + rol Cliente); mail verificación; auditoría `cuenta/registro`.
  - Vistas `layouts/cuenta + cuenta/registro.blade.php` + `resources/js/cuenta/registro.js` (input en `vite.config.js`, `@vite` al final, dark tokens, `@csrf`, `@error` por campo).
  - _Requisitos: 1_
  - Nota: vistas extienden `layouts/publica`, sin JS (sin entrada Vite necesaria).

  - [x] 3.1 Tests ejemplo registro (campos, creación transaccional, duplicado 422, throttle, `activo`/caja no bloquea).
    - _Requisitos: 1_

- [x] 4. Verificación de email
  - `Cuenta\VerificacionController@verify/reenviar` (signed URL o token hash, `throttle`); consumo un solo uso (`consumed_at`, expira); `email_verified_at=now(), status=ACTIVE`; auditoría.
  - Vista `cuenta/verificacion*.blade.php` + reenvío.
  - _Requisitos: 2_

- [x] 5. Login/logout públicos + bloqueo
  - `Cuenta\LoginController` (nombre no colisiona con `Auth\LoginController`) + `GET/POST /cuenta/login` (`guest`, `throttle:5,1`), `POST /cuenta/logout` (`auth`); checks `status/activo/verified/locked_until`; `Auth::guard('web')->attempt + regenerate`; `failed_attempts/locked_until` progresivo; mensajes genéricos anti-enumeración; auditoría ok+fallido sin secretos.
  - Vistas `cuenta/login.blade.php` + `resources/js/cuenta/login.js` (vite + dark + `@csrf`).
  - **Aviso:** si se unifica mensaje genérico también en `/login` operativo, actualizar `tests/Feature/Auth/LoginTest.php:119-195` (decisión D7).
  - _Requisitos: 3, 4.1, 4.3, 9.1–9.3_
  - Nota: D7 = no unificar; `/login` operativo intacto (sin middleware `guest` en cuenta: redirección manual a `cuenta.panel`).

  - [x] 5.1 Tests ejemplo login/logout (válido→`/cuenta`, inválidos genéricos, PENDING/bloqueado/inactivo genéricos, logout invalida).
    - _Requisitos: 3, 4_

- [x] 6. Checkpoint — cuenta local navegable
  - `composer run test` en verde; flujo manual registro→verificación→login→logout; Cliente no entra al panel; operador no entra a `/cuenta/*` privada. Preguntar al usuario antes de seguir.
  - Resultado 2026-10-03: 29/29 tests `Cuenta` en verde + `Auth/Login` intactos; suite completa solo con 2 fallos preexistentes verificados en HEAD limpio (`EgresoCajaTipoPagoTest`, `ServicioWebPropertiesTest` p5).

- [x] 7. Recuperación + revocación global
  - `Cuenta\RecuperacionController` (`throttle:3,1`); respuesta siempre genérica; token corto hasheado; cambio `Hash::make + password_changed_at=now() + consumed_at` + `logoutOtherDevices` + purga `sessions` ajenas + `regenerate` + auditoría; `POST /cuenta/sesiones/revocar-todas`.
  - Vistas `cuenta/recuperacion*.blade.php` + `resources/js/cuenta/recuperacion.js`.
  - Scheduler limpieza `one_time_tokens/sessions` en `routes/console.php` + `SESSION_SECURE_COOKIE=true` en `.env.prod` (no en repo).
  - _Requisitos: 4.2, 5, 9.1, 9.5_
  - Nota: revocación = borrado de filas `sessions` ajenas + `regenerate` (sin `logoutOtherDevices`, innecesario sin remember); `SESSION_SECURE_COOKIE` queda para despliegue (no en repo).

- [x] 8. Zona privada con ownership (lectura primero)
  - [x] 8.0 Plantilla panel bienvenida MVP (ya creada: `resources/views/cuenta/panel.blade.php`)
    - Verificar que extiende `layouts/publica`, muestra el mensaje de bienvenida, sin `<script>` inline y sin entrada Vite; `GET /cuenta` (`cuenta.panel`, `auth+cliente`) la renderiza, guest redirige a `/cuenta/login`.
    - _Requisitos: 10_
  - `Cuenta\CuentaController@show/edit/update` + `VehiculoController@index/show` (scope `where cliente_id in user->clientes ACTIVO`, `paginate(10)`, `overflow-x-auto`); Policies `CustomerUserLink/Automotor/(Lavado|CambioAceite lectura)`; 403 + auditoría en intento ajeno.
  - Rutas: estáticas (`/vehiculos/buscar`) ANTES que `resource`; grupo `['auth','cliente','can:acceso-cuenta']` con prefijo `cuenta`.
  - Vistas `cuenta/panel, cuenta/vehiculos/*` + `resources/js/cuenta/{panel,vehiculos}.js` (vite + dark).
  - _Requisitos: 6, 7_
  - Nota: implementado panel dinámico + `VehiculoController@index/show` (Automotor scopeado, `paginate(10)`, `overflow-x-auto`) + `AutomotorPolicy` (`Gate::authorize`); sin `/vehiculos/buscar` ni historial lavados (futuro).

  - [x] 8.1 Tests ejemplo ownership (A→403 en recurso B; listado A sin filas B; `paginate(10)`).
    - _Requisitos: 7_

- [x] 9. Property tests base (≥100 iter, `faker`)
  - Crear `tests/Feature/Cuenta/*PropertyTest.php` con tag `// Feature: autenticacion-publica-clientes, Property {N}: ...`:
  - [x] 9.1 **Propiedad 1: registro crea identidad+vínculo+token+rol sin permisos** — _Req. 1.3, 1.6_ (`CuentaRegistroPropertyTest`, 100 iter en verde)
  - [ ]* 9.2 **Propiedad 2: sin verificación no hay login** — _Req. 2.1, 2.2, 3.2_ (cubierta con tests ejemplo; property pendiente fase 12)
  - [ ]* 9.3 **Propiedad 3: token un solo uso + expiración** — _Req. 2.3, 2.4, 5.2, 5.3_ (cubierta con tests ejemplo; property pendiente)
  - [ ]* 9.4 **Propiedad 4: anti-enumeración login/recuperación** — _Req. 3.3, 5.1_ (cubierta con tests ejemplo; property pendiente)
  - [ ]* 9.5 **Propiedad 5: throttle + bloqueo progresivo** — _Req. 3.4, 9.2_ (cubierta con tests ejemplo; property pendiente)
  - [ ]* 9.6 **Propiedad 6: separación público↔operativo** — _Req. 6.2, 6.3_ (cubierta con tests ejemplo; property pendiente)
  - [ ]* 9.7 **Propiedad 7: ownership IDOR/BOLA** — _Req. 7.1, 7.2_ (cubierta con tests ejemplo; property pendiente)
  - [ ]* 9.8 **Propiedad 8: revocación global** — _Req. 4.2, 4.3_ (cubierta parcial; property pendiente)
  - [ ]* 9.10 **Propiedad 10: auditoría sin secretos + normalización** — _Req. 9.3, 9.4_ (pendiente)
  - JS: `tests/js/cuenta/*.property.test.js` (`fast-check`) si `resources/js/cuenta/*` tiene lógica pura; `npm run test`.
  - Nota: sin JS en cuenta (vistas server-side), no aplica `npm run test`.

- [x] 10. Checkpoint — zona privada segura
  - Suite completa verde; demo IDOR (A no ve B), expiración/revocación, CSRF en `POST /cuenta/*`. Confirmación del usuario.
  - Resultado 2026-10-03: IDOR + expiración + revocación + CSRF cubiertos en `tests/Feature/Cuenta/*`; resto de suite sin regresiones (2 fallos preexistentes en HEAD limpio).

- [x] 11. Google OIDC (fase posterior, solo tras 1–10)
  - `composer require laravel/socialite firebase/php-jwt` (o `google/apiclient`); `xxxx_create_social_identities_table` (`UNIQUE(provider,provider_subject)`); `config/services.php google + .env.example GOOGLE_*`; `Cuenta\GoogleController@redirect/callback/vincular/desvincular` (Code+PKCE, `state/nonce/code_verifier` corta, scopes `openid email profile`, validación `firma/iss/aud/exp/nonce`, `throttle:10,1`); política no-auto-vínculo + notificación + auditoría; prohíbe quitar último método.
  - _Requisitos: 8_
  - Implementado 2026-10-03: `GoogleOidcService` explícito (Http + firebase/php-jwt) con checklist Req 8 completo — Socialite 5.x quedó instalado como lib mantenida, pero su driver web valida vía userinfo sin exigir `nonce`/doble `iss`, así que el servicio aplica PKCE-S256 + `state/nonce/verifier` en sesión (10 min) + JWKS cacheado + `iss` dual + `aud` + `exp` + `nonce` + `sub`. Alta Google crea cuenta `ACTIVE` verificada (o `PENDING` si email no verificado) + ficha + vínculo + identidad en transacción; email coincidente guarda pendiente 15 min y exige sesión local (vista `google-vincular`); desvincular exige `password_changed_at` (último método). Tablas/modelos: `social_identities` + `SocialIdentity` + `User::socialIdentities()`. Correo `CuentaGoogleMail` (registro/vínculo/desvínculo). Migración `2026_10_03_000005`.
  - Bugfix 2026-10-03 (real, en MySQL): `registros_auditoria.accion` es `varchar(30)` y dos acciones lo excedían (`inicio de sesión con Google (cuenta)` 37, `intento fallido…(cuenta)` 45) → 500 solo en MySQL (SQLite no valida longitud). Acortadas a `inicio sesión google` / `intento fallido cuenta` + test guardián `CuentaAuditoriaAccionesTest` que falla si algún literal supera la columna.

  - [x] 11.1 Tests Google con mocks (sub conocido entra; email-coincidente exige control; `state/nonce/exp/aud/firma` mal →403; `UNIQUE(provider,subject)`).
    - _Requisitos: 8_
    - `CuentaGoogleTest` (15 tests, servicio mockeado) en verde.
  - [x] 11.2 **Propiedad 9: Google por provider+sub, nunca email solo** (≥100 iter). — _Req. 8.2–8.4_
    - `CuentaGooglePropertyTest` (100 iter, cripto real con llaves estáticas `tests/Support/GoogleOidcLlaves.php`) + `GoogleOidcServiceTest` (8 casos) en verde.

- [ ] 12. Endurecimiento y operación
  - Idle/absoluta diferenciada (middleware `last_seen` si D4 lo exige), reautenticación para email/password/vínculos, `throttle` verificación/callback, métricas (logins ok/fallidos, callbacks fallidos, sesiones activas, bloqueos), retención/limpieza programada, `data:clean` si normaliza, MFA admin diferido (TOTP o IdP) sin tocar `Gate::before` hoy.
  - _Requisitos: 9_

- [ ] 13. Checkpoint final
  - `composer run test` + `npm run test` + `vendor/bin/pint` verdes; `php artisan migrate:fresh --seed` ok; `GET /ping` ok; despliegue: migraciones aditivas idempotentes, rollback por fase (dropear `social_identities/one_time_tokens` no afecta `users/clientes`), secretos por entorno, `redirect URI` exacta. Preguntar al usuario.

## Notas

- Tareas `*` opcionales para MVP rápido (mínimo: 0–8 + 13); propiedades 1–10 exigibles antes de producción.
- Trazabilidad: cada tarea cita requisitos; cada property cita `Property {N}` del `design.md`.
- Tiempos siempre relativos `now()->addMinutes/subMinutes` (Lima); nunca literales fijos.
- Nunca red real (Google/DNI/placa/Cloudinary/mail) en tests: mocks + `MAIL_MAILER=array/log` + `SESSION_DRIVER=array`.
