# Feature: Dashboard de Estadísticas e Ingresos

## Índice

1. [Resumen ejecutivo](#resumen-ejecutivo)
2. [Estado actual del sistema](#estado-actual-del-sistema)
3. [Modelo de datos y fuentes de información](#modelo-de-datos-y-fuentes-de-información)
4. [Requisitos técnicos de la nueva feature](#requisitos-técnicos-de-la-nueva-feature)
5. [Diseño técnico](#diseño-técnico)
6. [Glosario de métricas](#glosario-de-métricas)
7. [Plan de implementación en unidades de trabajo](#plan-de-implementación-en-unidades-de-trabajo)
8. [Resultados esperados](#resultados-esperados)
9. [Criterios de aceptación](#criterios-de-aceptación)
10. [Verificación](#verificación)

---

## Resumen ejecutivo

El **Dashboard** actual es un stub vacío (`resources/views/dashboard.blade.php`) que solo muestra un saludo de bienvenida. Se implementa un tablero de **estadísticas e ingresos** orientado a dar al dueño del negocio una comprensión rápida del local, centrado en los **tres procesos que generan dinero**:

1. **Ventas** (`ventas`) — venta de productos de mostrador.
2. **Lavados** (`lavados`) — servicio de lavado vehicular.
3. **Cambios de aceite** (`cambio_aceites`) — servicio + productos (aceites/filtros).

La feature agrega:

- **KPIs numéricos** (ingresos de hoy, del mes, número de operaciones, ticket promedio).
- **Gráfico de evolución de ingresos** por día (últimos 30 días) desglosado por fuente (Ventas / Lavados / Cambio de aceite).
- **Gráfico de distribución por método de pago** (efectivo / Yape / Izipay) en el período.
- **Top de productos más vendidos** (unión de `detalle_ventas` + `cambio_productos`).
- **Alerta de productos con stock bajo** (para reponer inventario).

Se prioriza la **utilidad sobre la cantidad**: pocas métricas, pero realmente accionables para el negocio (decisión de diseño explícita).

**Decisión de frontend:** se incorpora **Chart.js vía Vite** (bundle npm) para los gráficos, respetando el stack actual (Laravel + Tailwind CSS 4 + Vite) y el modo oscuro del theme existente.

---

## Estado actual del sistema

### Dashboard actual

```
resources/views/dashboard.blade.php
  @section('content')
    <h1>Dashboard</h1>
    <p>Bienvenido al panel de control...</p>
  @endsection
```

La ruta se define como un **closure** en `routes/web.php`:

```php
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');
```

No existe controlador ni lógica de agregación para el dashboard. No hay librería de gráficos instalada (`package.json` solo declara `sweetalert2` como dependencia de producción).

### Frontend disponible

- Tailwind CSS 4 (config en `resources/css/app.css`) con tokens de theme propios: `bg-surface`, `text-primary`, `text-secondary`, `border-main`, `divide-main`, `input-main`, `input-error`, `label-main`, `error-message`; soporte de **dark mode** vía clase `dark`.
- Vite build (`npm run build` / `npm run dev`), componentes JS en `resources/js/` (`app.js`, `modal.js`, `stock-modal.js`, `image-viewer.js`, `confirmations.js`, `utils/input-filters.js`).
- `sweetalert2` disponible.
- Patrón existente de agregación: `app/Services/CajaService.php` (método `calcularResumen`) ya consolidó ingresos de las 3 fuentes **por caja**; esta feature lo generaliza **por rango de fechas**.

---

## Modelo de datos y fuentes de información

Las tres tablas críticas comparten campos aprovechables para estadística:

| Fuente | Total | Estado (cuenta como ingreso) | Método de pago | Campo fecha | Desglose/productos |
|--------|-------|------------------------------|----------------|-------------|--------------------|
| `ventas` | `total` | Todos los registros | `metodo_pago` + `monto_efectivo/yape/izipay` | `created_at` | `detalle_ventas` (producto, cantidad, precio_unitario, subtotal) |
| `lavados` | `total` | **solo `confirmado`** | `metodo_pago` + montos | `fecha` | `detalle_servicios` (servicios) + `lavado_trabajadores` |
| `cambio_aceites` | `total` | **solo `confirmado`** | `metodo_pago` + montos | `created_at` | `cambio_productos` (producto, cantidad, precio, total) |

### Definición de "ingreso consolidado"

Aplica el **mismo criterio** que `CajaService::calcularResumen` pero **por fecha** en lugar de por caja:

```
INGRESO_FUENTE =
    ventas:          SUM(ventas.total)
    lavados:         SUM(lavados.total  WHERE estado = 'confirmado')
    cambio_aceites:  SUM(cambio_aceites.total WHERE estado = 'confirmado')

INGRESO_TOTAL(período) = SUM(ventas) + SUM(lavados_confirmados) + SUM(cambio_aceites_confirmados)
```

> **Nota de normalización de fechas:** `lavados` usa la columna `fecha` (tipo `date`) mientras `ventas` y `cambio_aceites` usan `created_at` (timestamps). La serie temporal debe normalizar ambos a `CAST(columna AS DATE)` para comparar por día.

### Método de pago

Los `metodo_pago` posibles son `efectivo`, `yape`, `izipay`, `mixto`. Para `mixto` se desglosa en sus montos parciales (`monto_efectivo`, `monto_yape`, `monto_izipay`). Se **reutiliza la misma lógica de distribución** ya implementada y validada en `CajaService`.

### Frecuencia de datosChart.js se buildea con Vite; no se inyectan scripts externos por CDN. |
| RNF-05 | **Compatibilidad dark mode:** colores de gráficos y superficies ajustados con el theme actual. |
| RNF-06 | **Cobertura de pruebas** para la lógica de agregación del servicio. |

---

## Diseño técnico

### 1. Servicio de agregación

- El volumen de registros es de un negocio local (operaciones por día), por lo que **no se requiere** agregación precomputada / tablas de resumen. Se calcula en tiempo real con consultas SQL agregadas sobre índices existentes (`fecha`, `stock`). Si el volumen creciera, se podría cachear o precomputar en el futuro (no está en el alcance actual).

---

## Requisitos técnicos de la nueva feature

### Requisitos funcionales

| ID | Requisito |
|----|-----------|
| RF-01 | Mostrar **KPIs**: ingresos de hoy, ingresos del mes, total de operaciones del período, ticket promedio. |
| RF-02 | Mostrar **gráfico de evolución de ingresos por día** (últimos 30 días) desglosado por fuente (Ventas / Lavados / Cambio de aceite). |
| RF-03 | Mostrar **gráfico de distribución por método de pago** (efectivo / Yape / Izipay) en el período. |
| RF-04 | Mostrar **top N de productos más vendidos** (por cantidad), uniendo ventas y cambios de aceite. |
| RF-05 | Mostrar **lista de productos con stock bajo** (activos con `stock <= umbral` configurable). |
| RF-06 | Proveer un **selector de período** simple (7 / 30 días / este mes) que actualice los KPIs y gráficos. |
| RF-07 | El dashboard respeta **dark mode** y el diseño del theme existente. |
| RF-08 | La página `page/{dashboard}` conserva autenticación obligatoria (`middleware('auth')`). |

### Requisitos no funcionales

| ID | Requisito |
|----|-----------|
| RNF-01 | **Rendimiento:** consultas agregadas en una sola pasada por fuente; evitar N+1 (usar `GROUP BY` + `SUM` en SQL, no loops de Eloquent). |
| RNF-02 | **Correctitud de criterios:** los lavados/cambios de aceite solo cuentan como ingreso en estado `confirmado` (mismo criterio que Caja). |
| RNF-03 | **Normalización de fechas** entre `fecha` (lavados) y `created_at` (ventas/cambio_aceites) para la serie por día. |
| RNF-04 | **Zero-dependencia global:** 

#### `app/Services/DashboardService.php` (nuevo)

Sigue el patrón de `CajaService`: clase inyectable con métodos que devuelven arrays/objetos planos listos para la vista.

```php
class DashboardService
{
    /**
     * Resumen general del período.
     * @return array{
     *   ingresos_hoy: float,
     *   ingresos_mes: float,
     *   operaciones_mes: int,
     *   ticket_promedio_mes: float,
     *   ventas_mes: float, lavados_mes: float, cambios_mes: float,
     * }
     */
    public function resumenGeneral(): array;

    /**
     * Serie diaria de ingresos por fuente para los últimos $dias días.
     * @return array{ dias: string[], ventas: float[], lavados: float[], cambios: float[], totals: float[] }
     */
    public function ingresosPorDia(int $dias = 30): array;

    /**
     * Suma consolidada por método de pago en el período.
     * @return array{ efectivo: float, yape: float, izipay: float }
     */
    public function metodoPago(CarbonImmutable $desde, CarbonImmutable $hasta): array;

    /**
     * Top N productos más vendidos por cantidad (ventas + cambios de aceite).
     * @return array<int, array{ nombre: string, cantidad: int }>
     */
    public function topProductos(int $limit = 5): array;

    /**
     * Productos activos con stock por debajo o igual al umbral.
     * @return array<int, array{ nombre: string, stock: int, categoria: ?string }>
     */
    public function productosStockBajo(int $umbral = 5): array;
}
```

**Implementación sugerida (consultas agregadas, sin N+1):**

- `resumenGeneral()`: 3 consultas con `SUM(total)` + `COUNT(*)` filtradas por día/mes.
- `ingresosPorDia()`: por cada fuente, `GROUP BY CAST(fecha_efectiva AS DATE)`; luego se rellenan en PHP los días sin registros con `0` (evita huecos en el gráfico).
- `metodoPago()`: iterar los tres modelos y aplicar la lógica de `mixto` (reutilizar el `switch` de `CajaService`, extraído para no duplicarla).
- `topProductos()`: dos consultas agregadas:
  - `detalle_ventas` join `productos`: `SUM(cantidad)` por producto.
  - `cambio_productos` join `productos`: `SUM(cantidad)` por producto.
  - Combinar en PHP y ordenar, tomar `limit`.
- `productosStockBajo()`: `Producto::where('activo', true)->where('stock', '<=', $umbral)->orderBy('stock')->get()`.

> Se recomienda que `metodoPago()` y el desglose de `mixto` se deleguen a un helper interno para no duplicar la lógica ya existente en `CajaService` (opcional: extraerla a un trait/método compartido).

### 2. Controlador

#### `app/Http/Controllers/DashboardController.php` (nuevo)

```php
class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function index(Request $request): \Illuminate\View\View
    {
        $periodo = $request->integer('periodo', 30);             // 7 | 30 | (este mes)
        $desde   = ...; $hasta = ...;                            // según periodo
        $stats   = $this->dashboardService->resumenGeneral();
        $serie   = $this->dashboardService->ingresosPorDia($periodo);
        $pagos   = $this->dashboardService->metodoPago($desde, $hasta);
        $top     = $this->dashboardService->topProductos(5);
        $stock   = $this->dashboardService->productosStockBajo(5);

        return view('dashboard', compact('stats','serie','pagos','top','stock','periodo'));
    }
}
```

### 3. Rutas

`routes/web.php` — reemplazar el closure por el controlador:

```php
use App\Http\Controllers\DashboardController;

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');
```

### 4. Frontend

#### Instalación

```
npm i chart.js
```

#### `resources/js/dashboard.js` (nuevo)

Inicializa los gráficos leyendo los datos serializados por Blade (`@json`) desde atributos `data-*` en los `<canvas>`. Ejemplo de flujo:

```js
// 1. Gráfico de barras apiladas (ingresos por día)
const serie = JSON.parse(canvas.dataset.serie);
new Chart(canvas, {
  type: 'bar',
  data: {
    labels: serie.dias,
    datasets: [
      { label: 'Ventas',        data: serie.ventas, backgroundColor: '...' },
      { label: 'Lavados',       data: serie.lavados, backgroundColor: '...' },
      { label: 'Cambio aceite', data: serie.cambios, backgroundColor: '...' },
    ],
  },
  options: { scales: { x: { stacked: true }, y: { stacked: true } }, ... },
});

// 2. Gráfico de dona (método de pago)
new Chart(dona, { type: 'doughnut', data: { labels: [...], datasets: [...] } });
```

Se **registra el script** en `resources/js/app.js` (`import './dashboard';`) o se carga solo en la vista del dashboard.

> **Dark mode:** los colores de ejes/leyendas deben derivarse del theme. Recomendación: leer el tema actual (`document.documentElement.classList.contains('dark')`) y re-renderizar con los colores correctos, o usar colores neutros legibles en ambos temas (`#94a3b8` etc.).

#### `resources/views/dashboard.blade.php` (modificado)

Estructura (Tailwind + componentes del theme):

```
[Row 1 — KPIs (grid responsive)]
  ┌ Ingresos hoy ┐ ┌ Ingresos mes ┐ ┌ Operaciones mes ┐ ┌ Ticket promedio ┐
  └──────────────┘ └──────────────┘ └──────────────────┘ └──────────────────┘

[Row 2 — selector de período (7 / 30 días / este mes)]

[Row 3 — gráfico principal  |  gráfico método de pago]
  [Ingresos por día (30d)]      [Distribución método de pago]

[Row 4 — tablas]
  [Top productos vendidos]   [Productos con stock bajo]
```

Diseño por tarjetas con las clases del theme: contenedor `bg-surface border border-main rounded-xl shadow-sm`, títulos `text-primary`, textos secundarios `text-secondary`, modo oscuro automático por `dark`.

El gráfico se alimenta vía `data-serie="{{ json_encode($serie['dias']) }}"` o `@json`, evitando mezclar lógica en el HTML.

---

## Glosario de métricas

| Métrica | Definición | Fuente | Fórmula |
|---------|-----------|--------|---------|
| **Ingresos hoy** | Ingresos consolidados del día actual | ventas + lavados + cambio_aceites | `SUM(total)` del día, lavados/cambios solo confirmados |
| **Ingresos del mes** | Ingresos consolidados del mes calendario actual | idem | `SUM(total)` del mes |
| **Ticket promedio** | Ingreso medio por operación | ingresos consolidados | `INGRESO_TOTAL(mes) / COUNT(ventas + lavados_confirmados + cambios_confirmados)` |
| **Operaciones** | Nº total de transacciones en el período | idem | conteo de las 3 fuentes |
| **Evolución por día** | Serie temporal de ingresos por fuente | idem agrupado por día | `GROUP BY CAST(fecha/created_at AS DATE)` |
| **Método de pago** | Amount cobrado por cada medio | `metodo_pago` + montos | switch con desglose de `mixto` |
| **Top productos** | Productos más vendidos por cantidad | `detalle_ventas` + `cambio_productos` | `SUM(cantidad)` por producto combinando ambas tablas |
| **Stock bajo** | Productos activos cerca de agotarse | `productos` | `stock <= umbral` (default 5) |

> **Nota de interpretación para el dueño:** el "ingreso" incluye dinero de ventas de mostrador, lavados y cambios de aceite. El ticket promedio y la evolución por día ayudan a detectar picos/bajones y la estacionalidad del local. El desglose por método de pago indica el mix de cobro (útil para manejo de caja y Yape/Izipay).

---

## Plan de implementación en unidades de trabajo

### Unidad 1 — Backend: servicio y controlador (fundamento de datos)

| # | Tarea |
|---|-------|
| 1.1 | Crear `app/Services/DashboardService.php` con los métodos de agregación. |
| 1.2 | Implementar normalización de fechas (lavados usa `fecha`, ventas/cambios `created_at`). |
| 1.3 | Implementar `metodoPago()` reutilizando la lógica de desglose `mixto` de `CajaService`. |
| 1.4 | Crear `app/Http/Controllers/DashboardController.php` e inyectar el servicio. |
| 1.5 | Ajustar `routes/web.php` (controlador en lugar del closure; `auth` obligatorio). |
| 1.6 | **Verificación:** `php artisan route:list` y `php artisan tinker` para inspeccionar la salida del servicio con datos reales/demo. |

### Unidad 2 — Frontend: Chart.js y vistas

| # | Tarea |
|---|-------|
| 2.1 | `npm i chart.js`. |
| 2.2 | Crear `resources/js/dashboard.js` con los 2 gráficos (barras apiladas + dona) y registro en `app.js` (o carga en la vista). |
| 2.3 | Implementar soporte **dark mode** en los gráficos. |
| 2.4 | Rediseñar `resources/views/dashboard.blade.php`: KPIs, selector de período, gráficos y tablas con el theme existente. |
| 2.5 | **Verificación:** `npm run build` OK y visualizar el dashboard autenticado. |

### Unidad 3 — Calidad y refinamiento

| # | Tarea |
|---|-------|
| 3.1 | Escribir **tests unitarios** (phpunit) de `DashboardService`: criterios de estado (confirmado), normalización de fechas, desglose `mixto`, top productos y stock bajo. |
| 3.2 | Ajustar el selector de período (7 / 30 días / este mes) y que los KPIs/gráficos respondan correctamente. |
| 3.3 | **Verificación:** `php artisan test`, `vendor/bin/pint`, `npm run build` y revisión visual manual. |

---

## Resultados esperados

### Datos

- El dueño ve de un vistazo los ingresos de hoy y del mes, el número de operaciones y el ticket promedio.
- Gráfico de evolución de ingresos por día desglosado por Ventas / Lavados / Cambio de aceite.
- Distribución por método de pago (efectivo / Yape / Izipay).
- Top de productos más vendidos y alerta de productos con stock bajo.

### Flujo

- El dashboard se sirve solo a usuarios autenticados.
- El selector de período permite alternar entre 7 / 30 días / este mes.
- Sin dependencias globales externas: Chart.js compilado con Vite.

### Calidad

- Mismo criterio de "ingreso consolidado" que Caja (sin duplicar/contradecir la lógica ya validada).
- Consultas agregadas eficientes (sin N+1).
- Tests unitarios verdes y build de Vite OK.
- Mesas: pocas métricas pero altamente accionables (decisión explícita de diseño: "poco útil > mucho irrelevante").

---

## Criterios de aceptación

1. El dashboard autenticado muestra KPIs (ingresos hoy, mes, operaciones, ticket promedio) correctamente calculados.
2. El gráfico de evolución por día desglosa Ventas / Lavados / Cambio de aceite para el período seleccionado.
3. El gráfico de distribución por método de pago es coherente con `CajaService::calcularResumen` para el mismo rango.
4. El top de productos proviene de la unión de ventas y cambios de aceite (por cantidad).
5. Los productos con stock bajo se listan correctamente y respetan el flag `activo`.
6. Los lavados/cambios de aceite **pendientes** NO cuentan como ingreso; los **confirmados** sí.
7. El dashboard respeta dark mode y el style del theme existente.
8. `php artisan test` y `npm run build` pasan sin errores; no hay regresiones en otras vistas.

---

## Verificación

| Comando | Propósito |
|---------|-----------|
| `npm i` | Instalar `chart.js` |
| `npm run build` | Compilar assets (Vite) |
| `php artisan route:list` | Validar ruta del dashboard con controlador |
| `php artisan test` | Ejecutar suite de tests (incluye los de `DashboardService`) |
| `vendor/bin/pint --test` | Formato de código |
| Navegación autenticada a `/dashboard` | Revisión visual de KPIs, gráficos, dark mode y selector de período |

> Nota: el dashboard depende solo del esquema actual (sin migraciones nuevas). No requiere `migrate:fresh` ni cambios de BD; solo código de servicio, controlador y vistas + dependencia npm.
