# Orden de atención — Resumen de alcance

## 1. Lo que se entendió

El negocio necesita gestionar el **orden de atención** de los vehículos a partir de los
tickets de **lavados** y **cambio de aceite**:

- Cada ticket nace con un **timestamp de registro** (`created_at` + nueva columna
  `atencion_inicio_at`): ese momento es el **inicio de la orden de atención** y define
  la posición en la cola.
- Al **confirmar/pagar** el ticket (`procesarConfirmacion`, que ya exige caja activa y
  método de pago) se registra el **fin de la atención** (`atencion_fin_at`) y se
  persiste la **duración solo en minutos/horas** (`tiempo_atencion_minutos`, entero).
- Los listados de tickets pendientes muestran la **cola de arriba a abajo**: arriba los
  últimos registrados, abajo los más antiguos, cada uno con su número de orden y hora
  de registro; los culminados muestran el tiempo de atención.

> Decisión clave: hoy `confirmado` == `pagado` (la confirmación ya cobra: método de
> pago + caja + descuentos/kardex). Por eso esta fase mide
> **registro → confirmación/pago** como tiempo de atención, sin romper caja,
> reportes, dashboard ni auditoría. Separar "confirmar" de "pagar" queda como
> evolución futura (ver §5).

## 2. Lo implementado

### Base de datos (migración `2026_10_03_000001`)

En `lavados` y `cambio_aceites`, tres columnas nuevas (todas nulables):

| Columna | Tipo | Significado |
|---|---|---|
| `atencion_inicio_at` | datetime | Inicio de la orden (= timestamp de registro) |
| `atencion_fin_at` | datetime | Fin de la atención (confirmación/pago) |
| `tiempo_atencion_minutos` | unsigned int | Duración persistida, solo minutos |

La migración incluye **backfill**: filas existentes toman
`atencion_inicio_at = created_at`; las confirmadas además
`atencion_fin_at = updated_at` y duración = diferencia en minutos.

### Lógica (`app/Services/TiempoAtencionService.php`)

Punto único de cálculo, reutilizable por ambos módulos:

- `minutosEntre(inicio, fin)`: minutos completos, nunca negativo.
- `etiqueta(minutos)`: presentación en minutos/horas (`45 min`, `2 h`, `2 h 15 min`).
- `datosFinalizacion(inicio, fin)`: par listo para persistir.

### Modelos (`Lavado`, `CambioAceite`)

- Nuevos campos en `$fillable` y `$casts` (`datetime`/`integer`).
- Accesor `tiempo_atencion_label` (etiqueta minutos/horas).
- Scopes `colaAtencion()` (`created_at DESC, id DESC`) y
  `culminadosPorAtencion()` (`atencion_fin_at DESC, ...`).

### Controladores (`LavadoController`, `CambioAceiteController`)

- `store()`: fija `atencion_inicio_at = now()` al registrar el ticket.
- `procesarConfirmacion()`: fija `atencion_fin_at = now()`, calcula y guarda
  `tiempo_atencion_minutos` (con fallback a `created_at` para tickets legacy).
- `index()` (pendientes): orden de cola — **arriba los últimos registrados**.
  En lavados se corrigió el orden anterior (`fecha operativa DESC`) a
  `created_at DESC`, que es el verdadero orden de llegada.
- `confirmados()`: ordenados por fin de atención descendente.

### Vistas

- Pendientes (ambos módulos): columnas **N.º** (correlativo de cola con paginación)
  y **Registro** (hora `d/m H:i`).
- Confirmados (ambos módulos): columna **Tiempo atención** (`45 min`, `1 h 30 min`).
- Tickets imprimibles (ambos): sección **Orden de atención** con inicio, fin,
  duración, o estado `En cola` si sigue pendiente.

### Pruebas (`tests/Feature/OrdenAtencion/`)

- `OrdenAtencionTest`: inicio al registrar, fin+duración al confirmar (ambos
  módulos vía HTTP), orden de cola últimos-primero y orden de culminados.
- `TiempoAtencionPropertyTest`: 2 propiedades × 100 iteraciones (minutos nunca
  negativos + etiqueta solo minutos/horas con round-trip exacto).

## 3. Archivos tocados

```
database/migrations/2026_10_03_000001_add_tiempos_atencion_to_lavados_y_cambio_aceites.php (nuevo)
app/Services/TiempoAtencionService.php (nuevo)
app/Models/Lavado.php / app/Models/CambioAceite.php
app/Http/Controllers/LavadoController.php / CambioAceiteController.php
database/factories/LavadoFactory.php / CambioAceiteFactory.php
resources/views/lavados/{pendientes,confirmados,ticket}.blade.php
resources/views/cambio-aceite/{pendientes,confirmados,ticket}.blade.php
tests/Feature/OrdenAtencion/{OrdenAtencionTest,TiempoAtencionPropertyTest}.php (nuevos)
```

## 4. Supuestos y límites de esta fase

1. **Confirmar = pagar**: no se crea un estado nuevo; el contador cierra en la
   confirmación actual. La duración incluye espera en cola + servicio.
2. **Granularidad en minutos** (entero): no se guardan segundos.
3. **Sin contador en vivo**: pendientes muestran hora de inicio; el tiempo final
   aparece al confirmar. El live-timer es evolución (ver §5).
4. **Colas separadas** por módulo (lavados y cambio tienen sus listados); la cola
   unificada es evolución.

## 5. Cómo escalar a futuro desde esta base

1. **Separar confirmar vs pagar** (flujo `pendiente → en_atencion → pagado`):
   - Nuevo valor de `estado` + endpoint `finalizar/pagar`; `atencion_inicio_at`
     pasaría a fijarse al iniciar el servicio y el cierre ya existe tal cual.
     `TiempoAtencionService` se reutiliza sin cambios.
2. **Contador en vivo en pendientes**: con `atencion_inicio_at` ya expuesto, un
   módulo JS (`resources/js/orden-atencion/`) puede pintar el transcurrido con
   `setInterval` y alertas por SLA (p. ej. > 60 min en cola).
3. **Cola unificada lavados + aceite**: una vista que combine ambas tablas
   ordenando por `atencion_inicio_at` (índice recomendado a futuro) + panel TV.
4. **Métricas**: tiempo promedio/mediana por módulo, trabajador y franja horaria
   usando `tiempo_atencion_minutos` (consultas triviales sobre enteros), para
   reportes y bonos por productividad.
5. **SLA y priorización**: reglas por tipo de vehículo/servicio (p. ej. camiones
   con ventana distinta) reordenando la cola sin tocar el registro base.
