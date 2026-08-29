# Flujo de Creación de Productos con Cloudinary

## Análisis Arquitectónico Completo del CRUD de Imágenes

---

## 1. Stack Tecnológico

| Componente | Tecnología |
|---|---|
| Framework | Laravel 12 |
| Paquete Cloudinary | `cloudinary-labs/cloudinary-laravel: ^3.0` |
| Almacenamiento local | `storage/app/public/` (fallback) |
| DSN | `CLOUDINARY_URL` en `.env` |
| BD | SQLite / MySQL (columna `foto` tipo `text`) |

---

## 2. Estructura de Archivos del Módulo

```
app/
  Http/Controllers/ProductoController.php    ← CRUD + subida/eliminación Cloudinary
  Models/Producto.php                         ← Modelo con accessor foto_url
config/filesystems.php                        ← Disco 'cloudinary' configurado
database/migrations/
  2024_01_01_000004_create_productos_table.php          ← tabla base
  2026_04_30_000001_add_activo_foto_to_productos_table.php  ← agrega columna foto
  2026_05_16_143507_adjust_foto_column_in_productos_table.php ← cambia a tipo text
resources/
  views/productos/
    create.blade.php        ← formulario de creación con input file + preview
    edit.blade.php          ← formulario de edición con preview de imagen actual y nueva
    index.blade.php         ← listado con miniatura de foto
  js/productos/
    shared.js               ← lógica de preview compartida
    create.js               ← inicializa preview en create
    edit.js                 ← inicializa preview en edit
    index.js                ← toggle status + búsqueda AJAX
routes/web.php                                ← rutas protegidas con permiso acceso-inventario
tests/Feature/ProductoCloudinaryTest.php      ← tests con mock de Cloudinary
```

---

## 3. Flujo Completo: Creación de Producto (Store)

### 3.1 Capa Vista — `create.blade.php`

- Formulario con `enctype="multipart/form-data"`
- Input file: `<input type="file" name="foto" accept="image/jpeg,image/jpg,image/png,image/webp">`
- Bloque de preview oculto: `<div id="bloque-preview" class="hidden">`
- Carga `@vite('resources/js/productos/create.js')`

### 3.2 Capa JavaScript — `create.js` + `shared.js`

```js
// shared.js
export function initFotoPreview(inputId, previewId, bloqueId) {
    const input = document.getElementById(inputId);
    input.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (event) {
            document.getElementById(previewId).src = event.target.result;
            document.getElementById(bloqueId).classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });
}

// create.js
import { initFotoPreview } from './shared.js';
document.addEventListener('DOMContentLoaded', () => {
    initFotoPreview('foto', 'preview-foto', 'bloque-preview');
});
```

**Comportamiento:** Cuando el usuario selecciona un archivo, se activa un `FileReader` que muestra el preview local antes de enviar el formulario.

### 3.3 Capa Controlador — `ProductoController::store()`

```
1. Validación:
   - nombre: required, string, max:150
   - precio_compra: required, numeric, >0
   - precio_venta: required, numeric, >0
   - stock: required, integer, min:0
   - inventario: required, integer, min:0
   - foto: nullable, image, mimes:jpg,jpeg,png,webp, max:2048
   - categoria_id: nullable, integer, exists:categorias,id

2. Subida a Cloudinary (si hay foto):
   $request->hasFile('foto') → true
   Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath())
   Retorna: $result['secure_url']  ← URL pública de Cloudinary

3. Transacción DB:
   - Producto::create([...datos..., 'foto' => $fotoUrl])
   - Si categoria_id: Categoria::increment('contador_productos')

4. Redirección:
   redirect()->route('productos.index')->with('success', ...)
```

**Patrón clave:** La subida a Cloudinary ocurre **FUERA de la transacción** de BD para evitar enviar a Cloudinary archivos que luego se descarten por un rollback. Primero se sube, y si falla la BD, la imagen queda huérfana en Cloudinary (trade-off aceptado).

---

## 4. Flujo de Edición (Update)

```
1. Validación (misma que store)

2. Si se sube nueva foto:
   a. Eliminar imagen anterior de Cloudinary:
      - Extraer public_id: pathinfo(explode('/', $url)[-1], PATHINFO_FILENAME)
      - Cloudinary::uploadApi()->destroy($publicId)
   b. Subir nueva imagen a Cloudinary:
      - Cloudinary::uploadApi()->upload($file->getRealPath())
      - Asignar $data['foto'] = $result['secure_url']

3. Si NO se sube nueva foto:
   - No incluir 'foto' en $data → se conserva el valor anterior

4. Transacción DB:
   - $producto->update($data)
   - Si cambió categoria_id: ajustar contadores (decrement/increment)
```

**Extracción de public_id desde URL de Cloudinary:**

```
URL: https://res.cloudinary.com/drm3cfpnm/image/upload/v1/mi_imagen.jpg
                            public_id = mi_imagen
Algoritmo: explode('/', url) → último segmento → pathinfo(filename, PATHINFO_FILENAME)
```

---

## 5. Flujo de Eliminación (Destroy)

```
1. FUERA de transacción: eliminar imagen de Cloudinary
   - Si foto comienza con 'http':
     - Extraer public_id
     - Cloudinary::uploadApi()->destroy($publicId)
   - Si no (almacenamiento local):
     - Storage::disk('public')->exists() → delete()

2. DENTRO de transacción:
   - Si tiene categoria_id: decrement contador
   - $producto->delete()
```

**Justificación:** Las operaciones de sistema de archivos/API externa no pueden revertirse, por lo que se ejecutan antes de la transacción. Si el delete de BD falla, la imagen ya no existe en Cloudinary (inconsistencia menor aceptada).

---

## 6. Modelo — Accessor `foto_url`

```php
// Producto.php
public function getFotoUrlAttribute(): ?string
{
    if (!$this->foto) return null;           // Sin imagen
    if (str_starts_with($this->foto, 'http')) return $this->foto;  // Cloudinary
    return asset('storage/' . $this->foto); // Local storage
}
```

**Propósito:** Centralizar la lógica de resolución de URLs. En las vistas se usa `$producto->foto_url` en lugar de `$producto->foto`, desacoplando la presentación del almacenamiento real.

---

## 7. Migraciones de la Columna `foto`

| Migración | Cambio |
|---|---|
| `2026_04_30_...` | Agrega `foto` como `string (255)` nullable |
| `2026_05_16_...` | Cambia a `text` nullable (las URLs de Cloudinary pueden exceder 255 chars) |

---

## 8. Configuración de Cloudinary

### 8.1 Dependencia (`composer.json`)
```json
"cloudinary-labs/cloudinary-laravel": "^3.0"
```

### 8.2 Disco de filesystem (`config/filesystems.php:63-66`)
```php
'cloudinary' => [
    'driver' => 'cloudinary',
    'url'    => env('CLOUDINARY_URL'),
],
```

### 8.3 Variable de entorno (`.env`)
```
CLOUDINARY_URL=cloudinary://api_key:api_secret@cloud_name
```

El paquete auto-descubre la configuración a través de `CLOUDINARY_URL`. No requiere config adicional en `config/services.php`.

---

## 9. Rutas (Protegidas)

```php
Route::middleware('permission:acceso-inventario')->group(function () {
    Route::get('productos/buscar', ...)->name('productos.buscar');
    Route::patch('productos/{producto}/stock', ...)->name('productos.updateStock');
    Route::patch('productos/{producto}/toggle-status', ...)->name('productos.toggleStatus');
    Route::resource('productos', ProductoController::class)->except(['show']);
});
```

Todas las rutas de productos requieren el permiso `acceso-inventario` (Spatie Laravel Permission).

---

## 10. Tests — Estrategia de Mock (`ProductoCloudinaryTest.php`)

```php
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

// Mock de subida
$mockResponse = \Mockery::mock(\Cloudinary\Api\ApiResponse::class);
$mockResponse->shouldReceive('offsetGet')
    ->with('secure_url')
    ->andReturn('https://res.cloudinary.com/.../test_product.jpg');

Cloudinary::shouldReceive('uploadApi->upload')
    ->once()
    ->andReturn($mockResponse);

// Mock de eliminación
Cloudinary::shouldReceive('uploadApi->destroy')
    ->once()
    ->with('old_image')
    ->andReturn($mockDestroyResponse);
```

**Tres tests implementados:**
1. `it_uploads_image_to_cloudinary_when_creating_a_producto` — verifica que se llame a `upload`, que se guarde la URL en BD, que `foto_url` retorne la URL correcta
2. `it_deletes_old_cloudinary_image_when_updating_with_new_one` — verifica `destroy` de la antigua + `upload` de la nueva
3. `it_deletes_cloudinary_image_when_destroying_producto` — verifica `destroy` al eliminar

---

## 11. Patrón Arquitectónico (Resumen para Replicar)

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENTE (Browser)                     │
│  create.blade.php / edit.blade.php                      │
│  ↓ input[type="file"] + JS FileReader preview           │
│  ↓ FormData con enctype="multipart/form-data"           │
├─────────────────────────────────────────────────────────┤
│                 LARAVEL CONTROLLER                      │
│  ProductoController.php                                 │
│                                                         │
│  store():                                               │
│    1. $request->validate([...])                         │
│    2. if ($request->hasFile('foto')) {                  │
│         Cloudinary::uploadApi()->upload($file->path)    │
│         → $secure_url                                   │
│       }                                                 │
│    3. DB::transaction(fn() => Producto::create(         │
│         [...datos..., 'foto' => $secure_url]            │
│       ))                                                │
│                                                         │
│  update():                                              │
│    1. Validar                                           │
│    2. if (nueva foto) {                                 │
│         destroy(old_public_id)                          │
│         upload(nueva) → $secure_url                     │
│       }                                                 │
│    3. DB::transaction(fn() => update())                 │
│                                                         │
│  destroy():                                             │
│    1. destroy(public_id) ← FUERA de transacción         │
│    2. DB::transaction(fn() => delete())                 │
├─────────────────────────────────────────────────────────┤
│                     MODEL                               │
│  Producto.php                                           │
│  - $fillable = ['foto', ...]                            │
│  - getFotoUrlAttribute(): resuelve Cloudinary vs local   │
│  - $casts = ['precios' => decimal, 'activo' => boolean] │
├─────────────────────────────────────────────────────────┤
│                    CLOUDINARY API                       │
│  upload($realPath) → { secure_url }                     │
│  destroy($publicId) → { result }                        │
│                                                         │
│  URL → public_id:                                       │
│    pathinfo(end(explode('/', $url)), PATHINFO_FILENAME)  │
├─────────────────────────────────────────────────────────┤
│                 BASE DE DATOS                           │
│  productos.foto (text, nullable)                        │
│  Almacena: "https://res.cloudinary.com/..."             │
└─────────────────────────────────────────────────────────┘
```

---

## 12. Checklist para Replicar en Otro Sistema

### 12.1 Instalación
```bash
composer require cloudinary-labs/cloudinary-laravel:^3.0
```

### 12.2 Configurar `.env`
```
CLOUDINARY_URL=cloudinary://api_key:api_secret@cloud_name
```

### 12.3 Migración
```php
// Tabla existente → agregar columna foto
Schema::table('tu_tabla', function (Blueprint $table) {
    $table->text('foto')->nullable();
});
```

### 12.4 Modelo
```php
protected $fillable = ['foto'];
protected $casts = ['foto' => 'string'];

public function getFotoUrlAttribute(): ?string
{
    if (!$this->foto) return null;
    if (str_starts_with($this->foto, 'http')) return $this->foto;
    return asset('storage/' . $this->foto);
}
```

### 12.5 Controlador — Store
```php
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

$fotoUrl = null;
if ($request->hasFile('foto')) {
    $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
    $fotoUrl = $result['secure_url'];
}

DB::transaction(function () use ($validated, $fotoUrl) {
    TuModelo::create([...datos..., 'foto' => $fotoUrl]);
});
```

### 12.6 Controlador — Update
```php
if ($request->hasFile('foto')) {
    if ($modelo->foto && str_starts_with($modelo->foto, 'http')) {
        $parts = explode('/', $modelo->foto);
        $publicId = pathinfo(end($parts), PATHINFO_FILENAME);
        Cloudinary::uploadApi()->destroy($publicId);
    }
    $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
    $data['foto'] = $result['secure_url'];
}
$modelo->update($data);
```

### 12.7 Controlador — Destroy
```php
if ($modelo->foto && str_starts_with($modelo->foto, 'http')) {
    $parts = explode('/', $modelo->foto);
    $publicId = pathinfo(end($parts), PATHINFO_FILENAME);
    Cloudinary::uploadApi()->destroy($publicId);
}
$modelo->delete();
```

### 12.8 Vista — Create
```blade
<input type="file" name="foto" accept="image/jpeg,image/jpg,image/png,image/webp">
<div id="bloque-preview" class="hidden">
    <img id="preview-foto" src="#">
</div>
@vite('resources/js/create.js')
```

### 12.9 Vista — Edit
```blade
@if($modelo->foto)
    <img src="{{ $modelo->foto_url }}" alt="Actual">
@endif
<input type="file" name="foto" accept="image/jpeg,image/jpg,image/png,image/webp">
<div id="bloque-nueva" class="hidden">
    <img id="preview-nueva" src="">
</div>
@vite('resources/js/edit.js')
```

### 12.10 Tests
```php
Cloudinary::shouldReceive('uploadApi->upload')->once()->andReturn($mockResponse);
Cloudinary::shouldReceive('uploadApi->destroy')->once()->with('public_id')->andReturn($mockDestroyResponse);
```
