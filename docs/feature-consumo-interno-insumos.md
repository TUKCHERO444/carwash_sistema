# Insumos de consumo interno y salidas por consumo

## Objetivo

Distinguir los productos para **venta/operación facturable** de los
**insumos de consumo interno** (shampoo, ceras, siliconas y otros usados en
lavados), con ingreso, permanencia y salida del inventario controlados y
trazables. Sin consumo automático por lavado, sin recetas ni estimaciones:
el negocio no mide con precisión el consumo por atención y no se inventa
esa precisión.

## Alcance

- `productos.destino`: `comercial` (histórico, default) o `consumo_interno`
  (editable en crear/editar, badge en el listado). Informativo y de control:
  **no bloquea** ventas, cambios de aceite ni lavados (un producto puede
  tener ambos usos según la operación).
- Compras sin cambios: el insumo ingresa por recepción normal (stock,
  inventario, Kardex, caja, costo).
- Nuevo tipo de ajuste **`consumo_interno`** (salida general, no atada a un
  lavado): reutiliza el flujo de ajustes (`AJU-XXXX`, motivo obligatorio,
  líneas con lock, Kardex fuente `consumo_interno`, auditoría
  `crear ajuste`). Cantidades positivas se normalizan a salida.
- `ajustes_inventario.tipo` convertido de ENUM a string(20) (los tipos
  válidos viven en `AjusteInventario::TIPOS`; el controlador ya no duplica
  el catálogo).
- Lavados intactos: confirmar un lavado no descuenta insumos.
- Merma, daño, ajustes y conteo físico intactos y diferenciados.
- Kardex: fuente `consumo_interno` filtrable en el listado y reportes.

## Rehabilitación previa (requerida por la feature)

El flujo de ajustes nunca llegó a funcionar y se reparó como prerrequisito:

- `KardexService::registrar()` era `private` pero lo llamaba
  `AjusteInventarioService` (fatal en todo ajuste) → ahora público, único
  punto de escritura de Kardex.
- `resolveMovimiento()` retornaba 2 elementos y se desestructuraba en 3
  (`Undefined array key 2`) → retorna costo explícito.
- `prepararLineas()` trataba cualquier `conteo_fisico` presente (el JS envía
  `"undefined"`) como conteo y vaciaba el stock → solo aplica en tipo
  `conteo_fisico` con valor numérico.
- Validación `required_without` apuntaba a campos top-level inexistentes →
  reglas por tipo (cantidad con signo salvo conteo).
- Etiqueta de auditoría anotada antes del `create` (el observer la consume
  en el evento `created`).

## Archivos clave

- Migraciones `2026_10_04_000004` (destino), `000005` (tipo→string),
  `000006` (CHECK `consumo_interno`).
- `Producto` (`DESTINOS`, `etiquetaDestino`), `ProductoController`,
  vistas `productos/create|edit|index`.
- `AjusteInventario::TIPOS`, `AjusteInventarioController`,
  `AjusteInventarioService`, vistas `ajustes/create|index`,
  `resources/js/ajustes/create.js` (motivo para consumo interno).
- `KardexService::FUENTES` + `registrar()` público.
- Tests: `tests/Feature/ConsumoInternoTest.php` (7 tests); se corrigió
  `KardexTest::test_cada_fuente_registrable_es_filtrable` (orígenes con
  relleno `OR-%02d` para evitar subcadenas al crecer el catálogo).

## Reglas

- Destino a nivel **producto** (el stock es por producto; una línea de
  compra no puede partir existencias). Productos de doble uso quedan
  `comercial` y sus consumos se registran como salidas igual.
- Consumo interno ≠ merma/daño (utilización normal vs. situaciones
  excepcionales).
- Sin costeo por lavado, sin consumo por ticket, sin predicción.

## Verificación

- `ConsumoInternoTest` + suite total en verde (salvo 2 preexistentes en
  `main`). Spec: `.kiro/specs/ajustes-kardex-completo/` (tarea 13).
