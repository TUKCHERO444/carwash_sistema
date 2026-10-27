# Análisis: reinicio/pérdida de datos en formularios (CRUD manual)

> Documento de análisis. **No se ha aplicado ningún cambio de código.**
> Objetivo: identificar los módulos en los que un dato inválido enviado al servidor
> provoca que el formulario "se reinicie" y el usuario pierda lo que había ingresado.

## 1. Alcance y método

Se revisaron todos los módulos con captura manual de datos: controladores
(`app/Http/Controllers`), vistas (`resources/views/**/create|edit|panel|confirmar`)
y módulos JS (`resources/js/**`).

El flujo analizado es el de **validación del lado servidor**:

1. El usuario envía el formulario.
2. Laravel ejecuta `$request->validate(...)`.
3. Si falla, redirige atrás con `withInput()` y `$errors`.
4. La vista debe repoblar los campos con `old(...)` y, en formularios dinámicos,
   el JS debe reconstruir las filas desde `old(...)` o desde inputs ocultos.

Se identificaron **tres causas raíz**:

| Causa | Descripción |
|---|---|
| **A. Vista sin `old()`** | El campo vuelve a su valor inicial o al valor de BD, aunque Laravel sí devolvió el input. |
| **B. Filas dinámicas no rehidratadas** | El JS arranca con `items = []` y no lee `old(...)` ni inputs ocultos, por lo que las líneas desaparecen (y los totales vuelven a 0). |
| **C. `back()` sin `withInput()`** | El controlador redirige sin conservar el input; se pierde todo el formulario aunque la vista use `old()`. |

> Nota: el motor cliente `resources/js/utils/validation.js` **no** es la causa. Evita el
> envío y conserva los valores en pantalla; el reinicio ocurre cuando el servidor
> rechaza y se vuelve a renderizar la vista.

## 2. Resumen ejecutivo (módulos afectados)

### 2.1 Prioridad alta — se pierde casi todo el formulario

| # | Módulo | Pantalla | Qué se pierde | Evidencia |
|---|---|---|---|---|
| 1 | **Ventas** | `ventas/create` | Carrito completo (`productos[]`), total (vuelve a `0.00`), toggles de descuento y porcentaje; el resto de escalares sí se conserva | `resources/js/ventas/create.js:82` (`let items = []`), `:419-420` (render + recálculo inicial); `resources/views/ventas/create.blade.php` (sin `old()` en toggles/porcentaje) |
| 2 | **Ventas (sin caja)** | `VentaController@store` | Toda la venta si no hay caja activa | `app/Http/Controllers/VentaController.php:81-82` → `back()->with('error_caja', true)` **sin** `withInput()` |
| 3 | **Ajustes de inventario** | `ajustes/create` | Todas las líneas de producto y el **motivo**; solo se conservan `tipo` y `observaciones` | `resources/views/ajustes/create.blade.php:66-67` (motivo sin `old()`), `:137` (`tbody` vacío, sin hidden de `old('lineas')`); `resources/js/ajustes/create.js:167` espera `input[name^="lineas["]` que nunca existe |
| 4 | **Lavados** | `lavados/create` | Servicios seleccionados (`servicios[]`) y precio estimado; sí se conservan placa, cliente, fecha, trabajadores | `resources/js/lavados/create.js:19` (`let items = []`), `:81` (render inicial) |
| 5 | **Cambio de aceite** | `cambio-aceite/create` | Productos seleccionados (`productos[]`) y total; sí se conservan datos del cliente, fecha y trabajadores | `resources/js/cambio-aceite/create.js:21` (`let items = []`), `:138` (render inicial) |
| 6 | **Contenido web** | `contenido-web/edit` | Todos los textos, checkboxes y curaduría de marcas vuelven a los valores de BD | `resources/views/contenido-web/edit.blade.php:48,68,82,99,125,142,168,185,215,222` (usa `$contenido->...`, **0 usos de `old()`**) |

### 2.2 Prioridad media — se pierde parte del formulario

| # | Módulo | Pantalla | Qué se pierde | Evidencia |
|---|---|---|---|---|
| 7 | **Devoluciones / cambios** | `ventas/devoluciones/create` | Cantidades por línea (vuelven a `0`); el producto sustituto queda oculto y sin nombre/precio/stock | `resources/views/ventas/devoluciones/create.blade.php:74` (`value="0"` fijo), `:105` (`bloque-cambio` oculto), `:121` (hidden con `old` pero sin UI) |
| 8 | **Caja — abrir** | `caja/panel` (modal) | `monto_inicial` y el modal se cierra | `resources/views/caja/panel.blade.php:195` (input sin `old()`); modal sin `:open` por errores |
| 9 | **Caja — egreso manual** | `caja/partials/modal-egreso` | `monto`, `descripcion`, `categoria`, `trabajador_id`, `tipo_pago` y el modal se cierra | `resources/views/caja/partials/modal-egreso.blade.php:8,12,17,26,35` (sin `old()`); `CajaController.php:194` (`back()->with('error')` sin `withInput()`) |
| 10 | **Lavados — confirmación** | `lavados/confirmar` + `LavadoController@procesarConfirmacion` | Datos editables de confirmación/pago si no hay caja | `app/Http/Controllers/LavadoController.php:118-120` (`back()->with('error_caja', true)` sin `withInput()`) |
| 11 | **Cambio de aceite — confirmación** | `cambio-aceite/confirmar` + `CambioAceiteController@procesarConfirmacion` | Datos editables de confirmación/pago si no hay caja | `app/Http/Controllers/CambioAceiteController.php:521-523` (sin `withInput()`) |

### 2.3 Casos esperados / no corregibles (no son bugs)

| Caso | Motivo |
|---|---|
| Contraseñas en `users/create`, `users/edit`, `cuenta/registro`, `cuenta/nueva-clave` | No se repueblan por seguridad; el resto de campos sí se conserva |
| Inputs `type="file"` (foto producto/servicio/trabajador/lavado/cambio-aceite) | El navegador no permite repoblar archivos por seguridad |
| `publica/cotiza` | Formulario de contacto con `action="#"`; aún no tiene backend ni validación |
| `asistencia` | Operación AJAX (full-sync), no recarga la página |
| `caja/panel` — cerrar caja | `efectivo_contado` y `observacion_arqueo` sí usan `old()` |

## 3. Detalle por módulo

### 3.1 Ventas (`ventas/create`)

- **Síntoma:** al fallar cualquier validación (por ejemplo, total inválido), el
  carrito desaparece y el total vuelve a `0.00`.
- **Causa:** `resources/js/ventas/create.js:82` inicializa `items = []` y no existe
  lectura de `old('productos')`; en `:419-420` se ejecuta `renderTabla()` y
  `recalcularTotales()`, sobrescribiendo el `old('total')` de la vista.
- **Se conserva:** `observacion`, `metodo_pago`, montos mixtos (`pagos/_form` usa
  `old("monto_*")`).
- **Se pierde:** líneas, total, checkbox "descuento por porcentaje", porcentaje y
  checkbox "descuento manual" (no tienen `old()`).
- **Agravante:** si no hay caja activa, `VentaController.php:82` redirige sin
  `withInput()`, perdiendo incluso los escalares.

### 3.2 Ajustes de inventario (`ajustes/create`)

- **Síntoma:** las líneas agregadas desaparecen y el motivo se vacía.
- **Causa:** la tabla se renderiza solo por JS (`tbody` vacío en la vista,
  `create.blade.php:137`). El JS intenta reconstruir desde
  `input[name^="lineas["]` (`create.js:167`), pero la vista nunca imprime esos
  inputs con `old('lineas')`.
- **Se conserva:** `tipo` (`old`) y `observaciones` (`old`, línea 93).
- **Se pierde:** `lineas[]` y `motivo` (textarea sin `old`, líneas 66-67).

### 3.3 Lavados (`lavados/create`)

- **Síntoma:** los servicios seleccionados desaparecen y el precio estimado vuelve
  a `0.00`.
- **Causa:** `resources/js/lavados/create.js:19` (`items = []`) y render inicial en
  `:81`; no se lee `old('servicios')`.
- **Se conserva:** `tipo_vehiculo_id`, `placa`, `nombre`, `dni`, `telefono`,
  `fecha`, `trabajadores_ids` (todos con `old()` en la vista).

### 3.4 Cambio de aceite (`cambio-aceite/create`)

- **Síntoma:** los productos seleccionados y el total desaparecen.
- **Causa:** `resources/js/cambio-aceite/create.js:21` (`items = []`) y render
  inicial en `:138`; no se lee `old('productos')`.
- **Se conserva:** datos del cliente, `fecha`, `descripcion`, `trabajadores_ids`.

### 3.5 Devoluciones / cambios (`ventas/devoluciones/create`)

- **Síntoma:** las cantidades ingresadas vuelven a `0`.
- **Causa:** el input se renderiza con `value="0"` fijo
  (`create.blade.php:74`), sin `old("lineas.$i.cantidad")`.
- **Sustituto:** `producto_nuevo_id` sí se conserva en un hidden (línea 121),
  pero `#bloque-cambio` está `hidden` (línea 105) y no se repinta
  nombre/precio/stock; el usuario debe volver a buscar el producto.
- **Se conserva:** `tipo`, `motivo`, `cantidad_nueva` (aunque el bloque esté oculto).

### 3.6 Contenido web (`contenido-web/edit`)

- **Síntoma:** cualquier error de validación (por ejemplo, un título de más de 120
  caracteres) revierte **todos** los textos y checkboxes a lo guardado en BD,
  incluyendo cambios no guardados en otras secciones.
- **Causa:** la vista usa exclusivamente `$contenido->textValue(...)` /
  `$contenido->boolValue(...)` y `$estado` de marcas; no hay ningún `old()`.
- **Riesgo adicional:** los checkboxes no enviados (desmarcados) se vuelven a marcar
  según BD, por lo que el usuario puede creer que su cambio se guardó.

### 3.7 Caja (`caja/panel` y modal de egreso)

- **Abrir caja:** `monto_inicial` sin `old()` (`panel.blade.php:195`). Además, el
  modal no se reabre automáticamente tras el error (el componente `x-modal` solo
  abre con `:open`, y aquí no se pasa).
- **Egreso manual:** todos los campos sin `old()` (`modal-egreso.blade.php:8,12,17,26,35`);
  tampoco se reabre el modal.
- **Errores de servicio:** `CajaController.php:98` y `:194` usan
  `back()->with('error', ...)` sin `withInput()`.

### 3.8 Confirmaciones de lavado y cambio de aceite

- **Síntoma:** si el usuario confirma el pago sin caja activa, se pierden los datos
  editables de la pantalla de confirmación.
- **Causa:** `LavadoController.php:120` y `CambioAceiteController.php:523` hacen
  `back()->with('error_caja', true)` sin `withInput()`.
- **Contraste:** `DevolucionVentaController.php:94` sí usa `withInput()` en el mismo
  escenario.

## 4. Módulos verificados sin pérdida de datos

Estos módulos usan `old()` de forma correcta (campos escalares) y/o rehidratan sus
filas dinámicas:

| Módulo | Observación |
|---|---|
| Compras (`compras/create`, `compras/edit`) | `window.oldDetalle` + `old('detalle')` reconstruye las líneas; proveedor y cabecera con `old()` |
| Clientes | Todos los campos con `old()` |
| Trabajadores | Todos los campos con `old()`; la consulta DNI solo rellena campos vacíos |
| Vehículos | Todos los campos con `old()` |
| Productos / Categorías / Marcas | Campos con `old()` (la imagen no se puede repoblar) |
| Servicios | Campos con `old()` (la imagen no se puede repoblar) |
| Proveedores | Campos con `old()` |
| Tipos de vehículo | Campos con `old()` |
| Métodos de pago | Campos con `old()` |
| Roles | `name` y `permissions[]` con `old()` |
| Usuarios | `name`, `email`, `role` con `old()`; contraseñas por diseño |
| Caja — cerrar | `efectivo_contado` y `observacion_arqueo` con `old()` |
| Cuenta pública | `registro`, `login`, `recuperacion`, `verificacion` con `old()`; contraseñas por diseño |

## 5. Matriz rápida de causas

| Módulo | A. Sin `old()` | B. Filas no rehidratadas | C. `back()` sin `withInput()` |
|---|:---:|:---:|:---:|
| Ventas create | Parcial (toggles) | **Sí** | **Sí** (sin caja) |
| Ajustes create | **Sí** (motivo) | **Sí** | No |
| Lavados create | No | **Sí** | No |
| Cambio aceite create | No | **Sí** | No |
| Devoluciones create | **Sí** (cantidades) | Parcial (sustituto) | No (sí usa `withInput`) |
| Contenido web | **Sí** (todo) | — | No |
| Caja abrir | **Sí** | — | No |
| Caja egreso | **Sí** (todo) | — | **Sí** (catch) |
| Lavados confirmar | No | — | **Sí** (sin caja) |
| Cambio aceite confirmar | No | — | **Sí** (sin caja) |

## 6. Recomendaciones (para una fase posterior, sin implementar aquí)

1. **Estandarizar `old()`** en todas las vistas: `value="{{ old('campo', $modelo->campo ?? '') }}"`,
   `@checked(old('flag', $modelo->flag))` y `@selected(old('select', $modelo->select))`.
2. **Rehidratar filas dinámicas** desde `old()` en `ventas/create`, `lavados/create`,
   `cambio-aceite/create`, `ajustes/create` y `devoluciones/create`; los tres últimos
   ya tienen la infraestructura JS, solo falta exponer los datos (`window.oldX = @json(old(...))`)
   o renderizar hidden inputs.
3. **No perder el formulario por caja cerrada:** agregar `->withInput()` en
   `VentaController@store`, `LavadoController@procesarConfirmacion` y
   `CambioAceiteController@procesarConfirmacion`.
4. **Reabrir modales con errores** en `caja/panel` y `modal-egreso` (por ejemplo,
   `:open="$errors->any()"` acotado al modal correspondiente).
5. **Contenido web:** repoblar desde `old()` con fallback a `$contenido`, incluyendo
   `marcas_web[]` y `marcas_orden[...]`.
6. **Caja egreso/abrir:** agregar `old()` a todos los campos y `withInput()` en los
   `catch` de `CajaController@abrir` y `@registrarEgreso`.
7. **Tests de regresión:** un feature test por módulo que envíe un payload inválido y
   verifique que `assertSessionHasOldInput('campo')` (o el render) conserva los datos.
