# Tareas — Módulo Proveedores (CRUD)

Todas las tareas completadas. Trazabilidad a `.kiro/specs/proveedor-crud/requirements.md`.

## 1. Migración y modelo
- [x] 1.1 Crear `database/migrations/2026_09_25_000001_create_proveedores_table.php` con `id`, `char('ruc', 11)->unique()`, `razon_social`, `direccion`/`estado_tributario`/`condicion` nulables, `char('estado', 1)->default('1')`, `timestamps` e índices — _Requirements: 25-29_
- [x] 1.2 Crear `app/Models/Proveedor.php` con `$table = 'proveedores'`, `HasFactory`, `$fillable`, `casts.estado = integer` y mutador `setRucAttribute` que elimina los no dígitos — _Requirements: 20, 21_
- [x] 1.3 Añadir `DataNormalizer::normalizarRuc()` — _Requirement: 20_

## 2. Controlador
- [x] 2.1 Implementar `index` con `orderBy('razon_social')->paginate(10)` — _Requirement: 1_
- [x] 2.2 Implementar `create` y `edit` — _Requirements: 5, 24_
- [x] 2.3 Implementar `store` con las reglas compartidas y los mensajes en español — _Requirements: 6-13, 18, 22, 23_
- [x] 2.4 Implementar `update` ignorando la unicidad del propio registro (`unique:proveedores,ruc,$id`) — _Requirements: 19, 22_
- [x] 2.5 Implementar `destroy` — _Requirement: 24_

## 3. Rutas y permisos
- [x] 3.1 Registrar el resource en `routes/web.php` con `->parameters(['proveedores' => 'proveedor'])` dentro de `permission:acceso-proveedores` — _Requirements: 32, y trampa del singularizador_
- [x] 3.2 Añadir `acceso-proveedores` a `PermissionSeeder` (el rol `Administrador` lo recibe vía `AuthSeeder::syncPermissions`) — _Requirements: 30, 31_

## 4. Vistas
- [x] 4.1 `proveedores/index.blade.php`: 7 columnas, `overflow-x-auto`, `py-6`/`py-8`, badges de estado, paginación, `data-confirm` — _Requirements: 2-5_
- [x] 4.2 `proveedores/create.blade.php`: RUC con `maxlength`/`data-length`/`data-filter="digits"`/`data-validate-length="11"`, `select` de estado, errores inline — _Requirements: 9, 14, 17, 23_
- [x] 4.3 `proveedores/edit.blade.php`: idéntico con `old()` y `$proveedor` — _Requirements: 15, 17, 23_

## 5. Frontend
- [x] 5.1 Crear `resources/js/proveedores/validate.js` con `Validation.validate(form)` sobre `#form-proveedor` — _Requirement: 16_
- [x] 5.2 Registrar el entry en `vite.config.js` — _AGENTS.md: módulo usado por una vista debe estar en el array `input`_

## 6. Navegación y auditoría
- [x] 6.1 Añadir `'proveedores.*'` a `$gestionAdministrativaActive` y `'acceso-proveedores'` a los dos `@canany` — _Requirement: 33_
- [x] 6.2 Añadir el enlace "Proveedores" (escritorio y móvil) tras "Automotores" — _Requirement: 33_
- [x] 6.3 Registrar `Proveedor::class` en `AuditServiceProvider` — _Requirement: 34_

## 7. Datos de prueba
- [x] 7.1 `ProveedorFactory` con RUC de 11 dígitos y estado `inactivo()` — _Tests_
- [x] 7.2 `ProveedorSeeder` idempotente y registro en `DatabaseSeeder` — _Tests_

## 8. Tests — `tests/Feature/ProveedorTest.php` (26 tests)
- [x] 8.1 RUC: acepta 11 dígitos; rechaza 10, 12, letras, vacío y duplicado — _Requirements: 6, 7_
- [x] 8.2 Razón social: rechaza vacía; acepta puntos, comas, `&`, comillas y guion — _Requirement: 8_
- [x] 8.3 Opcionales: `direccion`, `estado_tributario` y `condicion` vacíos se guardan como `null` — _Requirements: 9, 10, 11_
- [x] 8.4 Estado: rechaza `2`, rechaza `A`, rechaza vacío, acepta `0` — _Requirement: 12_
- [x] 8.5 Update: persiste cambios, permite el mismo RUC, rechaza el RUC de otro proveedor y RUC inválido — _Requirements: 13, 19_
- [x] 8.6 Destroy elimina el registro — _Requirement: 24_
- [x] 8.7 Vistas: index muestra datos y pagina de 10 en 10; create/edit abren; el form expone las reglas de frontend del RUC — _Requirements: 1, 5, 14, 15_
- [x] 8.8 Permisos: sin `acceso-proveedores` se redirige al dashboard con flash de error; visitante al login — _Requirements: 31, 32_
- [x] 8.9 Modelo: `setRucAttribute` normaliza `" 205-123-45678 "` a `20512345678` — _Requirement: 20_
- [x] 8.10 Seeder: genera 6 proveedores con RUC de 11 dígitos y estado ∈ {0, 1} — _Tests_

## 9. Siguiente paso (no implementado)
- [ ] 9.1 Migración `productos.proveedor_id` + `Proveedor::productos()`
- [ ] 9.2 Pantalla de ingreso de mercadería (compra a proveedor) con entrada de stock al kardex
- [ ] 9.3 Servicio de consulta de RUC que rellene `razon_social`, `direccion` y `estado_tributario`
