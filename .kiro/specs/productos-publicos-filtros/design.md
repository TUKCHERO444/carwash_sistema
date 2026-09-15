# Diseño — Listado público de productos con filtros

## Arquitectura

- **Controlador**: `PaginaInicioController::productosCategoria` — acepta parámetros query `letra`, `stock` y `q`; aplica filtros en cadena con `when()`; usa `paginate(20)->withQueryString()`; computa `$totalCategoria` (conteo total sin filtros).
- **Vista**: `resources/views/publica/productos-categoria.blade.php` — reutiliza `card-producto-grande` con `'junta' => true`.
- **JS**: `resources/js/publica/productos-filtros.js` — debounce de búsqueda y auto-submit en selects.

## Layout del encabezado

```
┌──────────────────────────────────────────────────────────────────┐
│                    CATEGORÍA (h2 centrado)                       │
│                                                                  │
│  ┌─ Filtros ──────────────────────┐  ┌─ Contador ─────────────┐ │
│  │  Orden alfabético | Stock      │  │  25 productos —        │ │
│  │  [Buscar...............]       │  │  mostrando 20           │ │
│  │  Limpiar filtros               │  └────────────────────────┘ │
│  └────────────────────────────────┘                             │
└──────────────────────────────────────────────────────────────────┘
```

## Patrón de tabla junta

Se reutiliza el mismo patrón de `grilla-marcas.blade.php`: grid `gap-px bg-steel-200 border border-steel-200 rounded-2xl overflow-hidden` con cada card usando `$junta = true` para suprimir su propio borde/redondeo.

## Orden predeterminado

Cuando no hay filtros activos: `productos.nombre ASC`.

Cuando se combinan `letra` y `stock`, `letra` tiene prioridad y `stock` se aplica como orden secundario.
