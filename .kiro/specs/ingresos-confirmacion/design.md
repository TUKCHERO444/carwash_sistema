# Documento de Diseño Técnico

## Feature: ingresos-confirmacion

---

## Visión General

Esta feature divide el flujo de registro de ingresos del taller mecánico en dos etapas separadas: **registro** (estado `pendiente`) y **confirmación** (estado `confirmado`). El objetivo es permitir que el operario registre rápidamente la entrada de un vehículo sin necesidad de completar el pago en ese momento, y luego confirme el pago desde un panel dedicado.

El cambio principal es la introducción de un campo `estado` en la tabla `ingresos` y la reorganización de las vistas y rutas del módulo:

- `/ingresos` → nueva **Tabla de Pendientes** (vista principal)
- `/ingresos/confirmados` → **Tabla de Confirmados** (vista secundaria, equivalente a la actual `ingresos.index`)
- `/ingresos/{id}/confirmar` → nueva acción de confirmación de pago

Los ingresos existentes en la base de datos se migran automáticamente al estado `confirmado` para preservar la integridad de los datos históricos.

---

## Arquitectura

El sistema sigue la arquitectura MVC de Laravel existente. No se introducen nuevas capas ni dependencias externas.

```mermaid
flowchart TD
    A[Operario] -->|GET /ingresos| B[IngresoController::index]
    B -->|filtra estado=pendiente| C[(ingresos DB)]
    B --> D[Vista: ingresos/pendientes]

    A -->|GET /ingresos/create| E[IngresoController::create]
    E --> F[Vista: ingresos/create]
    F -->|POST /ingresos| G[IngresoController::store]
    G -->|estado=pendiente| C
    G -->|redirect| D

    A -->|GET /ingresos/{id}/confirmar| H[IngresoController::confirmar]
    H --> I[Vista: ingresos/confirmar]
    I -->|POST /ingresos/{id}/confirmar| J[IngresoController::procesarConfirmacion]
    J -->|estado=confirmado + datos pago| C
    J -->|redirect| D

    A -->|GET /ingresos/confirmados| K[IngresoController::confirmados]
    K -->|filtra estado=confirmado| C
    K --> L[Vista: ingresos/confirmados]
```

### Decisiones de diseño

**Reutilización del controlador existente**: Se extiende `IngresoController` con nuevos métodos en lugar de crear un controlador separado. Esto mantiene la cohesión del módulo y evita duplicar la lógica de negocio compartida (búsqueda de servicios, manejo de fotos, etc.).

**Panel de confirmación como vista dedicada**: El `Panel_Confirmacion` es una vista nueva (`ingresos/confirmar.blade.php`) que combina el formulario de edición existente con los campos de pago y los botones de acción (confirmar, actualizar, eliminar). Esto evita modificar el formulario de creación y mantiene la separación de responsabilidades.

**Formulario de creación simplificado**: El formulario `create.blade.php` se modifica para eliminar los campos de pago (`metodo_pago`, `total`, `precio`, `monto_*`). El precio se sigue calculando en el frontend para mostrarlo al operario, pero no se envía al servidor — el servidor lo calcula en el `store`.

**Migración no destructiva**: Se agrega el campo `estado` con valor por defecto `pendiente` y se actualiza en la misma migración todos los registros existentes a `confirmado`. El `down()` revierte el campo sin pérdida de datos.

---

## Componentes e Interfaces

### Rutas nuevas y modificadas

```php
// Rutas existentes que se mantienen (sin cambios de URL)
Route::resource('ingresos', IngresoController::class);
// → GET  /ingresos          → index()       (ahora muestra pendientes)
// → GET  /ingresos/create   → create()      (formulario simplificado)
// → POST /ingresos          → store()       (crea con estado=pendiente)
// → GET  /ingresos/{id}     → show()        (detalle, sin cambios)
// → GET  /ingresos/{id}/edit → edit()       (edición, sin cambios)
// → PUT  /ingresos/{id}     → update()      (actualización, sin cambios)
// → DELETE /ingresos/{id}   → destroy()     (eliminación, sin cambios)

// Rutas nuevas
Route::get('/ingresos/confirmados', [IngresoController::class, 'confirmados'])
     ->name('ingresos.confirmados');

Route::get('/ingresos/{ingreso}/confirmar', [IngresoController::class, 'confirmar'])
     ->name('ingresos.confirmar');

Route::post('/ingresos/{ingreso}/confirmar', [IngresoController::class, 'procesarConfirmacion'])
     ->name('ingresos.procesarConfirmacion');
```

> **Nota de orden de rutas**: La ruta `/ingresos/confirmados` debe registrarse **antes** del resource para evitar que Laravel interprete `confirmados` como un parámetro `{ingreso}`.

### Métodos nuevos en `IngresoController`

| Método | HTTP | Ruta | Descripción |
|--------|------|------|-------------|
| `index()` | GET | `/ingresos` | Lista ingresos con `estado=pendiente`, paginados |
| `store()` | POST | `/ingresos` | Crea ingreso con `estado=pendiente`, sin campos de pago |
| `confirmados()` | GET | `/ingresos/confirmados` | Lista ingresos con `estado=confirmado`, paginados |
| `confirmar()` | GET | `/ingresos/{id}/confirmar` | Muestra el Panel_Confirmacion |
| `procesarConfirmacion()` | POST | `/ingresos/{id}/confirmar` | Valida pago, actualiza estado a `confirmado` |

Los métodos `edit()`, `update()`, `show()`, `destroy()`, `ticket()` y `buscarServicios()` no cambian.

### Vistas nuevas y modificadas

| Vista | Cambio | Descripción |
|-------|--------|-------------|
| `ingresos/index.blade.php` | **Reemplazada** | Nueva Tabla_Pendientes: columnas simplificadas, botón "Abrir ticket", botón "Listado de ingresos culminados" |
| `ingresos/create.blade.php` | **Modificada** | Se eliminan los campos de pago (`metodo_pago`, `total`, `precio`, `monto_*`) |
| `ingresos/confirmar.blade.php` | **Nueva** | Panel_Confirmacion: muestra datos del ingreso + formulario de pago completo + botones de acción |
| `ingresos/confirmados.blade.php` | **Nueva** | Tabla_Confirmados: equivalente a la vista `index` actual con todas sus columnas y acciones |

---

## Modelos de Datos

### Migración: agregar campo `estado`

```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_estado_to_ingresos_table.php

public function up(): void
{
    Schema::table('ingresos', function (Blueprint $table) {
        $table->enum('estado', ['pendiente', 'confirmado'])
              ->default('pendiente')
              ->after('fecha');
    });

    // Todos los ingresos existentes se marcan como confirmados
    DB::table('ingresos')->update(['estado' => 'confirmado']);
}

public function down(): void
{
    Schema::table('ingresos', function (Blueprint $table) {
        $table->dropColumn('estado');
    });
}
```

### Modelo `Ingreso` — cambios

Se agrega `estado` al array `$fillable` y al cast correspondiente:

```php
protected $fillable = [
    'cliente_id', 'vehiculo_id', 'fecha', 'precio', 'total', 'foto',
    'user_id', 'metodo_pago', 'monto_efectivo', 'monto_yape', 'monto_izipay',
    'estado',  // nuevo
];

protected $casts = [
    'fecha'          => 'date',
    'precio'         => 'decimal:2',
    'total'          => 'decimal:2',
    'monto_efectivo' => 'decimal:2',
    'monto_yape'     => 'decimal:2',
    'monto_izipay'   => 'decimal:2',
    // 'estado' no necesita cast especial (string enum)
];

// Scopes de conveniencia
public function scopePendientes($query)
{
    return $query->where('estado', 'pendiente');
}

public function scopeConfirmados($query)
{
    return $query->where('estado', 'confirmado');
}
```

### Esquema de la tabla `ingresos` (estado final)

```
ingresos
├── id                  bigint unsigned PK
├── cliente_id          bigint unsigned FK → clientes
├── vehiculo_id         bigint unsigned FK → vehiculos
├── fecha               date
├── estado              enum('pendiente','confirmado') DEFAULT 'pendiente'  ← NUEVO
├── precio              decimal(10,2) DEFAULT 0
├── total               decimal(10,2) DEFAULT 0
├── foto                varchar(255) nullable
├── user_id             bigint unsigned FK → users
├── metodo_pago         enum('efectivo','yape','izipay','mixto') DEFAULT 'efectivo'
├── monto_efectivo      decimal(10,2) nullable
├── monto_yape          decimal(10,2) nullable
├── monto_izipay        decimal(10,2) nullable
├── created_at          timestamp
└── updated_at          timestamp
```

### Lógica de validación por acción

**`store()` — Ingreso Pendiente** (campos de pago excluidos):
```php
[
    'vehiculo_id'             => ['required', 'integer', 'exists:vehiculos,id'],
    'placa'                   => ['required', 'string', 'max:7'],
    'nombre'                  => ['nullable', 'string', 'max:100'],
    'dni'                     => ['nullable', 'string', 'max:8'],
    'fecha'                   => ['required', 'date'],
    'foto'                    => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
    'trabajador_id'           => ['required', 'integer', 'exists:trabajadores,id'],
    'servicios'               => ['nullable', 'array'],
    'servicios.*.servicio_id' => ['required', 'integer', 'exists:servicios,id'],
]
```

**`procesarConfirmacion()` — Confirmación de Pago**:
```php
[
    'vehiculo_id'             => ['required', 'integer', 'exists:vehiculos,id'],
    'placa'                   => ['required', 'string', 'max:7'],
    'nombre'                  => ['nullable', 'string', 'max:100'],
    'dni'                     => ['nullable', 'string', 'max:8'],
    'fecha'                   => ['required', 'date'],
    'foto'                    => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
    'trabajador_id'           => ['required', 'integer', 'exists:trabajadores,id'],
    'servicios'               => ['nullable', 'array'],
    'servicios.*.servicio_id' => ['required', 'integer', 'exists:servicios,id'],
    'precio'                  => ['required', 'numeric', 'min:0'],
    'total'                   => ['required', 'numeric', 'gt:0'],
    'metodo_pago'             => ['required', 'in:efectivo,yape,izipay,mixto'],
    'monto_efectivo'          => ['nullable', 'numeric', 'min:0'],
    'monto_yape'              => ['nullable', 'numeric', 'min:0'],
    'monto_izipay'            => ['nullable', 'numeric', 'min:0'],
]
```

---

## Propiedades de Corrección

*Una propiedad es una característica o comportamiento que debe ser verdadero en todas las ejecuciones válidas del sistema — esencialmente, una declaración formal sobre lo que el sistema debe hacer. Las propiedades sirven como puente entre las especificaciones legibles por humanos y las garantías de corrección verificables por máquinas.*

### Propiedad 1: El registro siempre crea ingresos en estado pendiente

*Para cualquier* conjunto válido de datos de ingreso (vehículo, placa, fecha, trabajador), al ejecutar la acción `store`, el ingreso creado debe tener `estado = 'pendiente'`.

**Valida: Requisito 1.2**

---

### Propiedad 2: La validación rechaza ingresos con campos requeridos ausentes

*Para cualquier* solicitud de creación de ingreso en la que al menos uno de los campos requeridos (`vehiculo_id`, `placa`, `fecha`, `trabajador_id`) esté ausente o sea inválido, la validación debe fallar y no debe crearse ningún ingreso en la base de datos.

**Valida: Requisitos 1.3, 1.5**

---

### Propiedad 3: La tabla de pendientes solo muestra ingresos pendientes, ordenados por fecha descendente

*Para cualquier* conjunto de ingresos en la base de datos con estados mixtos (`pendiente` y `confirmado`), la respuesta de `GET /ingresos` debe contener únicamente los ingresos con `estado = 'pendiente'`, ordenados del más reciente al más antiguo.

**Valida: Requisito 2.2**

---

### Propiedad 4: La confirmación transiciona el estado y almacena los datos de pago

*Para cualquier* ingreso con `estado = 'pendiente'` y cualquier conjunto válido de datos de pago (`total > 0`, `metodo_pago` válido), al ejecutar `procesarConfirmacion`, el ingreso debe tener `estado = 'confirmado'` y los datos de pago deben estar almacenados correctamente.

**Valida: Requisitos 4.1, 4.2**

---

### Propiedad 5: La validación de confirmación rechaza totales inválidos

*Para cualquier* ingreso pendiente, si el `total` enviado es cero o negativo, la confirmación debe ser rechazada con un error de validación y el estado del ingreso debe permanecer `pendiente`.

**Valida: Requisito 4.4**

---

### Propiedad 6: La actualización preserva el estado pendiente

*Para cualquier* ingreso con `estado = 'pendiente'` y cualquier conjunto válido de datos de actualización, al ejecutar `update`, el estado del ingreso debe permanecer `pendiente`.

**Valida: Requisito 5.1**

---

### Propiedad 7: La eliminación remueve el ingreso y todos sus registros relacionados

*Para cualquier* ingreso con registros asociados en `ingreso_trabajadores` y `detalle_servicios`, al ejecutar `destroy`, el ingreso y todos sus registros relacionados deben ser eliminados de la base de datos.

**Valida: Requisito 6.2**

---

### Propiedad 8: La tabla de confirmados solo muestra ingresos confirmados, ordenados por fecha descendente

*Para cualquier* conjunto de ingresos en la base de datos con estados mixtos, la respuesta de `GET /ingresos/confirmados` debe contener únicamente los ingresos con `estado = 'confirmado'`, ordenados del más reciente al más antiguo.

**Valida: Requisito 7.2**

---

### Propiedad 9: El precio calculado es la suma del vehículo más los servicios

*Para cualquier* combinación de vehículo y lista de servicios, el campo `precio` almacenado en el ingreso debe ser igual a la suma del precio del vehículo más la suma de los precios de todos los servicios seleccionados.

**Valida: Requisito 5.6**

---

## Manejo de Errores

### Errores de validación

- **Campos requeridos ausentes en `store`**: Laravel devuelve errores por campo, el formulario se re-renderiza con los valores anteriores (`withInput()`). No se crea el ingreso.
- **`total <= 0` en `procesarConfirmacion`**: Error de validación `gt:0`. El ingreso permanece en estado `pendiente`.
- **`metodo_pago` inválido**: Error de validación `in:`. El ingreso permanece en estado `pendiente`.

### Errores de base de datos / transacciones

Todos los métodos que modifican datos (`store`, `procesarConfirmacion`, `update`, `destroy`) están envueltos en `DB::transaction()`. Si ocurre cualquier excepción:
- Se hace rollback automático.
- Se redirige de vuelta con `back()->withInput()->with('error', '...')`.
- El ingreso no queda en estado inconsistente.

### Eliminación de archivos

En `destroy()`, si el ingreso tiene foto, se elimina el archivo **después** de confirmar que el registro fue eliminado de la base de datos (dentro de la transacción). Si la eliminación del archivo falla, se registra el error pero no se revierte la eliminación del registro (el archivo huérfano es preferible a un registro huérfano).

### Acceso a ingresos en estado incorrecto

Si un operario intenta acceder a `GET /ingresos/{id}/confirmar` para un ingreso ya confirmado, el controlador debe redirigir a la Tabla_Confirmados con un mensaje informativo. Esto previene confirmaciones duplicadas.

---

## Estrategia de Testing

### Enfoque dual

Se utilizan dos tipos de tests complementarios:

1. **Tests de ejemplo (Feature Tests de Laravel)**: Verifican comportamientos específicos, flujos de UI y casos borde concretos.
2. **Tests de propiedades (PBT con PestPHP + `pest-plugin-faker` o generadores manuales)**: Verifican propiedades universales con múltiples entradas generadas.

### Librería de PBT

Se utiliza **[eris/eris](https://github.com/giorgiosironi/eris)** o generadores manuales con Faker dentro de PestPHP para ejecutar cada propiedad con mínimo 100 iteraciones. Alternativamente, se puede usar un loop `repeat(100)` con datos generados por Faker en cada iteración.

Formato de etiqueta para cada test de propiedad:
```
Feature: ingresos-confirmacion, Property {N}: {texto de la propiedad}
```

### Tests de propiedades (mínimo 100 iteraciones cada uno)

```php
// Feature: ingresos-confirmacion, Property 1: El registro siempre crea ingresos en estado pendiente
it('siempre crea ingresos con estado pendiente', function () {
    repeat(100, function () {
        $data = generarDatosIngresoValidos(); // Faker
        $response = $this->actingAs($user)->post('/ingresos', $data);
        $ingreso = Ingreso::latest()->first();
        expect($ingreso->estado)->toBe('pendiente');
    });
});

// Feature: ingresos-confirmacion, Property 2: La validación rechaza ingresos con campos requeridos ausentes
it('rechaza ingresos con campos requeridos ausentes', function () {
    repeat(100, function () {
        $campoAusente = fake()->randomElement(['vehiculo_id', 'placa', 'fecha', 'trabajador_id']);
        $data = generarDatosIngresoValidos();
        unset($data[$campoAusente]);
        $response = $this->actingAs($user)->post('/ingresos', $data);
        $response->assertSessionHasErrors($campoAusente);
        expect(Ingreso::count())->toBe(0);
    });
});

// Feature: ingresos-confirmacion, Property 3: La tabla de pendientes solo muestra pendientes
it('la tabla de pendientes solo muestra ingresos pendientes ordenados', function () {
    repeat(100, function () {
        // Crear mezcla aleatoria de pendientes y confirmados
        $nPendientes  = fake()->numberBetween(1, 10);
        $nConfirmados = fake()->numberBetween(0, 10);
        Ingreso::factory()->count($nPendientes)->pendiente()->create();
        Ingreso::factory()->count($nConfirmados)->confirmado()->create();

        $response = $this->actingAs($user)->get('/ingresos');
        $ingresos = $response->viewData('ingresos');

        expect($ingresos->every(fn($i) => $i->estado === 'pendiente'))->toBeTrue();
        expect($ingresos)->toBeSortedByDateDesc();
    });
});
```

### Tests de ejemplo (Feature Tests)

- `GET /ingresos` devuelve HTTP 200 con la vista `ingresos.pendientes`
- `GET /ingresos/confirmados` devuelve HTTP 200 con la vista `ingresos.confirmados`
- El formulario de creación no contiene campos de pago
- El Panel_Confirmacion muestra placa, servicios y campos de pago
- Confirmar un ingreso ya confirmado redirige con mensaje informativo
- Eliminar un ingreso con foto elimina el archivo del storage
- La alerta de pago mixto aparece cuando la suma no coincide con el total
- Paginación de 15 registros en la Tabla_Confirmados

### Tests de migración (Smoke Tests)

- La migración crea el campo `estado` con los valores `pendiente` y `confirmado`
- Los ingresos existentes tienen `estado = 'confirmado'` después de ejecutar la migración
- El valor por defecto para nuevos registros es `pendiente`
- El `down()` elimina el campo sin errores

### Factories

Se necesita actualizar `IngresoFactory` para soportar estados:

```php
// database/factories/IngresoFactory.php
public function pendiente(): static
{
    return $this->state(['estado' => 'pendiente']);
}

public function confirmado(): static
{
    return $this->state([
        'estado'      => 'confirmado',
        'total'       => fake()->randomFloat(2, 10, 500),
        'metodo_pago' => fake()->randomElement(['efectivo', 'yape', 'izipay']),
    ]);
}
```
