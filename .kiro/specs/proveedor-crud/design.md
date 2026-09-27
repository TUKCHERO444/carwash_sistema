# Diseño — Módulo Proveedores (CRUD)

## 1. Esquema

`database/migrations/2026_09_25_000001_create_proveedores_table.php`

```php
Schema::create('proveedores', function (Blueprint $table) {
    $table->id();                              // PK autoincremental
    $table->char('ruc', 11)->unique();         // RUC: 11 dígitos, único
    $table->string('razon_social', 150);
    $table->string('direccion', 200)->nullable();
    $table->string('estado_tributario', 100)->nullable();
    $table->string('condicion', 100)->nullable();
    $table->char('estado', 1)->default('1');  // char(1): solo un número (1/0)
    $table->timestamps();

    $table->index('razon_social');
    $table->index('estado');
});
```

Notas de diseño:

- `ruc` es `char(11)` porque siempre ocupa 11 dígitos; el índice único impide
  registrar dos veces al mismo proveedor.
- `estado` es `char(1)` con default `'1'`. La regla `size:1` + `in:0,1` en el
  controlador garantiza que jamás se guarde una letra ni un número fuera de {0, 1}.
- `estado_tributario` y `condicion` son texto libre de longitud acotada porque su
  contenido proviene de la API de consulta de RUC (paso 2 del flujo).

## 2. Modelo

`app/Models/Proveedor.php`

```php
class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';   // el pluralizador daría "proveedors"

    protected $fillable = [
        'ruc', 'razon_social', 'direccion',
        'estado_tributario', 'condicion', 'estado',
    ];

    protected $casts = ['estado' => 'integer'];

    public function setRucAttribute(?string $value): void
    {
        $this->attributes['ruc'] = preg_replace('/\D/', '', (string) $value);
    }
}
```

**Trampa del pluralizador:** `Str::plural('proveedor')` devuelve `proveedors` y
`Str::singular('proveedores')` devuelve `proveedore`. Por eso se declaran
explícitamente `protected $table` y `->parameters(['proveedores' => 'proveedor'])`
en las rutas (igual que hace `Automotor` con `automotores`).

El mutador de `ruc` es la segunda barrera: aunque el input ya pasó la validación,
 nunca se persiste un RUC con guiones o espacios.

## 3. Controlador

`app/Http/Controllers/ProveedorController.php`

Constantes de reglas y mensajes, y un método `reglas(?int $ignoreId = null)`
compartido entre `store` y `update` (patrón equivalente a `DESCRIPCION_RULES` en
`ProductoController`):

```php
private const RUC_RULE = 'digits:11';

private const RAZON_SOCIAL_RULE = 'regex:/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ .,&()\'"\-\/+]+$/u';
private const DIRECCION_RULE     = 'regex:/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ .,\-#]+$/u';

private const MENSAJES = [
    'ruc.required' => 'El RUC es obligatorio.',
    'ruc.digits'   => 'El RUC debe tener exactamente 11 dígitos numéricos.',
    'ruc.unique'   => 'Ya existe un proveedor registrado con este RUC.',
    'razon_social.required' => 'La razón social es obligatoria.',
    'razon_social.regex'    => 'La razón social solo admite letras, números y signos básicos.',
    'estado.required' => 'El estado es obligatorio.',
    'estado.in'       => 'El estado debe ser 1 (activo) u 0 (inactivo).',
];

private function reglas(?int $ignoreId = null): array
{
    $unicidad = $ignoreId ? 'unique:proveedores,ruc,'.$ignoreId : 'unique:proveedores,ruc';

    return [
        'ruc'              => ['required', 'string', self::RUC_RULE, $unicidad],
        'razon_social'     => ['required', 'string', 'max:150', self::RAZON_SOCIAL_RULE],
        'direccion'        => ['nullable', 'string', 'max:200', self::DIRECCION_RULE],
        'estado_tributario'=> ['nullable', 'string', 'max:100', 'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+$/u'],
        'condicion'        => ['nullable', 'string', 'max:100'],
        'estado'           => ['required', 'size:1', 'in:0,1'],
    ];
}
```

`digits:11` cubre a la vez "solo números" y "exactamente 11" en una sola regla.

Métodos: `index` (`orderBy('razon_social')->paginate(10)`), `create`, `store`,
`edit`, `update`, `destroy`.

## 4. Rutas

`routes/web.php`, dentro del grupo `Route::middleware(['auth'])`, antes del grupo
de `contenido-web`:

```php
Route::middleware('permission:acceso-proveedores')->group(function () {
    Route::resource('proveedores', ProveedorController::class)
        ->except(['show'])
        ->parameters(['proveedores' => 'proveedor']);
});
```

No hay rutas estáticas/AJAX en el módulo, pero el resource debe ir **después** de
cualquier ruta estática futura (regla global de `routes/web.php`).

## 5. Frontend

### Blade

- `resources/views/proveedores/index.blade.php` — tabla con `overflow-x-auto`,
  cabeceras `py-6`, celdas `py-8`, badges verde/rojo para el estado, paginación
  `{{ $proveedores->links() }}`, confirmación de borrado con `data-confirm`
  (SweetAlert2 vía `resources/js/confirmations.js`, cargado globalmente).
- `resources/views/proveedores/create.blade.php` y `edit.blade.php` — formularios
  con `id="form-proveedor"`, clases semánticas (`input-main`, `label-main`,
  `text-secondary`, `bg-surface`, `border-main`) y `@error(...)` inline.

Input del RUC (idéntico en create y edit):

```blade
<input type="text" id="ruc" name="ruc" inputmode="numeric"
       maxlength="11" data-length="11"
       data-filter="digits" data-validate-length="11" required>
```

- `data-filter="digits"` → `input-filters.js` (importado por `app.js`) borra en
  vivo todo lo que no sea dígito y recorta a 11.
- `data-validate-length="11"` → `Validation.validate()` bloquea el submit y muestra
  "Debe tener exactamente 11 caracteres." si la longitud no coincide.

### JS

`resources/js/proveedores/validate.js` — mismo patrón que
`resources/js/categorias/validate.js`:

```js
import { Validation } from '../utils/validation.js';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-proveedor');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!Validation.validate(form)) e.preventDefault();
        });
    }
});
```

`vite.config.js` — entry agregada junto a Marcas:

```js
// Proveedores
'resources/js/proveedores/validate.js',
```

Las vistas cargan el módulo con `@vite('resources/js/proveedores/validate.js')`
al final de `@section('content')`.

## 6. Permisos y navegación

- `database/seeders/PermissionSeeder.php`: nuevo permiso `acceso-proveedores`
  (bloque "Proveedores"). `AuthSeeder` hace
  `$adminRole->syncPermissions(Permission::all())`, por lo que el rol
  `Administrador` lo recibe automáticamente. `AppServiceProvider` además da
  `Gate::before` bypass total a ese rol.
- `resources/views/layouts/app.blade.php`:
  - `$gestionAdministrativaActive` incluye `'proveedores.*'`.
  - Los dos `@canany([...])` del dropdown "Gestión Administrativa" (escritorio y
    móvil) incluyen `'acceso-proveedores'`.
  - Enlace "Proveedores" tras "Automotores" en ambos menús, con su propio
    `@can('acceso-proveedores')` y resaltado activo por
    `request()->routeIs('proveedores.*')`.
- `app/Providers/AuditServiceProvider.php`: `Proveedor::class` agregado a `$modelos`
  para que toda alta/edición/baja quede en `registros_auditoria`.

## 7. Datos de prueba

- `database/factories/ProveedorFactory.php` — RUC aleatorio de 11 dígitos
  (`numerify('###########')`), razón social con `company()`, estado `1`, y estado
  `inactivo()`.
- `database/seeders/ProveedorSeeder.php` — 6 proveedores ficticios, idempotente
  con `firstOrCreate` por `ruc`, normalizados con `DataNormalizer::normalizarRuc()`.
  Registrado en `DatabaseSeeder` justo después de `MarcaSeeder`.
- `app/Services/DataNormalizer.php` — nuevo `normalizarRuc(?string): ?string`
  que devuelve los dígitos solo si son exactamente 11, alineado con
  `normalizarDni` y `normalizarTelefono`.

## 8. Convenciones de UI aplicadas

- Clases semánticas de dark mode (`.bg-surface`, `.input-main`, `.label-main`,
  `.text-primary`, `.text-secondary`, `.border-main`, `.divide-main`); nada de
  negro puro.
- Listados: `paginate(10)`, cabeceras `py-6`, celdas `py-8`, wrapper
  `overflow-x-auto`.
- Iconos Heroicons outline inline (`viewBox="0 0 24 24"`), igual que el resto del
  panel.
- `estado` en el listado se muestra con badge "Activo"/"Inactivo" en vez del dígito
  crudo, para que el usuario no tenga que memorizar el código.
