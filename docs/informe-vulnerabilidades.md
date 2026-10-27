# Informe de vulnerabilidades y soluciones

> **Fecha:** 2026-10-25  
> **Alcance:** panel administrativo Laravel 12, autenticación, autorización, formularios, rutas, frontend JS, configuración, Docker y dependencias.  
> **Método:** revisión estática de código y configuración, `composer audit`, `npm audit` y revisión de rutas. No se realizaron pruebas dinámicas de explotación.  
> **Estado:** auditoría documentada; VUL-01, VUL-02, VUL-03, VUL-04, VUL-05, VUL-06, VUL-07, VUL-08, VUL-09 y VUL-11 implementados. VUL-10 queda parcial aceptado: cabeceras base y `TrustHosts` implementados; la CSP estricta sin `unsafe-inline` se difiere como endurecimiento. VUL-06 requiere validación de imagen en un entorno con Docker.

---

## 1. Resumen ejecutivo

No se encontraron inyecciones SQL explotables ni fallas de CSRF en los flujos revisados. La autenticación base está bien construida: contraseñas hasheadas, regeneración de sesión en login, invalidación en logout y middleware que cierra sesiones de usuarios desactivados.

Los riesgos que requieren atención prioritaria son:

1. El login administrativo no tenía límite de intentos ni bloqueo de cuenta (VUL-01, ya implementado).
2. El seeder creaba una cuenta administradora con contraseña conocida (VUL-02, ya implementado).
3. Existía XSS almacenado en módulos de inventario y compras (VUL-04, ya implementado).
4. Había dependencias con vulnerabilidades conocidas, incluida una crítica de Laravel (VUL-05, ya implementado).
5. La imagen Docker podía incluir `.env` y otros archivos sensibles por falta de `.dockerignore` (VUL-06, ya implementado).
6. Los permisos `acceso-usuarios` y `acceso-roles` permitían escalar a Administrador si se delegaban a roles intermedios (VUL-03, ya implementado).
7. La sesión no forzaba cookies seguras ni fallaba con `APP_DEBUG=true` en producción; además existía una ruta pública de prueba de Cloudinary y la auditoría no guardaba IP/user-agent (VUL-07, VUL-08 y VUL-09, ya implementados).

### Matriz resumen

| ID | Severidad | Hallazgo | Estado |
|---|---|---|---|
| VUL-01 | Alta | Login administrativo sin rate limiting ni lockout | Implementado |
| VUL-02 | Alta | Credenciales iniciales débiles y conocidas | Implementado |
| VUL-03 | Alta | Escalada de privilegios en usuarios y roles | Implementado |
| VUL-04 | Alta | XSS almacenado en inventario/compras | Implementado |
| VUL-05 | Crítica / Alta | Dependencias vulnerables | Implementado |
| VUL-06 | Alta | Docker copia `.env` y archivos sensibles | Implementado |
| VUL-07 | Media | Configuración de sesión y debug permisiva | Implementado |
| VUL-08 | Media | Ruta pública `/test-cloudinary` | Implementado |
| VUL-09 | Media | Auditoría incompleta de login y permisos | Implementado |
| VUL-10 | Baja / Media | Ausencia de cabeceras defensivas y `TrustHosts` | Parcial aceptado (cabeceras y `TrustHosts`; CSP estricta pendiente) |
| VUL-11 | Baja | Política de contraseñas débil | Implementado |

---

## 2. Metodología

Revisión estática de:

- Rutas y middleware: `routes/web.php`, `bootstrap/app.php`.
- Autenticación: `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Controllers/Cuenta/LoginController.php`.
- Autorización: controladores administrativos, `app/Providers/AppServiceProvider.php`, seeders de roles y permisos.
- Formularios y vistas: búsqueda de `@csrf`, `{!! !!}` y validaciones.
- Frontend: búsqueda de `innerHTML`, `onclick` y concatenación de datos del backend.
- Configuración: `.env`, `config/session.php`, `config/app.php`, `Dockerfile`, `start.sh`.
- Dependencias: `composer audit --no-dev --format=json` y `npm audit --omit=dev --json`.

### Resultado de dependencias

- **PHP antes de VUL-05:** 41 advisories en 11 paquetes.
- **PHP después de VUL-05:** 0 advisories (`composer audit --no-dev`).
- **JavaScript de producción:** 0 vulnerabilidades reportadas (`npm audit --omit=dev`).

---

## 3. Hallazgos y soluciones

### VUL-01 — Login administrativo sin rate limiting ni bloqueo

**Severidad:** Alta

**Estado:** Implementado

**Ubicación corregida:**
- `routes/web.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Cuenta/LoginController.php`
- `app/Services/LoginLockoutService.php`
- `resources/views/errors/429.blade.php`
- `resources/views/partials/bloqueo-intentos.blade.php`
- `resources/js/auth/retry-countdown.js`
- `tests/Feature/Auth/AdminLoginSecurityTest.php`
- `tests/Unit/LoginLockoutServiceTest.php`

**Descripción original:**

`POST /login` solo estaba protegido por el middleware `guest`. No tenía `throttle`, no usaba los campos `failed_attempts`/`locked_until` y no aplicaba un bloqueo progresivo. El login público de clientes sí implementaba `throttle:5,1` y bloqueo, pero esa protección no cubría el panel administrativo.

Además, el login administrativo solo verificaba `activo`; ignoraba `locked_until`, `status` y `email_verified_at`.

**Impacto:**

- Ataques de fuerza bruta y credential stuffing contra cuentas administrativas.
- Un bloqueo aplicado desde la zona pública no impedía el acceso por el login del panel.
- Riesgo especialmente alto si se mantiene la contraseña por defecto del seeder.

**Implementación aplicada:**

1. `POST /login` incorpora `throttle:5,1,login` con prefijo propio para no compartir el contador global de otras rutas.
2. `LoginLockoutService` centraliza el bloqueo progresivo compartido por ambos logins:
   - Máximo de 5 intentos fallidos.
   - Bloqueos de 15, 30, 45 y 60 minutos según la cantidad acumulada de fallos.
   - No extiende un bloqueo vigente.
   - Reinicia `failed_attempts`/`locked_until` y actualiza `last_login_at` tras un acceso exitoso.
3. El login administrativo normaliza el email, verifica `locked_until`, `Hash::check`, `activo` y `status` antes de autenticar. No se exige `email_verified_at` porque las cuentas operativas las crea un administrador; el control efectivo de acceso es `activo` + `status`.
4. Los intentos fallidos, los intentos con cuenta bloqueada y los bloqueos quedan registrados en `registros_auditoria` (`modulo = sesiones`).
5. Los fallos mantienen un mensaje genérico para evitar enumeración de cuentas.
6. El rate limiting renderiza `errors/429` con el tiempo restante y un botón grande de vuelta al login; el bloqueo de cuenta muestra un aviso con cuenta regresiva dentro del panel de acceso.
7. Se añadieron tests de integración y unitarios, y se actualizó la auditoría de sesiones para reflejar el registro de fallos.

**Verificación:**

```bash
php artisan route:list --path=login -v
php artisan test --filter=AdminLoginSecurityTest
php artisan test --filter=LoginLockoutServiceTest
php artisan test --filter=SesionesTest
php artisan test --filter=Login
```

> Nota: la suite completa mantiene fallos preexistentes en el módulo de compras (`tipo_documento` obligatorio) y en `ServicioWebPropertiesTest`, ajenos a VUL-01.

---

### VUL-02 — Credenciales iniciales débiles y conocidas

**Severidad:** Alta en despliegues con seed

**Estado:** Implementado

**Ubicación original:**
- `database/seeders/AuthSeeder.php`

**Ubicación corregida:**
- `database/seeders/AuthSeeder.php`
- `config/seed.php`
- `.env.example`
- `AGENTS.md`
- `tests/Feature/Seguridad/CredencialesInicialesTest.php`

**Descripción original:**

El seeder creaba siempre la cuenta `admin@sistema.com` con contraseña `password`, incluso en producción. Esa credencial estaba documentada en `AGENTS.md` como login por defecto.

**Impacto:**

Si se ejecutaba `php artisan migrate:fresh --seed` en un entorno accesible, la cuenta administradora quedaba comprometible de inmediato. El lockout de VUL-01 limita la fuerza bruta, pero una credencial conocida por defecto sigue siendo crítica.

**Solución aplicada:**

1. `AuthSeeder` solo crea el administrador demo `admin@sistema.com / password` en entornos `local` y `testing`.
2. En otros entornos, si no se define `ADMIN_PASSWORD`, el seeder no crea el administrador y emite una advertencia.
3. Las credenciales iniciales se configuran mediante `ADMIN_NAME`, `ADMIN_EMAIL` y `ADMIN_PASSWORD` en `config/seed.php` y `.env.example`.
4. Cuando se define una contraseña explícita, se valida con `App\Rules\PasswordSegura` (VUL-11): mínimo 12 caracteres, mayúscula, minúscula, número, símbolo, lista negra y datos personales.
5. El administrador creado en producción se marca como `ACTIVE` y con `email_verified_at`, para que el login operativo no quede bloqueado.
6. No se implementó el cambio forzado en el primer acceso; se prefiere eliminar la credencial conocida y exigir una contraseña fuerte desde el despliegue.
7. Los demás seeders de datos demo no se modificaron; no deben ejecutarse en producción.

**Verificación ejecutada:**

```bash
php artisan test tests/Feature/Seguridad/CredencialesInicialesTest.php
php artisan test tests/Feature/Seguridad tests/Feature/Auth tests/Feature/Cuenta
composer run test
```

> `CredencialesInicialesTest`: **5 aprobadas**. `Seguridad + Auth + Cuenta`: **150 aprobadas**. La suite completa conserva los fallos preexistentes del módulo de compras (`tipo_documento` obligatorio) y de `ServicioWebPropertiesTest`, ajenos a VUL-02. Cubre el admin demo local, la ausencia de admin en producción sin `ADMIN_PASSWORD`, la creación con contraseña fuerte, y el rechazo de contraseñas débiles en producción y local.

---

### VUL-03 — Escalada de privilegios en gestión de usuarios y roles

**Severidad:** Alta, condicional a la delegación de permisos

**Estado:** Implementado

**Ubicación corregida:**
- `app/Http/Controllers/UserController.php`
- `app/Http/Controllers/RoleController.php`
- `app/Http/Controllers/UserToggleController.php`
- `app/Policies/UserPolicy.php`
- `app/Policies/RolePolicy.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/AdministratorProtectionService.php`
- `app/Support/RoleNames.php`
- `resources/views/users/index.blade.php`
- `resources/views/users/edit.blade.php`
- `resources/views/roles/index.blade.php`
- `tests/Feature/Seguridad/EscaladaPrivilegiosTest.php`

**Descripción original:**

Cualquier usuario con `acceso-usuarios` puede:

- Asignar el rol `Administrador` a otro usuario.
- Asignar el rol `Administrador` a sí mismo.
- Editar o eliminar a otros administradores.

Cualquier usuario con `acceso-roles` puede:

- Crear roles con permisos arbitrarios.
- Modificar el rol `Administrador`.
- Concederse permisos mediante un rol nuevo o editado.

El rol `Administrador` pasa cualquier comprobación de `Gate` por el `Gate::before`, por lo que estos permisos equivalen de facto a control total.

**Impacto:**

Si `acceso-usuarios` o `acceso-roles` se delega a un rol intermedio, ese rol puede escalar a Administrador. También se puede dejar el sistema sin administradores útiles o modificar el rol `Administrador`.

**Implementación aplicada:**

1. Se añadieron `UserPolicy` y `RolePolicy`, registradas explícitamente en `AppServiceProvider`. La asignación de roles y la gestión de usuarios/roles queda limitada por el alcance de permisos del actor.
2. Solo un `Administrador` puede asignar el rol `Administrador`. Un actor no administrador solo puede asignar roles cuyos permisos estén incluidos en los suyos. La misma regla se aplica al crear y editar usuarios, y el selector de roles se filtra en la vista.
3. Un usuario no puede modificar su propio rol. En la vista el selector queda deshabilitado y el servidor rechaza cualquier intento de cambio.
4. El rol `Administrador` está protegido contra edición, renombrado y eliminación; su nombre queda reservado. Los botones de acción no se muestran para ese rol.
5. `AdministratorProtectionService` impide eliminar, desactivar o quitar el rol al último Administrador (incluido el último administrador utilizable). La autoeliminación y la autodesactivación siguen bloqueadas.
6. Un actor no administrador tampoco puede editar, eliminar, activar/desactivar ni cambiar la contraseña de usuarios con permisos superiores a los suyos.
7. Se auditan las asignaciones y cambios de rol (`asignar rol`, `cambiar rol`) y las asignaciones y cambios de permisos (`asignar permisos`, `cambiar permisos`).
8. Se añadieron 14 tests de escalada vertical, alcance de roles, protección del rol `Administrador`, último administrador y auditoría.

**Verificación:**

```bash
php artisan test tests/Feature/Seguridad/EscaladaPrivilegiosTest.php
php artisan test tests/Feature/UserActivationToggle tests/Feature/Seguridad tests/Feature/Auditoria
```

> Nota: la suite completa queda en 667 pruebas aprobadas y 25 fallos preexistentes del módulo de compras (`tipo_documento` obligatorio) y de `ServicioWebPropertiesTest`, ajenos a VUL-03.

---

### VUL-04 — XSS almacenado en inventario y compras

**Severidad:** Alta

**Estado:** Implementado

**Ubicación original:**
- `app/Http/Controllers/ProductoController.php:133`
- `app/Http/Controllers/ProductoController.php:196`
- `app/Http/Controllers/ProveedorController.php:21`
- `resources/js/ventas/create.js:118-121`
- `resources/js/devoluciones/create.js:127-130`
- `resources/js/cambio-aceite/shared.js:103-106`
- `resources/js/compras/buscar-productos.js:63-66`
- `resources/js/compras/buscar-proveedores.js:57-61`
- `resources/js/ajustes/create.js:15`
- `resources/js/lavados/shared.js:80-83`
- `resources/js/ventas/create.js:150,183`
- `resources/js/cambio-aceite/create.js:31,69`

**Descripción:**

El nombre del producto solo se valida con `required|string|max:150`, sin una regex que restrinja caracteres. La razón social de proveedor permite comillas simples y dobles. Varios módulos insertan esos valores con `innerHTML` o dentro de atributos `onclick` sin escape.

Ejemplos:

```js
resultadosDiv.innerHTML = productos.map(p =>
    `<div ... onclick='agregarProducto(${JSON.stringify(p)})'>
        ${p.nombre} — S/ ${parseFloat(p.precio_venta).toFixed(2)} (Stock: ${p.stock})
    </div>`
).join('');
```

```js
resultadosDiv.innerHTML = proveedores.map(p => `
    <div ... onclick="window.seleccionarProveedor(${p.id}, '${p.razon_social.replace(/'/g, "\\'")}', '${p.ruc}')">
        <div ...>${p.razon_social}</div>
    </div>
`).join('');
```

**Impacto:**

Un producto o proveedor malicioso puede ejecutar JavaScript en la sesión de un administrador u operador. El payload queda almacenado en la base de datos y se ejecuta cada vez que se busca o visualiza el registro.

**Solución propuesta:**

1. Sustituir la interpolación en `innerHTML` por `textContent` y creación de nodos DOM.
2. Escapar todos los valores antes de insertarlos en HTML, reutilizando `escHtml` como en `resources/js/productos/index.js`.
3. Evitar `onclick` inline con `JSON.stringify`; usar `data-*` y `addEventListener`.
4. Escapar también los valores usados en atributos HTML.
5. Aplicar una regex restrictiva a nombres de producto y proveedor.
6. Escapar los nombres insertados en SweetAlert2 cuando se use `html`.
7. Añadir una Content Security Policy como defensa en profundidad.

**Verificación:**

Añadir pruebas con nombres como:

```text
<img src=x onerror=alert(1)>
"><script>alert(1)</script>
```

Deben mostrarse como texto literal, sin ejecutar JavaScript.

**Implementación aplicada:**

1. Se creó `resources/js/utils/escape-html.js` con `escapeHtml()`, que neutraliza `&`, `<`, `>`, comillas simples/dobles y backticks. Es la utilidad única para interpolar datos de BD en HTML.
2. Se escaparon los nombres y valores dinámicos en las tablas y autocompletes de ventas, devoluciones, cambio de aceite, ajustes de inventario, lavados y compras.
3. Se eliminaron los `onclick` inline que incrustaban `JSON.stringify(p)` o nombres de proveedor. Los resultados de búsqueda ahora usan `data-*` y `addEventListener`, manteniendo el objeto original en un cache local.
4. Los nombres usados en SweetAlert2 mediante `html:` (stock insuficiente en ventas y cambio de aceite) también se escapan.
5. `ProductoController` valida `nombre` contra una regex que rechaza `<`, `>`, comillas, backtick y caracteres de control, además de `required|string|max:150`. La razón social de proveedor ya estaba restringida y rechaza etiquetas HTML; las comillas siguen permitidas por compatibilidad con denominaciones comerciales, pero ahora se escapan en la salida.
6. Se añadió `app/Http/Middleware/SecurityHeaders.php` al grupo `web`. Incluye CSP (con `object-src 'none'`, `base-uri 'self'`, `frame-ancestors 'self'` y orígenes externos limitados), `X-Content-Type-Options`, `Referrer-Policy` y `X-Frame-Options` como defensa en profundidad. La CSP mantiene `unsafe-inline` porque la UI actual usa manejadores/estilos inline.
7. Se añadieron pruebas PHP y JS con los payloads `<img src=x onerror=alert(1)>` y `"><script>alert(1)</script>`.

**Verificación ejecutada:**

```bash
php artisan test tests/Feature/Seguridad/XssAlmacenadoTest.php
php artisan test tests/Feature/ProveedorTest.php tests/Feature/ProductoPrecioTest.php
php artisan test
npm test
vendor/bin/pint --test app/Http/Controllers/ProductoController.php app/Http/Middleware/SecurityHeaders.php tests/Feature/Seguridad/XssAlmacenadoTest.php
npm run build
```

> `XssAlmacenadoTest`: 15 pruebas aprobadas. `npm test`: 77 pruebas aprobadas. Las pruebas de proveedor y producto siguen pasando. Suite completa: **682 aprobadas y 25 fallos preexistentes** del módulo de compras (`tipo_documento` obligatorio) y de `ServicioWebPropertiesTest`, ajenos a VUL-04. `vendor/bin/pint --test` sobre los archivos modificados: aprobado; la revisión global del repositorio mantiene 7 archivos con formato pendiente ajenos a este cambio.

---

### VUL-05 — Dependencias vulnerables

**Severidad:** Crítica en Laravel; Alta en Guzzle, Symfony y CommonMark

**Estado:** Implementado

**Ubicación original:**
- `composer.lock`
- `composer.json`

**Ubicación corregida:**
- `composer.lock`

**Descripción:**

`composer audit --no-dev` reportó **41 advisories en 11 paquetes**.

Versiones instaladas relevantes:

| Paquete | Versión instalada | Problema principal | Versión corregida |
|---|---:|---|---|
| `laravel/framework` | `v12.58.0` | `CVE-2026-114509`, bypass de TokenGuard | `>=12.68.0` |
| `guzzlehttp/guzzle` | `7.10.0` | `CVE-2026-69246`, bypass de comprobaciones de host | `>=7.15.2` |
| `guzzlehttp/psr7` | `2.9.0` | Inyección CRLF / confusión de host | `>=2.12.3` |
| `league/commonmark` | `2.8.2` | Múltiples DoS y bypass de filtros XSS | Ver advisories |
| `symfony/http-kernel` | `v7.4.8` | Bypass de filtros `methods: ['GET']` | `>=7.4.12` |
| `symfony/mime` | `v7.4.8` | Inyección de cabeceras/SMTP | `>=7.4.12` |
| `symfony/routing` | `v7.4.8` | Bypass de requisitos de ruta | `>=7.4.13` |
| `symfony/http-foundation` | `v7.4.8` | ReDoS y bypass de subredes privadas | `>=7.4.19` |

La app usa el guard de sesión, no TokenGuard, por lo que la explotabilidad directa de `CVE-2026-114509` es limitada. Aun así, la dependencia está afectada y debe actualizarse.

**Impacto:**

Depende del paquete y del uso real. Los riesgos incluyen bypass de autenticación, inyección de cabeceras, SSRF, DoS y filtración de información.

**Actualización aplicada:**

Se ejecutó una actualización completa dentro de las restricciones de `composer.json`:

```bash
composer update --with-all-dependencies --no-interaction
composer audit --no-dev
```

Versiones instaladas después de la corrección:

| Paquete | Versión instalada | Requisito de seguridad |
|---|---:|---|
| `laravel/framework` | `v12.70.1` | `>=12.68.0` |
| `guzzlehttp/guzzle` | `7.16.0` | `>=7.15.2` |
| `guzzlehttp/psr7` | `2.13.2` | `>=2.12.3` |
| `league/commonmark` | `2.10.4` | `>2.10.1` |
| `league/flysystem` | `3.36.0` | `>3.35.2` |
| `symfony/http-kernel` | `v7.4.21` | `>=7.4.12` |
| `symfony/mime` | `v7.4.19` | `>=7.4.12` |
| `symfony/routing` | `v7.4.21` | `>=7.4.13` |
| `symfony/http-foundation` | `v7.4.21` | `>=7.4.19` |
| `symfony/mailer` | `v7.4.19` | `>=7.4.12` |
| `symfony/polyfill-intl-idn` | `v1.43.0` | `>=1.38.1` |

La actualización también incorporó versiones parcheadas de dependencias transitivas (Carbon, Monolog, Flysystem, PHPUnit, etc.). No fue necesario modificar las restricciones de `composer.json`.

**Verificación ejecutada:**

```bash
composer audit --no-dev
composer validate --strict --no-check-publish
npm audit --omit=dev
composer run test
```

> `composer audit --no-dev`: **0 advisories**. `composer validate --strict`: válido. `npm audit --omit=dev`: **0 vulnerabilidades**. Suite completa: **699 aprobadas y 25 fallos preexistentes** del módulo de compras (`tipo_documento` obligatorio) y de `ServicioWebPropertiesTest`, idénticos al baseline previo y ajenos a la actualización.

**Mantenimiento pendiente:**

- Este repositorio no tiene CI configurado; cuando se añada, debe ejecutar `composer audit --no-dev` y `npm audit --omit=dev` y bloquear despliegues con vulnerabilidades altas o críticas.
- Revisar periódicamente `composer outdated --direct --strict` y actualizar parches de seguridad.

---

### VUL-06 — Docker copia `.env` y archivos sensibles

**Severidad:** Alta

**Estado:** Implementado

**Ubicación original:**
- `Dockerfile:38`
- `Dockerfile:45`
- `Dockerfile:51`
- `start.sh:13`
- `start.sh:18`

**Ubicación corregida:**
- `.dockerignore`
- `Dockerfile`

**Descripción:**

No existía `.dockerignore`. El `Dockerfile` ejecuta `COPY . .`, por lo que el contexto de build podía incluir `.env`, `.git`, `node_modules`, logs y archivos locales. `.gitignore` no protege el contexto de Docker.

**Impacto:**

Si la imagen se publica, comparte o se sube a un registro, se pueden filtrar credenciales, tokens, historial Git y archivos internos.

**Solución aplicada:**

1. Se creó `.dockerignore` para excluir secretos, historial Git, dependencias locales, artefactos de build, tests, documentación y datos persistentes:

```text
.env
.env.*
.git
.gitattributes
.gitignore
.idea
.vscode
.phpactor.json
.phpunit.result.cache
auth.json
node_modules
vendor
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/testing/*
storage/framework/views/*
storage/app/public/*
storage/app/private/*
storage/pail
storage/*.key
bootstrap/cache/*
public/build
public/hot
public/storage
database/database.sqlite
*.log
*.sqlite
tests
docs
.kiro
```

2. `Dockerfile` ahora crea las carpetas persistentes necesarias después de excluirlas del contexto:

```dockerfile
RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize
```

La asignación de permisos se mantiene después del build:

```dockerfile
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
```

3. Se cambió `npm install` por `npm ci` para usar exactamente `package-lock.json` durante el build.
4. Los secretos no se incorporan a la imagen: `.env` queda fuera del contexto y debe inyectarse en runtime mediante variables de entorno.
5. Los artefactos generados localmente (`bootstrap/cache/packages.php`, `services.php`, `public/build`, logs y SQLite) tampoco se copian; se regeneran dentro de la imagen.

**Verificación estática ejecutada:**

```bash
npm ci --dry-run --ignore-scripts
git diff --check
```

> `npm ci --dry-run`: correcto. `git diff --check`: sin errores. No se pudo construir ni inspeccionar la imagen porque Docker no está instalado en este entorno.

**Verificación pendiente en un entorno con Docker:**

```bash
docker build -t carwash-sistema .
docker history --no-trunc carwash-sistema
docker run --rm carwash-sistema find /var/www/html -maxdepth 2 -name ".env"
```

> La última búsqueda no debe devolver ningún archivo. Como endurecimiento adicional, evaluar un build multi-stage para excluir Node.js, npm y dependencias de desarrollo de la imagen final.

---

### VUL-07 — Configuración de sesión y debug permisiva

**Severidad:** Media

**Estado:** Implementado

**Ubicación original:**
- `.env`
- `.env.example`
- `config/session.php:50`
- `config/session.php:172`
- `config/session.php:185`
- `config/session.php:202`
- `config/app.php:42`
- `app/Providers/AppServiceProvider.php:28-30`

**Ubicación corregida:**
- `app/Support/ProductionSecurityGuard.php`
- `app/Providers/AppServiceProvider.php`
- `config/session.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `.env.example`
- `tests/Feature/Seguridad/ConfiguracionSesionTest.php`

**Descripción original:**

- `APP_DEBUG=true` en `.env` y `.env.example`.
- `SESSION_ENCRYPT=false`.
- `SESSION_SECURE_COOKIE` no estaba definido.
- `SESSION_SAME_SITE` usaba `lax`.
- `SESSION_HTTP_ONLY` era `true`.

**Impacto:**

En producción, un `APP_DEBUG=true` expone trazas, rutas, consultas y datos internos. Sin `SESSION_SECURE_COOKIE=true`, la cookie puede enviarse por HTTP.

**Solución aplicada:**

1. `ProductionSecurityGuard::assertSafeConfiguration()` se ejecuta al arrancar y lanza una excepción si `APP_ENV=production` con `APP_DEBUG=true`, cookie de sesión no segura, `SESSION_HTTP_ONLY=false` o `SESSION_SAME_SITE` distinto de `lax`/`strict`.
2. En producción, `SESSION_SECURE_COOKIE` se fuerza a `true` por defecto aunque no se defina en `.env`; en local/testing conserva el valor de entorno.
3. `SESSION_HTTP_ONLY=true` y `SESSION_SAME_SITE=lax` quedan explícitos en `.env.example`.
4. `SecurityHeaders` añade `Strict-Transport-Security` en respuestas HTTPS o en producción.
5. `URL::forceScheme('https')` se mantiene para producción y ahora usa `config('app.env')` en lugar de `env()` directo.
6. `.env.example` documenta los valores de producción, incluido que `SESSION_ENCRYPT=true` es opcional y que activarlo invalida las sesiones existentes.

**Verificación ejecutada:**

```bash
php artisan test tests/Feature/Seguridad/ConfiguracionSesionTest.php
```

> **9 aprobadas / 17 aserciones**: debug, cookie segura, HttpOnly, SameSite, configuración válida, entorno local y HSTS sobre HTTPS.

---

### VUL-08 — Ruta pública `/test-cloudinary`

**Severidad:** Media

**Estado:** Implementado

**Ubicación original:**
- `routes/web.php:49-55`
- `public/test.jpg`

**Ubicación corregida:**
- `routes/web.php`
- `public/test.jpg` (eliminado)
- `tests/Feature/Seguridad/RutaPruebaCloudinaryTest.php`

**Descripción original:**

`GET /test-cloudinary` no requería autenticación. Subía `public/test.jpg` a Cloudinary y devolvía la URL pública.

**Impacto:**

Cualquiera podía invocar la ruta repetidamente, consumir cuota de Cloudinary y generar costes. También ampliaba innecesariamente la superficie de ataque.

**Solución aplicada:**

1. Se eliminó la ruta de prueba y el import de Cloudinary en `routes/web.php`.
2. Se eliminó `public/test.jpg` del repositorio.
3. No se conserva una variante de desarrollo: las subidas reales ya están cubiertas por los módulos autenticados existentes.
4. Las pruebas verifican que `/test-cloudinary` responde 404, que el archivo no existe y que `/ping` sigue disponible.

**Verificación ejecutada:**

```bash
php artisan test tests/Feature/Seguridad/RutaPruebaCloudinaryTest.php
```

> **3 aprobadas / 3 aserciones**.

---

### VUL-09 — Auditoría incompleta de login y permisos

**Severidad:** Media

**Estado:** Implementado

**Ubicación original:**
- `app/Services/AuditService.php`
- `app/Observers/AuditModelObserver.php`
- `app/Http/Controllers/Auth/LoginController.php:52-70`
- `app/Http/Controllers/UserController.php:58,97`
- `app/Http/Controllers/RoleController.php:76,126`

**Ubicación corregida:**
- `app/Services/AuditService.php`
- `app/Services/LoginLockoutService.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Cuenta/LoginController.php`
- `app/Http/Controllers/Cuenta/RecuperacionController.php`
- `app/Http/Controllers/Cuenta/RegistroController.php`
- `app/Http/Controllers/Cuenta/GoogleController.php`
- `app/Http/Controllers/UserController.php`
- `app/Http/Controllers/RoleController.php`
- `database/migrations/2026_10_25_000000_add_client_context_to_registros_auditoria_table.php`
- `tests/Feature/Auditoria/SesionesTest.php`
- `tests/Unit/LoginLockoutServiceTest.php`

**Descripción original:**

La auditoría registraba inicios y cierres de sesión exitosos y CRUD de modelos, pero no registraba intentos fallidos, bloqueos/desbloqueos, asignación o revocación de roles, cambios de permisos ni el contexto de red.

**Impacto:**

Era difícil investigar fuerza bruta, escaladas de privilegios o cambios no autorizados de permisos.

**Solución aplicada:**

1. Los intentos fallidos del panel y de la cuenta pública ya se registran mediante `LoginLockoutService`; los intentos con cuenta bloqueada también se auditan.
2. Al superar el máximo se registra `cuenta bloqueada`; al volver a entrar con un bloqueo vencido se registra `cuenta desbloqueada`.
3. La recuperación de contraseña de una cuenta bloqueada deja también el evento `cuenta desbloqueada`.
4. Los cambios de rol en usuarios (`syncRoles`) registran `asignar rol`/`cambiar rol`; los cambios de permisos de roles registran `asignar permisos`/`cambiar permisos`.
5. El alta pública y el alta con Google registran la asignación del rol `Cliente`.
6. `registros_auditoria` incorpora `ip_address` y `user_agent`; `AuditService` los completa en todos los eventos, incluidos los de autenticación.
7. Los intentos con un correo que no corresponde a ningún usuario no se asocian a un registro de auditoría porque `usuario_id` es obligatorio; quedan cubiertos por el throttle por IP del login y no se crean registros huérfanos. La retención/limpieza de auditoría sigue siendo una tarea operativa pendiente.

**Verificación ejecutada:**

```bash
php artisan test tests/Feature/Auditoria/SesionesTest.php tests/Unit/LoginLockoutServiceTest.php tests/Feature/Auth/AdminLoginSecurityTest.php
```

> **18 aprobadas / 99 aserciones**: incluye IP/user-agent, intento fallido, bloqueo, desbloqueo tras lockout vencido y el flujo de login administrativo. Suite completa final: **718 aprobadas y 25 fallos preexistentes** de compras/`ServicioWebPropertiesTest`.

---

### VUL-10 — Ausencia de cabeceras defensivas y `TrustHosts`

**Severidad:** Baja / Media

**Estado:** Parcial aceptado (cabeceras base y `TrustHosts` implementados; CSP estricta sin `unsafe-inline` diferida como endurecimiento)

**Ubicación original:**
- `bootstrap/app.php`
- `app/Providers/AppServiceProvider.php`

**Ubicación corregida:**
- `app/Http/Middleware/SecurityHeaders.php`
- `bootstrap/app.php`
- `tests/Feature/Seguridad/TrustHostsTest.php`

**Descripción original:**

No se encontraron:

- Content Security Policy.
- `X-Frame-Options` o `frame-ancestors`.
- `X-Content-Type-Options: nosniff`.
- `Referrer-Policy`.
- Configuración de hosts permitidos (`TrustHosts`).

**Impacto:**

Mayor impacto de XSS y clickjacking. La falta de `TrustHosts` puede afectar la generación de URLs absolutas en correos o redirecciones si el host se manipula.

**Implementación aplicada:**

1. `SecurityHeaders` se registra en el grupo `web` y añade CSP, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: SAMEORIGIN` y HSTS cuando la petición es HTTPS o el entorno es producción.
2. La CSP restringe `object-src`, `base-uri`, `frame-ancestors` y `form-action`, pero conserva `unsafe-inline` porque la UI actual usa manejadores y estilos inline.
3. `bootstrap/app.php` habilita `$middleware->trustHosts()`, por lo que Laravel valida el `Host` contra `APP_URL` (y sus subdominios) fuera de local/testing. `TrustHostsTest` verifica el patrón generado.
4. Queda pendiente eliminar `unsafe-inline` de `script-src` cuando los manejadores inline se sustituyan por listeners y los scripts de inicialización usen nonce. Los estilos inline de las vistas también requieren una migración aparte.
5. La solución base se cubre en VUL-04, punto 6, y en las pruebas de cabeceras de `XssAlmacenadoTest`.

**Decisión de cierre:** se acepta VUL-10 como parcial. No se retira `unsafe-inline` porque la UI usa manejadores y scripts inline en ventas, lavados, cambio de aceite, ajustes y reportes, y no existe una suite E2E de navegador que valide esos flujos. Retirarlo ahora cambiaría el comportamiento de módulos operativos sin cobertura suficiente.

**Seguimiento de endurecimiento:** migrar los manejadores inline a listeners con `data-*`, añadir un nonce por request a los scripts de inicialización, retirar `unsafe-inline` de `script-src` (los estilos inline requieren una migración separada) y validar los flujos POS en un navegador antes de desplegar.

---

### VUL-11 — Política de contraseñas débil

**Severidad:** Baja (el impacto aumenta si la cuenta tiene rol Administrador)

**Estado:** Implementado

**Ubicación original:**
- `app/Http/Controllers/UserController.php:65` (`store`)
- `app/Http/Controllers/UserController.php:108` (`update`)
- `resources/views/users/create.blade.php:82-120`
- `resources/views/users/edit.blade.php:83-120`
- `database/seeders/AuthSeeder.php`

**Ubicación corregida:**
- `app/Rules/PasswordSegura.php`
- `app/Http/Controllers/UserController.php`
- `resources/views/users/create.blade.php`
- `resources/views/users/edit.blade.php`
- `resources/js/users/password-policy.js`
- `resources/js/users/validate.js`
- `tests/Feature/Seguridad/PasswordPolicyTest.php`
- `tests/Feature/Seguridad/EscaladaPrivilegiosTest.php`
- `tests/js/users/password-policy.test.js`

**Descripción original:**

Las contraseñas creadas o modificadas desde el panel administrativo solo exigían `required|string|min:8|confirmed`. No se exigía complejidad, no se bloqueaban contraseñas comunes o asociadas a datos personales y la interfaz indicaba únicamente “Mínimo 8 caracteres”. La contraseña del seeder es `password`.

La validación estaba duplicada en `store` y `update`, por lo que el endurecimiento se centralizó para evitar que un flujo quedara con reglas más débiles.

**Impacto:**

Mayor probabilidad de contraseñas débiles, predecibles o reutilizadas. Si la cuenta creada tiene permisos administrativos, un compromiso permite acceso a ventas, caja, inventario, usuarios y auditoría.

**Política aplicada para contraseñas del panel administrativo:**

Se aplica a la creación de usuarios y a cualquier cambio de contraseña realizado desde el panel:

| Regla | Valor aplicado | Motivo |
|---|---|---|
| Longitud mínima | 12 caracteres | Aumenta el coste de fuerza bruta sin volver impráctica la operación. |
| Longitud máxima | 64 caracteres | Evita truncamientos y límites de bcrypt. |
| Mayúsculas | Al menos 1 | Exige variedad de caracteres. |
| Minúsculas | Al menos 1 | Exige variedad de caracteres. |
| Números | Al menos 1 | Cumple la combinación solicitada. |
| Símbolos | Al menos 1 de `!@#$%^&*()-_=+[]{};:,.?/` | Cumple la combinación solicitada. |
| Confirmación | Debe coincidir | Evita errores de captura. |
| Datos personales | No puede contener nombre, correo, DNI ni partes relevantes de ellos | Evita contraseñas adivinables. |
| Lista negra | Rechazar `password`, `123456`, `admin`, `carwash`, variantes y contraseñas comunes | Bloquea las claves más usadas. |
| Reutilización | La nueva contraseña no debe ser igual a la anterior | Evita cambios cosméticos. |
| Filtraciones | Opcional: `uncompromised()` o lista local | Bloquea claves presentes en filtraciones conocidas. |

La longitud por sí sola no sustituye a la lista negra: una contraseña larga pero predecible (`carwashcarwash`) debe rechazarse.

**Mecanismo de validación implementado (Laravel):**

1. `app/Rules/PasswordSegura.php` centraliza la política: longitud, mayúscula, minúscula, número, símbolo permitido, lista negra local, datos personales y reutilización de la contraseña actual.
2. `UserController::reglasPassword()` construye las reglas compartidas por `store` y `update`:

```php
'password' => $this->reglasPassword($request, $user),
```

3. La regla recibe el usuario objetivo y los valores de `name`/`email` del formulario para bloquear claves que contengan datos personales. En `store` no hay usuario previo; en `update` también se usa `Hash::check()` para impedir reutilizar la contraseña actual.
4. En `update`, las reglas son `nullable` y `sometimes`: dejar el campo vacío conserva la contraseña. Si se envía una nueva, se aplican todas las validaciones anteriores.
5. `confirmed` sigue verificando `password_confirmation`; los mensajes de esa regla se definieron en español en `UserController::MENSAJES_PASSWORD`.
6. No se registra la contraseña ni el hash en logs o auditoría. `AuditService::SENSIBLES` ya excluye `password` y `remember_token`.
7. No se añadió todavía un flujo de restablecimiento administrativo; si se crea, debe reutilizar `PasswordSegura`. La restricción del seeder demo sigue pendiente en VUL-02.
8. La validación del servidor es la fuente de verdad; el frontend solo muestra los requisitos y la fortaleza.

**Mensajes de error implementados (español):**

- “La contraseña debe tener al menos 12 caracteres.”
- “La contraseña no puede tener más de 64 caracteres.”
- “La contraseña debe incluir al menos una mayúscula.”
- “La contraseña debe incluir al menos una minúscula.”
- “La contraseña debe incluir al menos un número.”
- “La contraseña debe incluir al menos un símbolo (!@#$%^&*).”
- “La contraseña es demasiado común. Elige una diferente.”
- “La contraseña no puede contener tu nombre, correo o documento.”
- “La nueva contraseña debe ser diferente a la actual.”
- “La confirmación de la contraseña no coincide.”

**Cambios de interfaz implementados:**

1. `users/create` y `users/edit` muestran `minlength="12"`, `maxlength="64"` y el texto “Mínimo 12 caracteres, con mayúscula, minúscula, número y símbolo”.
2. Ambos formularios incluyen una lista de requisitos y un indicador de fortaleza mediante `data-password-policy`.
3. `resources/js/users/password-policy.js` evalúa la contraseña y actualiza el indicador; `users/validate.js` lo inicializa. La comprobación definitiva sigue en el servidor.
4. Se conserva `autocomplete="new-password"` y se permite pegar contraseñas para no romper gestores de contraseñas.

**Criterios de aceptación:**

- `Abcdef1234!` (11 caracteres) debe rechazarse por longitud.
- `abcdef12345!` debe rechazarse por falta de mayúscula.
- `ABCDEF12345!` debe rechazarse por falta de minúscula.
- `Abcdefghijk!` debe rechazarse por falta de número.
- `Abcdef123456` debe rechazarse por falta de símbolo.
- `Password123!` debe rechazarse por lista negra.
- Una contraseña que contenga el nombre o el correo del usuario debe rechazarse.
- `M4r!posaAzul27` debe aceptarse.
- En edición, dejar `password` vacío debe conservar la contraseña actual.
- La confirmación distinta debe rechazarse en creación y edición.

**Pruebas implementadas:**

- `tests/Feature/Seguridad/PasswordPolicyTest.php`: 17 pruebas con `DataProvider` para longitud, categorías, símbolo permitido, lista negra, datos personales, confirmación, reutilización, creación, edición sin contraseña y presencia de los requisitos en los formularios.
- `tests/js/users/password-policy.test.js`: 5 pruebas del evaluador de fortaleza, incluida una propiedad con 200 cadenas aleatorias.

**Verificación ejecutada:**

```bash
php artisan test tests/Feature/Seguridad/PasswordPolicyTest.php
php artisan test tests/Feature/Seguridad
npm test
vendor/bin/pint --test app/Rules/PasswordSegura.php app/Http/Controllers/UserController.php tests/Feature/Seguridad/PasswordPolicyTest.php
npm run build
```

> `PasswordPolicyTest`: 17 aprobadas. `tests/Feature/Seguridad`: 46 aprobadas. `npm test`: 82 aprobadas. `npm run build`: correcto, 125 módulos transformados. Suite completa: **699 aprobadas y 25 fallos preexistentes** del módulo de compras (`tipo_documento` obligatorio) y de `ServicioWebPropertiesTest`, ajenos a VUL-11.

---

## 4. Controles correctos observados

Estos puntos no requieren corrección inmediata, pero deben mantenerse:

- `CheckUserActivo` está añadido al grupo `web` y cierra sesiones de usuarios desactivados (`bootstrap/app.php:28`).
- `EnsureCliente` exige rol Cliente, cuenta activa, verificada y no bloqueada (`app/Http/Middleware/EnsureCliente.php:26-33`).
- Las rutas administrativas usan `auth` + `permission:*` en la mayoría de módulos.
- El login regenera la sesión (`LoginController.php:63`).
- El logout invalida la sesión y regenera el token CSRF (`LoginController.php:84-88`).
- Las contraseñas se almacenan con cast `hashed` y `Hash::make`.
- Los formularios POST revisados incluyen `@csrf`; las peticiones AJAX usan el meta token.
- No se encontraron consultas SQL crudas explotables en los flujos revisados: se usan bindings, `selectRaw` con constantes y ordenamientos con listas blancas.
- `resources/js/utils/escape-html.js` centraliza el escape de datos de BD; se aplica en ventas, devoluciones, cambio de aceite, ajustes, lavados, compras y productos.

---

## 5. Plan de acción recomendado

### Fase 0 — Inmediata

1. Actualizar Laravel, Guzzle, PSR-7, Symfony y CommonMark (VUL-05, implementado).
2. Añadir `throttle` y lockout al login administrativo (VUL-01, implementado).
3. Eliminar la credencial demo en producción y exigir `ADMIN_PASSWORD` fuerte (VUL-02, implementado).
4. Eliminar `/test-cloudinary` y su archivo de prueba (VUL-08, implementado).
5. Verificar que producción tenga `APP_DEBUG=false` y cookies seguras (VUL-07, implementado).

### Fase 1 — Corto plazo

1. Corregir los sinks XSS y restringir nombres de producto/proveedor (VUL-04, implementado).
2. Crear `.dockerignore` y reconstruir la imagen (VUL-06, implementado; validar la imagen en un entorno con Docker).
3. Restringir `acceso-usuarios` y `acceso-roles` a Administradores (VUL-03, implementado).
4. Proteger el rol Administrador y el último administrador (VUL-03, implementado).

### Fase 2 — Mediano plazo

1. Endurecer cookies y sesión (VUL-07, implementado).
2. Cabeceras defensivas y CSP base implementadas (VUL-04/VUL-10). VUL-10 queda parcial aceptado: retirar `unsafe-inline` de `script-src` y migrar estilos inline es un endurecimiento posterior que requiere pruebas de navegador.
3. Auditar intentos fallidos y cambios de roles/permisos (VUL-09, implementado).
4. Aplicar la política de contraseñas del panel administrativo (VUL-11, implementado): mínimo 12 caracteres, mayúscula, minúscula, número y símbolo, lista negra y bloqueo de datos personales.
5. Añadir pruebas automatizadas de seguridad.

---

## 6. Pruebas recomendadas

### Backend

- El seeder no debe crear el administrador demo fuera de `local`/`testing`.
- El seeder debe rechazar una `ADMIN_PASSWORD` débil o común.
- Login con credenciales inválidas repetidas debe devolver 429 o bloqueo.
- Login de cuenta bloqueada debe rechazarse.
- Login exitoso debe reiniciar `failed_attempts`.
- Un login fallido debe guardar IP y user-agent en auditoría.
- Un acceso exitoso tras un bloqueo vencido debe registrar `cuenta desbloqueada`.
- En producción, el arranque debe fallar con `APP_DEBUG=true` o cookie de sesión insegura.
- `/test-cloudinary` debe responder 404 y el archivo `public/test.jpg` no debe existir.
- Usuario con `acceso-usuarios` no debe poder asignarse Administrador si no lo es.
- Usuario con `acceso-roles` no debe poder modificar el rol Administrador.
- No se debe poder eliminar o desactivar al último Administrador.
- Rutas administrativas sin permiso deben devolver 403.
- Crear o editar un usuario con contraseña de menos de 12 caracteres debe fallar.
- La contraseña debe rechazarse si no incluye mayúscula, minúscula, número o símbolo.
- La contraseña debe rechazarse si es común o contiene nombre, correo o documento del usuario.
- La contraseña debe rechazarse si no coincide con `password_confirmation`.
- Una contraseña válida de 12 o más caracteres debe aceptarse en `users.store` y `users.update`.
- En `users.update`, dejar la contraseña vacía debe conservar la contraseña actual.

### Frontend

- Nombres de producto con `<script>` deben mostrarse como texto.
- Razones sociales con comillas no deben romper atributos `onclick`.
- Resultados de búsqueda no deben interpretar HTML.
- El formulario de usuario debe mostrar el mínimo de 12 caracteres y los requisitos de mayúscula, minúscula, número y símbolo.
- Los campos de contraseña deben conservar `autocomplete="new-password"` y permitir pegar desde un gestor de contraseñas.

### Infraestructura

```bash
composer audit --no-dev
npm audit --omit=dev
docker build -t carwash .
docker run --rm carwash find /var/www/html -maxdepth 2 -name ".env"
```

---

## 7. Archivos clave revisados

- `routes/web.php`
- `bootstrap/app.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Cuenta/LoginController.php`
- `app/Http/Controllers/UserController.php`
- `app/Http/Controllers/RoleController.php`
- `app/Http/Controllers/UserToggleController.php`
- `app/Http/Controllers/ProductoController.php`
- `app/Http/Controllers/ProveedorController.php`
- `app/Http/Middleware/EnsureCliente.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `app/Support/ProductionSecurityGuard.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/LoginLockoutService.php`
- `app/Services/AuditService.php`
- `app/Models/User.php`
- `config/app.php`
- `config/session.php`
- `database/migrations/2026_10_25_000000_add_client_context_to_registros_auditoria_table.php`
- `.env`
- `.env.example`
- `Dockerfile`
- `start.sh`
- `database/seeders/AuthSeeder.php`
- `database/migrations/2026_10_03_000002_add_cuenta_fields_to_users_table.php`
- `resources/js/ventas/create.js`
- `resources/js/devoluciones/create.js`
- `resources/js/cambio-aceite/shared.js`
- `resources/js/cambio-aceite/create.js`
- `resources/js/compras/buscar-productos.js`
- `resources/js/compras/buscar-proveedores.js`
- `resources/js/compras/create.js`
- `resources/js/compras/edit.js`
- `resources/js/ajustes/create.js`
- `resources/js/lavados/shared.js`
- `resources/js/productos/index.js`
- `composer.json`
- `composer.lock`

---

## 8. Nota de alcance

Este documento es un informe de auditoría y no aplica cambios de código. El árbol de trabajo del repositorio puede contener modificaciones ajenas a esta revisión; deben revisarse y confirmarse antes de implementar las soluciones propuestas.
