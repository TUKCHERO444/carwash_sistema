# Design Document — módulo de reportes

## Visión General

Este módulo añade un **bloque de reportes de solo lectura** para el administrador. Agrega, filtra y exporta la información de los tres procesos centrales (ventas, lavados, cambio de aceite) y de las entidades complementarias (inventario, clientes/automotores, caja, personal, kardex) con distintos niveles de detalle.

Decisiones de diseño clave:

- **Módulo de solo lectura**: no registra transacciones, no abre/cierra caja (no aplica `error_caja`) y no escribe en `registros_auditoria`. Esto elimina acoplamiento y permite servicios de agregación puros.
- **Criterio de ingreso consolidado único**: se reutiliza el criterio ya probado de `CajaService`/`DashboardService` — ventas y cambio_aceites por `DATE(created_at)`, lavados **confirmados** por `DATE(fecha)`. Un solo lugar (`RangoTrait` / helpers del servicio de ingresos) evita divergencias.
- **Un controlador, servicios por dominio**: `ReporteController` delega en servicios bajo `app/Services/Reportes/`. Cada servicio es una función pura de agregación (sin efectos), lo que permite property testing de invariantes (sumas y particiones).
- **Filtros GET estándar**: `desde`, `hasta`, más selects contextuales según el reporte; validación inline; rango por defecto de 30 días con `now()` (`America/Lima`).
- **Exportación CSV sin dependencias**: `?export=csv` en la misma ruta → `StreamedResponse` con BOM UTF-8 y separador `;` (Excel es-PE). Reutiliza los mismos servicios.
- **Impresión con `print:` de Tailwind**: botón que llama a `window.print()`; sidebar, nav móvil y botones se ocultan con la variante `print:hidden`.
- **Nuevo campo `pago_diario`** en `trabajadores` (default 50 en BD y seeder) para alimentar el resumen de pago.
- **Nuevo permiso `acceso-reportes`**: independiente de los demás; solo lo recibe el Administrador.

---

## Arquitectura

```
HTTP Request
    │
    ▼
routes/web.php  (grupo middleware: auth, permission:acceso-reportes, prefix reportes, name reportes.*)
  GET  /reportes                → index
  GET  /reportes/ingresos       → ingresos        (?desde&hasta | ?export=csv)
  GET  /reportes/ventas         → ventas          (+ ?user_id&metodo_pago&correlativo)
  GET  /reportes/lavados        → lavados         (+ ?vehiculo_id&servicio_id&trabajador_id)
  GET  /reportes/cambio-aceite  → cambioAceite    (+ ?trabajador_id&producto_id)
  GET  /reportes/inventario     → inventario      (+ ?categoria_id&marca_id)
  GET  /reportes/clientes       → clientes        (+ ?desde&hasta)
  GET  /reportes/caja           → caja            (+ ?desde&hasta)
  GET  /reportes/personal       → personal        (?mes&trabajador_id)
  GET  /reportes/kardex         → kardex          (+ ?producto_id&tipo)
    │
    ▼
ReporteController
  index()      → vista reportes.index (tarjetas)
  ingresos()   → ReporteIngresosService → vista + Chart.js | CSV
  ventas()     → ReporteVentasService   → vista            | CSV
  lavados()    → ReporteLavadosService  → vista            | CSV
  cambioAceite()→ ReporteCambioAceiteService → vista       | CSV
  inventario() → ReporteInventarioService → vista          | CSV
  clientes()   → ReporteClientesService → vista            | CSV
  caja()       → ReporteCajaService    → vista             | CSV
  personal()   → ReportePersonalService → vista            | CSV
  kardex()     → ReporteKardexService  → vista             | CSV
    │
    ▼
Servicios de agregación (app/Services/Reportes/)
  ReporteIngresosService, ReporteVentasService, ReporteLavadosService,
  ReporteCambioAceiteService, ReporteInventarioService, ReporteClientesService,
  ReporteCajaService, ReportePersonalService, ReporteKardexService,
  ReporteCsvService (export), ReporteFiltersSoporte (helper de rango)
    │
    ▼
Blade Views (extienden layouts.app)
  resources/views/reportes/{index, ingresos, ventas, lavados, cambio-aceite,
    inventario, clientes, caja, personal, kardex}.blade.php
  resources/views/reportes/partials/filtros.blade.php   (barra de filtros reutilizable)
  resources/views/reportes/partials/acciones.blade.php  (botones Imprimir / Exportar CSV)
    │
    ▼
resources/js
  reportes/ingresos.js   (Chart.js: series diarias + doughnut de método de pago)
  reportes/print.js      (window.print() sobre el contenido, ocultando chrome del layout)
```

**Nota de refactoring tolerada:** `ReporteIngresosService` puede reusar la lógica de `DashboardService::ingresosEnRango`, `metodoPago`, `ingresosPorDia` y `topProductos`. Se permite extraer helpers privados (por ejemplo una constante `FECHA_VENTAS`/`FECHA_LAVADOS`/`FECHA_CAMBIOS`) sin alterar el comportamiento público del dashboard; los tests existentes de `DashboardServiceTest` deben seguir en verde.

---

## Componentes e Interfaces

### ReporteController

**Namespace:** `App\Http\Controllers`  
**Ruta del archivo:** `app/Http/Controllers/ReporteController.php`

| Método | HTTP | URI | Descripción |
|--------|------|-----|-------------|
| `index()` | GET | `/reportes` | Vista con tarjetas de acceso a cada reporte |
| `ingresos(Request $r)` | GET | `/reportes/ingresos` | KPIs, serie diaria (Chart.js), doughnut método de pago, días top |
| `ventas(Request $r)` | GET | `/reportes/ventas` | KPIs + agregados + detalle paginado de ventas |
| `lavados(Request $r)` | GET | `/reportes/lavados` | KPIs + agregados por vehículo/servicio/trabajador + detalle |
| `cambioAceite(Request $r)` | GET | `/reportes/cambio-aceite` | KPIs + agregados por producto/trabajador + detalle |
| `inventario(Request $r)` | GET | `/reportes/inventario` | Top productos, stock, resúmenes por categoría/marca |
| `clientes(Request $r)` | GET | `/reportes/clientes` | Top clientes y automotores |
| `caja(Request $r)` | GET | `/reportes/caja` | Cajas cerradas con balance + egresos |
| `personal(Request $r)` | GET | `/reportes/personal` | Asistencias del mes + resumen de pago |
| `kardex(Request $r)` | GET | `/reportes/kardex` | Movimientos agregados + detalle |

**Validaciones inline** (convención del proyecto, sin Form Requests):

```php
// filtros comunes de rango
$data = $request->validate([
    'desde' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:hasta', 'before_or_equal:today'],
    'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde', 'before_or_equal:today'],
]);

// ventas
$data = $request->validate([
    'desde' => [...mismas reglas...],
    'hasta' => [...mismas reglas...],
    'user_id' => ['nullable', 'integer', 'exists:users,id'],
    'metodo_pago' => ['nullable', 'in:efectivo,yape,izipay,mixto'],
    'correlativo' => ['nullable', 'string', 'max:50'],
]);

// lavados
$data = $request->validate([
    ...,
    'vehiculo_id' => ['nullable', 'integer', 'exists:vehiculos,id'],
    'servicio_id' => ['nullable', 'integer', 'exists:servicios,id'],
    'trabajador_id' => ['nullable', 'integer', 'exists:trabajadores,id'],
]);

// personal
$data = $request->validate([
    'mes' => ['nullable', 'date_format:Y-m'],
    'trabajador_id' => ['nullable', 'integer', 'exists:trabajadores,id'],
]);
```

Cuando `$request->query('export') === 'csv'` y la validación falla el controlador SHALL responder `422` con JSON de errores (para que la descarga no cuelgue como HTML).

### Helper de rango — `app/Services/Reportes/DateRangeFiltro.php`

```php
final class DateRangeFiltro
{
    // Devuelve [desde: CarbonImmutable, hasta: CarbonImmutable, etiqueta: string].
    // Sin parámetros → últimos 30 días (hoy incluido) usando now() (America/Lima).
    public static function aplicar(?string $desde, ?string $hasta): array;

    // Serie completa de días 'Y-m-d' entre desde y hasta (sin huecos).
    public static function diasEntre(CarbonImmutable $desde, CarbonImmutable $hasta): array;

    // Mes 'Y-m' → [desde: CarbonImmutable, hasta: CarbonImmutable].
    public static function mes(?string $mes): array;
}
```

### Servicios de agregación (namespace `App\Services\Reportes`)

Cada servicio expone métodos **puros** (sin `auth()`, sin `session()`, sin escritura) que reciben rangos/arrays de filtros y devuelven arrays/collections serializables a JSON y CSV.

#### `ReporteIngresosService`

```php
class ReporteIngresosService
{
    /**
     * @return array{
     *   total: float, operaciones: int, ticket_promedio: float,
     *   ventas: float, lavados: float, cambios: float,
     *   efectivo: float, yape: float, izipay: float,
     * }
     */
    public function consolidado(CarbonImmutable $desde, CarbonImmutable $hasta): array;

    /**
     * Serie diaria. @return array{dias: string[], ventas: float[], lavados: float[], cambios: float[], totals: float[]}
     */
    public function serieDiaria(CarbonImmutable $desde, CarbonImmutable $hasta): array;

    /**
     * Top días por ingreso consolidado. @return array<int, array{fecha:string, ventas:float, lavados:float, cambios:float, total:float, actividad_dominante:?string}>
     */
    public function diasTop(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite = 10): array;

    public function metodoPago(CarbonImmutable $desde, CarbonImmutable $hasta): array; // efectivo/yape/izipay
}
```

**Lógica de `diasTop`:** se reutiliza la serie diaria y se ordena por `total` desc; la `actividad_dominante` es la fuente con mayor aporte si `≥ 50%` del total del día.

#### `ReporteVentasService`

```php
class ReporteVentasService
{
    /**
     * @return array{ total_ventas: float, numero_ventas: int, ticket_promedio: float }
     */
    public function kpis(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array;

    /** @return array<int, array{ user_id:int, user:string, total:float, operaciones:int }> */
    public function porUsuario(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array;

    /** @return array<int, array{ metodo:string, total:float, operaciones:int }> */
    public function porMetodo(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): array;

    public function detalle(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = [], int $perPage = 10): LengthAwarePaginator;

    public function detalleColeccion(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Collection; // para CSV
}
```

`$filtros` soporta `user_id`, `metodo_pago`, `correlativo`. Derecho de filtro base siempre presente: `whereBetween('created_at', [$desde, $hasta])`.

#### `ReporteLavadosService` y `ReporteCambioAceiteService`

Patrón idéntico al de ventas, con métodos `kpis(...)`, `porVehiculo(...)`/`porServicio(...)`/`porTrabajador(...)` (lavados) y `porProducto(...)`/`porTrabajador(...)` (cambio), más `detalle(...)` paginado y `detalleColeccion(...)`.

Detalles clave:

- **Lavados:** universo = `estado = 'confirmado'`; rango por `fecha`. `porTrabajador` une `lavado_trabajadores` (una operación cuenta 1 por lavado en que participó, sin multiplicar por cantidad).
- **Cambio aceite:** universo = `estado = 'confirmado'`; rango por `created_at`. `porProducto` une `cambio_productos` sumando `cantidad` e `total`.

#### `ReporteInventarioService`

```php
/** @return Collection<int, array{ id:int, nombre:string, cantidad:int, ingreso:float }> */
public function topPorCantidad(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = [], int $limite = 20): Collection;

/** @return Collection<int, array{ id:int, nombre:string, cantidad:int, ingreso:float }> */
public function topPorIngreso(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = [], int $limite = 20): Collection;

/** @return Collection<int, Producto> stock actual con categoría/marca y valorizado */
public function stockActual(array $filtros = []): Collection;

/** @return array<int, array{ id:int, nombre:string, productos:int, stock_total:int, ingresos:float }> */
public function resumenCategorias(...): array;

/** @return array<int, array{ id:int, nombre:string, productos:int, stock_total:int, ingresos:float }> */
public function resumenMarcas(...): array;
```

`topPorCantidad`/`topPorIngreso` combinan `detalle_ventas` y `cambio_productos` uniendo `ventas`/`cambio_aceites` para aplicar el rango (criterio ampliado de `DashboardService::topProductos`). El valorizado a costo usa `stock × precio_compra`.

#### `ReporteClientesService`

```php
/** @return Collection<int, array{ cliente_id:int, nombre:string, gasto:float, visitas:int, automotores:int, visitas_por_mes:float }> */
public function topClientes(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite = 20): Collection;

/** @return Collection<int, array{ placa:string, marca:string, modelo:string, cliente:string, visitas:int, ingresos:float }> */
public function topAutomotores(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite = 20): Collection;

public function detalle(CarbonImmutable $desde, CarbonImmutable $hasta, int $perPage = 10): LengthAwarePaginator; // detalle por cliente
```

`gasto = SUM(lavados.total confirmados) + SUM(cambio_aceites.total confirmados)` agrupado por `cliente_id` en el rango.

#### `ReporteCajaService`

```php
/** @return array{ cajas:int, ingresos:float, egresos:float, saldo_neto:float } */
public function kpis(CarbonImmutable $desde, CarbonImmutable $hasta): array;

public function detalle(CarbonImmutable $desde, CarbonImmutable $hasta, int $perPage = 10): LengthAwarePaginator; // Caja::cerrada()->whereBetween('fecha_cierre') + calcularResumen

/** @return Collection<int, EgresoCaja> egresos en el rango (por fecha_cierre de su caja) */
public function egresos(CarbonImmutable $desde, CarbonImmutable $hasta): Collection;

/** @return array<int, array{ descripcion:string, total:float, cantidad:int, tipo_pago:string }> */
public function egresosPorDescripcion(CarbonImmutable $desde, CarbonImmutable $hasta): array;
```

Para el balance por caja se reutiliza `CajaService::calcularResumen($caja)`; el rango filtra por `fecha_cierre`.

#### `ReportePersonalService`

```php
/**
 * @return array{
 *   mes: string, total_dias_con_marca: int, trabajadores: array<int, array{
 *      trabajador_id:int, nombre:string, pago_diario:?float, asistencias:int,
 *      porcentaje_asistencia:float, hora_promedio:?string, total_pago:float
 *   }>, total_pago_general: float,
 * }
 */
public function resumen(string $mes, ?int $trabajadorId = null): array;
```

`total_dias_con_marca` = días distintos con al menos una marca en el mes. `porcentaje_asistencia` = asistencias / `total_dias_con_marca` (0 si no hay días). `total_pago = asistencias × (pago_diario ?? 0)` y `sin_jornal` flag cuando `pago_diario` es null.

#### `ReporteKardexService`

```php
/** @return Collection<int, array{ producto_id:int, producto:string, entradas:int, salidas:int, saldo_neto:int, stock_actual:int }> */
public function agregado(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = []): Collection;

public function detalle(CarbonImmutable $desde, CarbonImmutable $hasta, array $filtros = [], int $perPage = 10): LengthAwarePaginator;
```

Tipo `entrada`/`salida` según `movimientos_kardex.tipo`; `saldo_neto = entradas - salidas`.

#### `ReporteCsvService`

```php
class ReporteCsvService
{
    // Devuelve StreamedResponse listo para descargar.
    public function descargar(string $nombreArchivo, array $cabeceras, array|Collection $filas): StreamedResponse;

    // Separa por ';' con BOM UTF-8 y comillas dobles.
    public static function BOM: string; // "\xEF\xBB\xBF"
}
```

**Formato de celda:** los valores decimales se escriben con punto decimal y se citan con comillas cuando el contenido incluye `;`, `"` o saltos de línea. `Content-Disposition: attachment`.

### Rutas

En `routes/web.php`, en un grupo propio al final de las rutas autenticadas:

```php
use App\Http\Controllers\ReporteController;

// Reportes (Protected by 'acceso-reportes') — solo lectura, todas las rutas estáticas
Route::middleware(['auth', 'permission:acceso-reportes'])
    ->prefix('reportes')
    ->name('reportes.')
    ->group(function () {
        Route::get('/', [ReporteController::class, 'index'])->name('index');
        Route::get('/ingresos', [ReporteController::class, 'ingresos'])->name('ingresos');
        Route::get('/ventas', [ReporteController::class, 'ventas'])->name('ventas');
        Route::get('/lavados', [ReporteController::class, 'lavados'])->name('lavados');
        Route::get('/cambio-aceite', [ReporteController::class, 'cambioAceite'])->name('cambioAceite');
        Route::get('/inventario', [ReporteController::class, 'inventario'])->name('inventario');
        Route::get('/clientes', [ReporteController::class, 'clientes'])->name('clientes');
        Route::get('/caja', [ReporteController::class, 'caja'])->name('caja');
        Route::get('/personal', [ReporteController::class, 'personal'])->name('personal');
        Route::get('/kardex', [ReporteController::class, 'kardex'])->name('kardex');
    });
```

No hay rutas con parámetros: se omiten los conflictos de Route Model Binding.

### Permisos y menú

- `PermissionSeeder.php` (bloque `// Reportes`): `'acceso-reportes'`.
- `layouts/app.blade.php`:
  - `@php`: `$gestionAdministrativaActive = request()->routeIs('vehiculos.*', 'servicios.*', 'clientes.*', 'automotores.*', 'reportes.*');`
  - Desplegable "Gestión Administrativa": `@canany([..., 'acceso-reportes'])`.
  - Dentro del menú (desktop y móvil): separador + encabezado "Reportes" y los enlaces a `reportes.index`, `reportes.ingresos`, `reportes.ventas`, `reportes.lavados`, `reportes.cambioAceite`, `reportes.inventario`, `reportes.clientes`, `reportes.caja`, `reportes.personal`, `reportes.kardex`, resaltados con `request()->routeIs('reportes.*')`.
  - El enlace principal conduce a `reportes.index` (tarjetas), de forma que el menú no se satura con 9 enlaces y la navegación se mantiene ordenada.

### Auditoría

El módulo es de **solo lectura**: no se registra ningún modelo nuevo ni se añade a `AuditServiceProvider`/`AuditService`. No se audita la consulta de reportes.

---

## Modelos de Datos

### Migración `2026_09_12_000002_add_pago_diario_to_trabajadores_table.php`

```php
Schema::table('trabajadores', function (Blueprint $table) {
    $table->decimal('pago_diario', 8, 2)->default(50)->after('foto');
});
```

### Modelo `app/Models/Trabajador.php`

```php
protected $fillable = [..., 'pago_diario'];
protected $casts = [..., 'pago_diario' => 'decimal:2'];
```

**Decisiones de esquema:**

- `default(50)` a nivel de BD: los registros legado (y los creados sin el campo) heredan el jornal definido por el negocio.
- `decimal(8,2)`: admite jornales de hasta 999,999.99; suficiente para el mercado peruano.
- El seeder `TrabajadorSeeder` y la factory `TrabajadorFactory` escriben `'pago_diario' => 50` explícito (idempotente con `firstOrCreate`).
- Formularios de trabajador: input `pago_diario` opcional (`nullable|numeric|min:0`); si se deja vacío se guarda `null` y el reporte lo trata como 0 con aviso "Sin jornal".

### Impacto en entidades existentes

| Entidad | Cambio |
|---------|--------|
| `Trabajador` | + `pago_diario` (fillable, cast, forms, seeder, factory) |
| `PermissionSeeder` | + `acceso-reportes` |
| `layouts/app.blade.php` | Sección "Reportes" en dropdown "Gestión Administrativa" |
| `routes/web.php` | Grupo `reportes.*` |
| `vite.config.js` | + `reportes/ingresos.js`, `reportes/print.js` |
| `DashboardService` | Tolerado extraer helpers de rango/fechas sin cambiar comportamiento público |
| `CajaService` | Se reutiliza `calcularResumen` tal cual (sin cambios) |

`DataNormalizer` no interviene: el módulo solo recibe fechas, ids y texto de correlativo (no se normaliza).

---

## Vistas Blade

### `resources/views/reportes/index.blade.php`

Extiende `layouts.app`. Cuadrícula de tarjetas (responsive `grid`): por cada reporte un enlace con icono, título y descripción, con clases `bg-surface border border-main rounded-xl hover:...`.

### `resources/views/reportes/partials/filtros.blade.php`

Barra de filtros reutilizable: inputs `desde`/`hasta` (`type="date"`) + contenedor `@stack`/slots para los selects contextuales + botones **Filtrar** (submit GET) y **Limpiar** (vuelve a la ruta sin parámetros). Se pasa un `$ruta` para construir el GET.

### `resources/views/reportes/partials/acciones.blade.php`

Botones: **Imprimir** (`data-imprimir-reporte`, JS `reportes/print.js`) y **Exportar CSV** (`<a href="?{{ request()->query->all()|merge(['export' => 'csv']) }}">`). `print:hidden` en el envoltorio.

### `resources/views/reportes/ingresos.blade.php`

1. Header con título, rango aplicado y acciones (imprimir/CSV).
2. KPIs (4 tarjetas): ingreso total, operaciones, ticket promedio, y tarjetas por fuente.
3. Gráfico de barras apiladas diarias (`#chart-reporte-ingresos`) + doughnut método de pago (`#chart-reporte-metodo-pago`).
4. Tabla "Días de mayor ingreso" (top 10) con desglose por actividad y badge de actividad dominante.
5. Datos inline ANTES del `@vite`:

```blade
<script>
    window.reportesIngresos = @json($serie);
    window.reportesMetodoPago = @json($metodoPago);
</script>
@vite('resources/js/reportes/ingresos.js')
```

### Reportes restantes (`ventas`, `lavados`, `cambio-aceite`, `inventario`, `clientes`, `caja`, `personal`, `kardex`)

Estructura común:

1. Header con título, rango/mes aplicado y acciones de impresión/CSV.
2. Filtros (`partials.filtros` con selects contextuales).
3. KPIs (tarjetas).
4. Tablas de agregados (por dimensión) y detalle paginado con `$x->links()`.
5. Estados vacíos ("No hay registros en el período.").

`personal` no usa rango de fechas: usa `mes` (input `month`) y el resumen se muestra en tablas por trabajador y una sección "Resumen de pago" con totales.

### Estilo (guias obligatorias)

- Contenedores: `bg-surface rounded-lg border border-main`; títulos `text-primary`; secundarios `text-secondary`.
- Tablas de listado: cabeceras `px-6 py-6`, celdas `px-6 py-8`, envoltorio `overflow-x-auto`, `divide-main`.
- Paginación: `paginate(10)` en detalles.
- Badges de método de pago: paleta ya usada en `ventas/index.blade.php` (efectivo/yape/izipay/mixto).
- Badge de actividad dominante en días top: verde (lavados), azul (ventas), ámbar (cambio de aceite) — misma gama de `dashboard.js`.

---

## Frontend (JS)

### `resources/js/reportes/ingresos.js`

Importa `Chart` (chart.js/auto) y reutiliza el patrón de `resources/js/dashboard.js`: `configBase()`, `colorTexto()`, `colorBordes()`, `FORMATO_SOLES`. Renderiza:

- `#chart-reporte-ingresos`: barras apiladas día × fuente.
- `#chart-reporte-metodo-pago`: doughnut efectivo/yape/izipay.

`document.addEventListener('DOMContentLoaded', ...)` inicializa ambos si el canvas existe.

Se extraen **funciones puras exportadas** para property testing JS:

```js
// Formatea soles es-PE (S/ 1,234.56).
export function formatoSoles(valor) {}

// Convierte 'YYYY-MM-DD' → etiqueta corta es-PE 'dd MMM'  (mismo patrón que dashboard).
export function etiquetaDia(fechaKey) {}

// Selecciona la actividad dominante si aporta ≥50%; else null.
export function actividadDominante({ ventas, lavados, cambios }) {}
```

### `resources/js/reportes/print.js`

```js
document.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-imprimir-reporte]');
    if (!boton) return;
    e.preventDefault();
    window.print();
});
```

La vista añade clases `print:hidden` al sidebar/nav (el layout ya las recibe vía `@section` de estilos o clases condicionales) y `print:block` al contenido. Alternativa sin tocar el layout: envolver el contenido con la clase `print-area` y añadir `print:hidden` a `aside`/`nav` desde el layout (única edición de unas clases en `layouts/app.blade.php`).

**Vite:** añadir `'resources/js/reportes/ingresos.js'` y `'resources/js/reportes/print.js'` al `input` de `vite.config.js` (bloque `// Reportes`).

---

## Propiedades de Corrección

*Una propiedad es una característica o comportamiento que debe mantenerse verdadero en todas las ejecuciones válidas del sistema. Las propiedades sirven como puente entre las especificaciones legibles por humano y las garantías verificables por máquina.*

El feature tiene lógica de agregación pura y comprobable con PBT en el backend; se prueban invariantes de suma, partición y acceso. También hay lógica pura en el frontend (formato de soles, etiqueta de día y actividad dominante) evaluable con fast-check.

**Reflexión sobre redundancia:**

- La normalización del rango (Requisito 4) se consolida en la **Propiedad 1**.
- La consistencia serie ↔ consolidado (Requisitos 5) en la **Propiedad 2**.
- El gasto por cliente (Requisito 11) en la **Propiedad 3**.
- El resumen de pago (Requisito 13) en las **Propiedades 4 y 5**.
- El control de acceso (Requisito 2) en las **Propiedades 6 y 7**.

---

### Propiedad 1: La normalización del rango es estable y está acotada

*Para cualquier* par válido `(desde, hasta)` (ambos `Y-m-d`, `desde ≤ hasta ≤ hoy`) o el caso sin parámetros, la normalización de `DateRangeFiltro` produce un intervalo `[desde', hasta']` tal que `desde' ≤ hasta'`, ambos ≤ hoy, y si no se pasan parámetros el intervalo dura entre 1 y 30 días terminando hoy. Al pasar el mismo par dos veces, el resultado es idéntico.

**Valida: Requisito 4**

---

### Propiedad 2: La serie diaria suma exactamente el consolidado

*Para cualquier* rango válido, la suma de `serieDiaria(...)['totals']` debe ser igual a `consolidado(...)['total']` (± 0.01 por redondeo), y por cada fecha la suma `ventas + lavados + cambios` de la serie debe coincidir con su `totals`. Para cada fecha con operaciones, un día solo puede aportar a `diasTop` lo que la serie registra.

**Valida: Requisitos 5, 6**

---

### Propiedad 3: El gasto por cliente es la suma de sus operaciones

*Para cualquier* rango válido y cualquier cliente, el valor `gasto` de `topClientes` debe ser exactamente la suma de los `total` de sus lavados confirmados más sus cambios de aceite confirmados en el rango. La lista resultante debe estar ordenada por `gasto` descendente y `visitas` debe ser el conteo de esas operaciones.

**Valida: Requisito 11**

---

### Propiedad 4: El resumen de pago multiplica asistencias por jornal

*Para cualquier* mes válido (`Y-m`) y cualquier trabajador, `total_pago` debe ser `asistencias × pago_diario` (0 si `pago_diario` es null) y el `total_pago_general` debe ser la suma de los `total_pago` de los trabajadores listados.

**Valida: Requisito 13**

---

### Propiedad 5: Las asistencias están acotadas por el mes

*Para cualquier* mes y trabajador, `asistencias ≤ número de días del mes` y `porcentaje_asistencia` debe estar en `[0, 100]` (0 si no hubo días con marca).

**Valida: Requisito 13**

---

### Propiedad 6: Todas las rutas del módulo requieren autenticación

*Para cualquier* ruta del módulo `reportes` (index y los 9 reportes) y cualquier parámetro válido, una petición sin sesión autenticada debe redirigir a `/login`.

**Valida: Requisito 2.5**

---

### Propiedad 7: Todas las rutas del módulo requieren `acceso-reportes`

*Para cualquier* ruta del módulo `reportes`, una petición de un usuario autenticado sin el permiso `acceso-reportes` debe ser denegada (HTTP 403). El rol Vendedor no debe acceder.

**Valida: Requisito 2.3, 2.6**

---

### Propiedad 8 (JS): La actividad dominante es consistente

*Para cualquier* entrada `{ventas, lavados, cambios}` con valores ≥ 0, `actividadDominante` devuelve la fuente con mayor valor **si y solo si** ese valor ≥ 50% de la suma, y `null` en caso contrario. Formato de moneda redundante con `intl`.

**Valida: Requisito 6.3; producción visual**

---

## Manejo de Errores

| Escenario | Comportamiento |
|-----------|----------------|
| `desde`/`hasta` mal formateados, `desde > hasta`, o `hasta` futuro | Vista: `withErrors`. CSV: HTTP 422 JSON. El rango por defecto NO se aplica sobre parámetros inválidos (mejor fallar claro que filtrar mal). |
| `mes` inválido en `personal` | HTTP 422/`withErrors` (mes no aplica para CSV porque personal no exporta por mes salvo resumen). |
| Selects con id inexistente | HTTP 422 por `exists:` en la validación (no se silencia). |
| Sin datos en el rango | Vistas con estado vacío ("No hay registros en el período."); KPIs en 0; CSV con solo cabeceras. |
| Días sin operaciones en la serie | Valor 0.0 en la serie (evita huecos). |
| Trabajador sin `pago_diario` | Se muestra "Sin jornal" y se calcula con 0. No bloquea el reporte. |
| No hay ninguna caja cerrada | KPIs en 0 y detalle vacío. |
| Usuario sin permiso | Middleware Spatie → HTTP 403 antes de entrar al controlador. |
| Usuario no autenticado | Middleware `auth` → redirección a `/login`. |
| Base de datos sin datos (ambiente limpio) | Todos los reportes renderizan con vacíos; ninguna vista rompe por colecciones vacías. |

---

## Estrategia de Pruebas

### Enfoque dual

PHPUnit (Laravel Feature Tests + property tests server-side en loop de 100 iteraciones, sobre SQLite en memoria) para el backend; Vitest + fast-check para las funciones puras del frontend. Tests etiquetados `// Feature: reportes, Property N: <descripción>` con **Valida: Requisitos ...**.

### Tests Feature — `tests/Feature/Reportes/`

| Archivo | Casos |
|---------|-------|
| `ReporteAccesoTest.php` | Redirect `/login` sin sesión; 403 sin permiso; 200 con permiso para index e ingresos. |
| `ReporteIngresosTest.php` | KPIs con datos conocidos; serie diaria; días top con actividad dominante; método de pago mixto; CSV de ingresos. |
| `ReporteVentasTest.php` | Filtros (usuario, método, correlativo); KPIs; agregados por usuario/método; CSV sin paginar. |
| `ReporteLavadosTest.php` | Universo confirmado (excluye pendientes); agregados por vehículo/servicio/trabajador; CSV. |
| `ReporteCambioAceiteTest.php` | Universo confirmado; agregados por producto/trabajador; CSV. |
| `ReporteInventarioTest.php` | Top cantidad e ingreso con rango; stock y alerta; resúmenes por categoría/marca; CSV. |
| `ReporteClientesTest.php` | Gasto = lavados+cambios; orden por gasto; top automotores; CSV. |
| `ReporteCajaTest.php` | Solo cajas cerradas por rango; balance cuadra con `CajaService`; egresos por descripción; CSV. |
| `ReportePersonalTest.php` | Asistencias y %; resumen de pago con/ sin jornal; acotación a trabajador; CSV. |
| `ReporteKardexTest.php` | Agregados entrada/salida; saldo neto; filtro tipo; CSV. |
| `TrabajadorPagoDiarioTest.php` | Crear/editar trabajador con `pago_diario`; validación; default del seeder/factory. |

### Property tests (PHPUnit) — `tests/Feature/Reportes/` `*PropertyTest.php` (100 iteraciones)

| Propiedad | Test |
|-----------|------|
| Property 1 | Rango con parámetros aleatorios válidos e inválidos; normalización estable y acotada. |
| Property 2 | Datos aleatorios en las 3 fuentes; `sum(serie) == consolidado` y consistencia por día. |
| Property 3 | Clientes con operaciones aleatorias; `gasto == sum(lavados)+sum(cambios)` y orden. |
| Property 4 | Asistencias y jornales aleatorios; `total_pago == asistencias × pago_diario`; suma general. |
| Property 5 | Asistencias ≤ días del mes; porcentaje en `[0,100]` (Faker para fechas relativas a `now()`). |
| Property 6 | Cada ruta del módulo sin auth → redirect `/login`. |
| Property 7 | Cada ruta del módulo con usuario sin permiso → 403. |

### Property tests (Vitest) — `tests/js/reportes/ingresos.property.test.js`

| Propiedad | Test |
|-----------|------|
| Property 8 | `actividadDominante` con triplas aleatorias ≥ 0; condición del 50% verificada. `formatoSoles` y `etiquetaDia` con valores límite. |

### Edge cases

- Rango de un solo día; ayer/hoy (día actual y borde del rango).
- Año bisiesto y fin de mes para `DateRangeFiltro::mes`.
- Operación `mixta` en método de pago (desglose efectivo/yape/izipay).
- Lavados del mes sin confirmar (no cuentan en ningún agregado).
- Trabajador inactivo (`estado=false`) en el resumen de personal: **sí se lista** si participó (el reporte de personal lista trabajadores con el filtro activo, no excluye por estado salvo que el filtro lo indique — decisión: se listan activos e inactivos, se marca inactivo para el usuario).
  - _Corrección de alcance:_ el resumen de pago por defecto lista **todos** los trabajadores con asistencias; el % de asistencia se calcula sobre los días con marca (no sobre el universo de activos), lo que evita acoplarse a `AsistenciaService::trabajadoresActivos` y mantiene el reporte simple.
- CSV con valores nulos y texto que requiere comillas.
- Base de datos vacía (todas las vistas sin romper).

---

## Notas de alcance

- **Fuera de alcance (fases futuras)**: gráficos para los demás reportes (hoy solo ingresos usa Chart.js), alertas programadas por correo, dashboards por rol, exportación PDF/Excel nativa, comparativo interanual, presupuestos. El esquema y los servicios de agregación dejan espacio para añadir estos sin romper nada.
- El módulo es estrictamente de solo lectura; no se añade observador de auditoría.
- La exportación CSV cubre todos los reportes (incluida la serie de ingresos); `personal` exporta el resumen por trabajador (no la matriz día a día).
- La decisión de semana/mes para `personal` se hace con `mes` (input `month`); sin matriz visual día a día por trabajador en esta fase (solo agregados y porcentajes).
- Esta feature **no modifica** `Caja`, `Asistencia`, `Seguridad` ni `DataNormalizer`; únicamente `Trabajador` (nuevo campo de jornal).