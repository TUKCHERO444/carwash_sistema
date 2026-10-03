# Documento de Requisitos — Autenticación pública de clientes (`/cuenta/*`)

## Introducción

Este documento define los requisitos para dotar a la web pública (hoy sin autenticación: `resources/views/publica/*` con `layouts/publica`) de registro, inicio/cierre de sesión y recuperación de acceso para clientes, más login con Google (OIDC) como fase posterior, sin romper el estado actual.

Estado actual verificado (ver `docs/contraste-autenticacion-publica.md`): un solo guard `web` sesión (`config/auth.php:40-45`, provider `users`), sesión `database` lifetime 120 (`config/session.php:21,35`), `User` operadores con `email unique + password + activo` (`app/Models/User.php`, migración `0001_01_01_000000`), `Cliente` entidad comercial sin credenciales (`app/Models/Cliente.php`, `clientes: dni nullable sin unique, nombre, apellidos, telefono`), sin vínculo `users <-> clientes`, sin `register/reset/verify`, sin Socialite/Sanctum/Google, RBAC spatie 17 permisos `guard web` (`PermissionSeeder.php`) + bypass `Administrador` (`AppServiceProvider.php:31-33`), auditoría `registros_auditoria` con `registrarSesion` solo para inicio/cierre exitoso (`LoginController.php:54,75`). Zona horaria única `America/Lima` (`config/app.php:68`); tiempos con `now()`/Carbon, nunca `date()` crudo.

Decisión arquitectónica central (no negociable): `Cliente` NO se convierte en entidad autenticable. La identidad sigue siendo `users.id` (bigint, convención del proyecto; se rechaza `auth_users` UUID paralelo). El vínculo identidad↔negocio es explícito vía pivote `customer_user_links`. Las credenciales siguen en `users`; las sesiones siguen en `sessions` nativa de Laravel (se rechaza `auth_sessions` custom y cookies `public_session/admin_session` separadas en monolito single-domain). Google se vincula por `provider + sub`, nunca solo por email.

## Glosario

- **Sistema**: aplicación Laravel 12 del carwash (Blade + Tailwind 4, Vite 7).
- **Cuenta**: fila `users` con rol `Cliente`; representa a quien puede autenticarse en la web pública.
- **Cliente**: fila `clientes`; persona operativa que deja el vehículo (DNI, nombre, teléfono, automotores por `placa` FK). Sin email/password.
- **Vínculo**: fila `customer_user_links` (`user_id ↔ cliente_id`, `estado`, `rol/alcance` opcional).
- **IdentidadExterna**: fila `social_identities` (`provider, provider_subject=sub`, `provider_email`).
- **TokenUnSoloUso**: fila `one_time_tokens` (`purpose=verificacion|recuperacion|vinculacion`, `token_hash`, `expires_at`, `consumed_at`).
- **GuardWeb**: único guard `web` sesión + provider `users` (`config/auth.php`).
- **Rol_Cliente**: rol spatie `Cliente` (`guard web`) sin ningún permiso `acceso-*`.
- **ZonaCuenta**: rutas `/cuenta/*` (públicas guest + privadas `auth`).
- **PanelOperativo**: rutas actuales `/dashboard` + recursos con `permission:*` (no se renombran a `/admin/*`).
- **DataNormalizer**: `app/Services/DataNormalizer.php` (DNI 8 dígitos, teléfono 9 dígitos, placa, nombres).
- **AuditService**: `app/Services/AuditService.php` + `AuditServiceProvider::$modelos` + tabla `registros_auditoria`.

---

## Requisitos

### Requisito 1: Registro de cuenta pública (email/contraseña)

**Historia de usuario:** Como visitante de la web pública, quiero crear una cuenta con email y contraseña para acceder a mi zona privada, sin obtener acceso al panel operativo.

#### Criterios de Aceptación

1. WHEN el visitante accede a `GET /cuenta/registro`, THE Sistema SHALL mostrar el formulario (layout `layouts/publica` o `layouts/cuenta`, clases `.bg-surface/.input-main/.label-main`, `@vite` del módulo, sin `<script>` inline salvo `window.* = @json(...)`).
2. THE formulario SHALL pedir `nombre`, `email`, `password + password_confirmation` y aceptar `dni/telefono` opcionales normalizados con `DataNormalizer`.
3. WHEN el visitante envía `POST /cuenta/registro` válido, THE Sistema SHALL en una transacción crear `users(name, email unique normalizado minúsculas, password Hash::make, status=PENDING_EMAIL_VERIFICATION, activo=true)`, crear o enlazar `clientes` (nuevo o match asistido, nunca duplicar por email porque `clientes` no tiene email), crear `customer_user_links(user_id, cliente_id, estado=ACTIVO)`, emitir `TokenUnSoloUso(purpose=verificacion)` (solo `token_hash`), enviar mail de verificación y auditar (`modulo=cuenta, accion=registro`).
4. WHEN el email ya existe, THEN THE Sistema SHALL responder con error de validación en el campo `email` (el registro sí puede revelar duplicado; el login/recuperación no — ver Requisitos 3 y 5).
5. THE ruta `POST /cuenta/registro` SHALL llevar `throttle` + `@csrf` y normalizar email con `DataNormalizer`/minúsculas antes de validar unicidad.
6. WHEN la cuenta se crea, THE Sistema SHALL asignarle el rol `Cliente` y ningún permiso `acceso-*`.
7. IF no hay caja activa, THEN THE registro de cuenta SHALL seguir funcionando (el acople `CajaService`/`error_caja` aplica solo a ventas, no a cuentas).

### Requisito 2: Verificación de email y activación

**Historia de usuario:** Como titular de una cuenta recién creada, quiero verificar mi email para activar mi acceso.

#### Criterios de Aceptación

1. WHEN se crea la cuenta, THE Sistema SHALL dejarla en `status=PENDING_EMAIL_VERIFICATION` con `email_verified_at=null` y denegar `POST /cuenta/login` hasta verificar.
2. WHEN el titular abre `GET /cuenta/verificacion/{id}/{hash}` firmado (o consume `TokenUnSoloUso purpose=verificacion`), THE Sistema SHALL marcar `email_verified_at=now()`, `status=ACTIVE`, `consumed_at=now()` y auditar (`modulo=cuenta, accion=verificacion`).
3. IF el token está expirado, ya consumido o el hash no coincide, THEN THE Sistema SHALL rechazar con 403/410 genérico y ofrecer reenvío con `throttle`.
4. WHEN el token se consume dos veces, THE segunda SHALL fallar (un solo uso).

### Requisito 3: Inicio de sesión público (email/contraseña)

**Historia de usuario:** Como cliente con cuenta verificada, quiero iniciar sesión en `/cuenta/*` con email y contraseña.

#### Criterios de Aceptación

1. WHEN el visitante accede a `GET /cuenta/login` (guest), THE Sistema SHALL mostrar el formulario; WHEN ya autenticado, THE Sistema SHALL redirigir a `/cuenta` (no a `/dashboard`).
2. WHEN envía `POST /cuenta/login` con credenciales válidas + `status=ACTIVE` + `activo=true` + no bloqueado, THE Sistema SHALL hacer `Auth::guard('web')->attempt()`, `session()->regenerate()`, resetear `failed_attempts=0`, registrar `last_login/password` no secreto y auditar inicio exitoso.
3. IF la cuenta está `PENDING/SUSPENDED/DISABLED`, `activo=false`, `locked_until` futuro o credenciales inválidas, THEN THE Sistema SHALL responder mensaje genérico anti-enumeración (misma respuesta/tiempo para inexistente, incorrecta o no verificada) y auditar el fallo sin secretos. **Nota de ruptura:** hoy `LoginController` distingue mensajes (`LoginTest.php:119-195`); este cambio exige actualizar esos tests del panel, decisión pendiente del usuario.
4. THE ruta SHALL llevar `throttle:5/min` + bloqueo progresivo (`failed_attempts++`, `locked_until`) y `@csrf`.
5. WHEN el login público tiene éxito, THE Sistema SHALL crear sesión `web` estándar (cookie única `SESSION_COOKIE`, `HttpOnly`, `SameSite=lax`, `Secure=true` en prod) y redirigir a `/cuenta`; nunca a `/dashboard`.

### Requisito 4: Cierre de sesión y revocación

**Historia de usuario:** Como cliente autenticado, quiero cerrar mi sesión y poder revocar todas mis sesiones tras un cambio sensible.

#### Criterios de Aceptación

1. WHEN envía `POST /cuenta/logout` (`auth`), THE Sistema SHALL auditar cierre, `Auth::logout()`, `session()->invalidate()`, `regenerateToken()` y redirigir a `/` o `/cuenta/login`.
2. WHEN cambia su contraseña, recupera acceso o vincula Google, THE Sistema SHALL ejecutar `Auth::logoutOtherDevices()` + borrar sus filas `sessions` salvo la actual + rotar ID de sesión + auditar (`modulo=cuenta, accion=revocacion_global`).
3. WHILE la sesión está expirada, revocada o la cuenta `SUSPENDED/DISABLED`, THE Sistema SHALL tratarla como inválida aunque el token sea correcto y redirigir a login.

### Requisito 5: Recuperación de contraseña

**Historia de usuario:** Como cliente que olvidó su contraseña (o cuenta solo-Google que quiere crear una local), quiero restablecerla con un enlace de un solo uso.

#### Criterios de Aceptación

1. WHEN envía `POST /cuenta/recuperacion` con cualquier email, THE Sistema SHALL responder siempre 200 genérico (anti-enumeración); solo si el email existe crea `TokenUnSoloUso(purpose=recuperacion, vida corta)` + mail y audita solicitud sin revelar existencia.
2. WHEN abre el enlace y envía nueva `password confirmed`, THE Sistema SHALL validar token (`token_hash`, `expires_at`, `consumed_at=null`), actualizar `password=Hash::make + password_changed_at=now()`, marcar `consumed_at`, ejecutar revocación global (Req. 4.2) y auditar.
3. THE tokens SHALL guardarse solo como `token_hash` (nunca en claro), con expiración y limpieza programada; la reutilización SHALL fallar.

### Requisito 6: Separación público vs panel operativo

**Historia de usuario:** Como operador, quiero que una sesión de cliente nunca abra el panel, y como cliente, que un operador sin vínculo no vea mi zona.

#### Criterios de Aceptación

1. THE Sistema SHALL mantener el guard `web` único; la separación es por rutas + rol/permiso, no por guards/cookies separadas: `ZonaCuenta` (`/cuenta/*`) exige rol `Cliente` (o `auth` + ownership, Req. 7); `PanelOperativo` exige `auth + permission:acceso-*` como hoy.
2. WHILE el usuario solo tiene rol `Cliente`, THE Sistema SHALL denegar (403) todo acceso a rutas `permission:acceso-*` y a `/dashboard` operativo.
3. WHILE la sesión pertenece a un operador sin rol `Cliente` ni vínculo, THE Sistema SHALL denegar (403/redirect) las rutas privadas `/cuenta/*` ajenas.
4. THE rol `Cliente` SHALL crearse en `PermissionSeeder` con `firstOrCreate` (`guard web`) sin permisos; `Gate::before Administrador` NO se extiende a `Cliente`.
5. Las rutas estáticas/AJAX de `/cuenta/*` (p. ej. `cuenta.vehiculos.buscar`) SHALL declararse ANTES que los resource de la zona (gotcha `routes/web.php`: estáticas antes que resource o Laravel las bindea como parámetro).

### Requisito 7: Autorización por recurso (ownership)

**Historia de usuario:** Como cliente, quiero que nadie con sesión válida pueda ver o modificar mis vehículos, lavados o datos con solo conocer su ID.

#### Criterios de Aceptación

1. THE Sistema SHALL autorizar cada recurso de `/cuenta/*` por ownership vía Policies (`CustomerUserLinkPolicy`, `AutomotorPolicy`, `LavadoPolicy`/`CambioAceitePolicy` de lectura): `user->clientes` (vía `customer_user_links estado=ACTIVO`) contiene al `cliente_id` del recurso; si no, 403 + auditoría del intento.
2. WHEN el cliente lista sus recursos, THE Sistema SHALL aplicar scoping (`where cliente_id in (...)`) + `paginate(10)` (convención listados) + `overflow-x-auto` en tablas.
3. THE vistas SHALL usar clases dark-mode semánticas (`resources/css/app.css`: `.bg-surface`, `.text-primary`, etc.; slate, nunca negro puro).

### Requisito 8: Inicio de sesión con Google (OIDC, fase posterior)

**Historia de usuario:** Como visitante, quiero entrar con mi cuenta de Google sin crear contraseña, vinculando por identidad estable y sin riesgo de secuestro por email coincidente.

#### Criterios de Aceptación

1. THE Sistema SHALL implementar OIDC Authorization Code + PKCE con librería mantenida (p. ej. `laravel/socialite` + validación `id_token` con `firebase/php-jwt`/`google/apiclient`): `state + nonce + code_verifier`, scopes mínimos `openid email profile`, `redirect URI` exacta registrada por entorno.
2. WHEN el callback canjea el code con `code_verifier`, THE Sistema SHALL validar firma, `iss`, `aud/client_id`, `exp`, `nonce`; cualquier fallo SHALL abortar + auditar (`modulo=cuenta, accion=google_callback_fallido`) con `throttle`.
3. WHEN el `sub` existe en `social_identities(provider=google)`, THE Sistema SHALL autenticar esa `user_id` (actualizando `last_login_at`) con `session()->regenerate()`.
4. IF el `sub` no existe: WHEN no hay `users` con ese email, THE Sistema SHALL crear cuenta+vínculo+`social_identities` en transacción (política de alta); IF sí hay `users` con ese email pero distinto `sub`, THEN THE Sistema SHALL NO vincular automáticamente y exigir prueba de control (login local previo o token de vinculación explícita) + notificar al titular.
5. THE Sistema SHALL impedir quitar el último método activo (si solo tiene Google, no puede desvincularlo sin crear password antes) y auditar vinculación/desvinculación.

### Requisito 9: Seguridad, sesiones, auditoría y operación

**Historia de usuario:** Como responsable del sistema, quiero sesiones revocables y auditadas, secretos bien guardados y pruebas que impidan regresiones.

#### Criterios de Aceptación

1. THE Sistema SHALL mantener sesiones opacas stateful en `sessions` nativa (sin JWT en `localStorage`); el navegador solo recibe secreto en cookie; expiración por inactividad (`SESSION_LIFETIME`, hoy 120) y absoluta/revocación según Req. 4; `SESSION_SECURE_COOKIE=true` en prod, `HttpOnly`, `SameSite=lax` (Strict solo admin si UX lo permite — diferido).
2. THE Sistema SHALL aplicar `throttle` en registro, login, recuperación, verificación y callback Google; rotar sesión tras login, cambio sensible y vinculación; exigir `@csrf` + validación `Origin/Referer` en mutaciones con cookie.
3. THE Sistema SHALL hashear passwords con el driver configurado de Laravel (hoy bcrypt vía `password hashed`; Argon2id solo como decisión posterior con rehash), nunca reversible ni claro; nunca loggear `password/token/code/id_token`.
4. THE Sistema SHALL extender `AuditService` (`SENSIBLES` + `registrarSesion`: fallidos, revocación, recuperación, vinculación Google) sobre `registros_auditoria` y registrar `CustomerUserLink/SocialIdentity/OneTimeToken` en `AuditServiceProvider::$modelos` si se auditan; excluir `sessions`/pivotes efímeras según `EXCLUIDOS`.
5. THE Sistema SHALL programar limpieza (`sessions` expiradas, `one_time_tokens` expirados/consumidos) y métricas (logins ok/fallidos, callbacks fallidos, sesiones activas, bloqueos).
6. THE entrega SHALL incluir tests automatizados (ver `design.md` Estrategia de Pruebas): rutas críticas, horizontal/IDOR, expiración/revocación, callback OIDC con mocks (nunca red real ni Cloudinary real), con tiempos relativos `now()` (Lima) y `RefreshDatabase` + `Permission::firstOrCreate + givePermissionTo`, mocks de `DniApiService/AutomotorApiService` si intervienen.

### Requisito 10: Panel de bienvenida (MVP inmediato)

**Historia de usuario:** Como cliente recién registrado/logueado, quiero ver un panel con un mensaje de bienvenida para confirmar que mi cuenta funciona.

#### Criterios de Aceptación

1. WHEN el cliente autenticado (rol `Cliente`, `status=ACTIVE`) accede a `GET /cuenta`, THE Sistema SHALL mostrar la vista `cuenta/panel.blade.php` con texto de bienvenida (plantilla estática, sin datos sensibles).
2. THE vista SHALL extender `layouts/publica`, usar clases dark-mode semánticas (`.bg-surface`, `.text-primary`, `.text-secondary`, `.border-main`) y NO contener `<script>` inline ni módulo JS (sin entrada en `vite.config.js` por ahora).
3. WHILE el visitante no está autenticado, THE Sistema SHALL redirigir `GET /cuenta` a `/cuenta/login`; WHILE es solo-`Cliente`, THE Sistema SHALL denegar `/dashboard` (Req. 6.2).

## Decisiones pendientes (bloquean implementación)

D1. Cardinalidad: ¿1 cuenta↔1 cliente (`UNIQUE(user_id)`) o membresías N:M (`UNIQUE(user_id,cliente_id)`)? D2. ¿Cuenta dual cliente+operador o separadas? D3. Política email-coincidente Google (no auto-vincular por defecto). D4. Duraciones idle/absoluta pública vs operativa, vida de tokens verificación/recuperación. D5. Recuperación para cuentas solo-Google. D6. Migración de `clientes` existentes sin email (alta asistida vs auto-match DNI+teléfono+placa; `dni` hoy no-unique). D7. Anti-enumeración en login operativo (rompe `LoginTest` actual). D8. Mailer (`postmark/resend/ses` en `config/services.php`) y plantillas. D9. Retención/borrado (Perú) sin romper historial.
