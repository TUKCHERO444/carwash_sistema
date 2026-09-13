# AGENTS.md

Laravel 12 (PHP 8.2) car-wash POS/ERP ("sistema de lavado") for the Peruvian market. Blade + Tailwind CSS 4 (Vite 7, dark mode), SQLite by default in dev (`database/database.sqlite`; local `.env` may override), tests always run on in-memory SQLite. The whole codebase — controllers, views, `.kiro/` docs, commit messages — is in Spanish; keep it that way.

## Commands

- `composer run dev` — dev server: `php artisan serve` + `queue:listen` + `pail` logs + `vite` (concurrently). Anything touching frontend needs Vite running.
- `composer run test` — `config:clear` then `php artisan test` (PHPUnit 11, in-memory SQLite per `phpunit.xml`; no DB setup required).
- `npm run test` — Vitest for property tests under `tests/js/**` (Node env, no DOM); run these too when editing `resources/js/`.
- `vendor/bin/pint` — code formatter (Laravel default preset).
- `php artisan migrate:fresh --seed` — reset + demo data. Default login from `AuthSeeder`: `admin@sistema.com` / `password`.
- `php artisan data:clean` — idempotent, non-destructive normalizer for legacy rows (see `app/Console/Commands/DataCleanCommand.php`, uses `app/Services/DataNormalizer.php`). Run after schema changes that add normalization.
- Health check route: `GET /ping`.
- No CI, no linter beyond Pint; PHPUnit gives broad coverage.

## Frontend (Vite)

JS is strictly modularized: per-business-module folders under `resources/js/<module>/` (e.g. `ventas/create.js`). Conventions from `.kiro/specs/js-modularization`:

- Every module used by a view MUST be added to the `input` array in `vite.config.js` AND loaded via `@vite([...])` at the end of `@section('content')` in the Blade view. Missed entries silently 404 in prod build.
- Blade views must NOT contain inline behavior `<script>` blocks. The only allowed inline script is data init, e.g. `window.productos = @json($productos);`, and it must come before the `@vite` directive.
- Frontend libs: SweetAlert2 (confirmation modals — see `resources/js/confirmations.js`) and Chart.js (dashboard).

## Routing gotcha

Static/AJAX routes MUST be declared BEFORE resource routes, or Laravel binds them as model parameters (explicit comments in `routes/web.php`, e.g. `clientes.consultarDni`, `lavados.confirmados`, product `buscar`). Always follow this when adding routes.

## Backend architecture

- **RBAC (spatie/laravel-permission).** Routes are guarded by `permission:` with Spanish names (`acceso-ventas`, `acceso-caja`, `historial-caja`, `acceso-auditoria`, ...). New permissions must be added to `database/seeders/PermissionSeeder.php`. `AppServiceProvider` gives the `Administrador` role `Gate::before` bypass of everything.
- **Audit.** `AuditServiceProvider` + `AuditModelObserver` write to `registros_auditoria` (via `AuditService`) for every model registered in the provider's `$modelos` list. New auditable model → add it to that list or it silently won't be audited.
- **Caja coupling.** `VentaController`/`CambioAceiteController` etc. refuse to save when no caja is active: they flash `error_caja` and the views show a "caja requerida" modal pointing to the caja panel. Keep `CajaService` as the single entrypoint for `caja_id` assignment (`app/Services/CajaService.php`).
- **External APIs.** `DniApiService` (DNI lookup) and `AutomotorApiService` (placa lookup) hit `api.json.pe` and share `VEHICLE_API_TOKEN` (`config/services.php`). They are resilient by design — return `[]`/null on any failure and never block forms — so do not make forms depend on them.
- **Cloudinary** for image uploads (productos, lavados, cambio-aceite). Needs `CLOUDINARY_URL` in `.env` (not in `.env.example`).
- **Input normalization** is centralized in `app/Services/DataNormalizer.php` (DNI, phone, plate, names, alphanumerics). Reuse it in controllers instead of duplicating regex.

## Zona horaria (crítica — Perú)

- **Regla global:** el sistema opera en hora de Perú (`America/Lima`, UTC−5, sin horario de verano). El único punto de configuración es `config/app.php` → `'timezone' => 'America/Lima'`; Laravel aplica `date_default_timezone_set()` desde ahí en el bootstrap, así que cubre `now()`/Carbon, `created_at`/`updated_at`, la auditoría, las migraciones y la hora de operación que muestran las vistas. NO dependas de `php.ini` (la máquina lo tiene en `Europe/Berlin` y Laravel lo ignora).
- Nunca reviertas a `'UTC'` ni uses la hora local del servidor: eso desfasa fechas/horas ~5 h y rompe reportes, `toDateString()` y las marcas de asistencia. Si un módulo futuro usa fechas, genera los tiempos con `now()`/Carbon de la app — nunca `date('...')` crudo — y compara fechas con `->toDateString()`.
- Las pruebas (PHPUnit sobre SQLite en memoria y los tests JS) usan `now()` de la app y heredan automáticamente `America/Lima`; verifica siempre con tiempos relativos, no literales fijos.

## Tests

- PHPUnit classes extending `Tests\TestCase` with `RefreshDatabase`. Permissions used in a test must be created first (`Permission::firstOrCreate(...)`) then `givePermissionTo(...)` to a factory user.
- External APIs are mocked with `$this->mock(DniApiService::class)` / `AutomotorApiService`; Cloudinary calls are mocked too (see `*CloudinaryTest.php`). Never hit real networks or Cloudinary.
- Property tests (both `tests/Feature/*PropertyTest.php` and `tests/js/*.property.test.js`) run 100+ random iterations; JS ones use `fast-check`. Label each with a `// Feature: <spec>, Property {N}: <description>` comment.

## Spec-driven workflow

`.kiro/specs/<feature>/{requirements,design,tasks}.md` documents each implemented feature (workflow `requirements-first`). Before implementing or modifying a feature, read its spec's `tasks.md` — it lists expected property tests and traces back to requirements. UI pattern guides live in `.kiro/guia-modo-oscuro.md` and `.kiro/reajuste-listados.md`.

## UI conventions (enforced by the two guides above)

- Dark mode uses semantic utility classes defined in `resources/css/app.css` (`@theme` tokens): `.bg-surface`, `.input-main`, `.label-main`, `.text-primary`, `.text-secondary`, `.border-main`, `.divide-main`. Use slate tones (`#020617`, `#0f172a`, `#1e293b`, ...), never pure black.
- Listado tables: `paginate(10)` (not 15) in controllers; header cells `py-6`, body cells `py-8`; table wrapper uses `overflow-x-auto` (never `overflow-hidden`) for mobile scroll.
- The shipped `README.md` is unmodified Laravel boilerplate — ignore it.