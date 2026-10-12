# Especificación para generar el Diagrama Entidad-Relación (DER)

> Este documento es un **brief técnico** pensado para entregárselo a una IA generadora de diagramas.
> Describe la fuente, el alcance, la notación esperada, el diccionario completo de tablas y el
> catálogo de relaciones del sistema de lavado (`carwash_sistema`).
> Con esta información la IA debe poder producir un DER correcto y completo **sin necesidad de leer
> el `schema.sql` original**.

---

## 1. Objetivo

Generar el **Diagrama Entidad-Relación** de la base de datos del sistema web de lavado de autos a
partir del archivo `database/schema.sql` (volcado de MariaDB 10.4 / phpMyAdmin 5.2.1, motor InnoDB,
`utf8mb4_unicode_ci`).

El diagrama debe representar:

1. Todas las **entidades de negocio** (tablas del dominio).
2. Sus **atributos** (columnas) con tipo y claves (PK / FK / UNIQUE).
3. Las **relaciones** entre entidades con su **cardinalidad** (1:1, 1:N, N:M) y la regla de borrado
   (`CASCADE` / `SET NULL` / sin acción).
4. La **agrupación por módulos funcionales** (ventas, lavado, caja, inventario, compras, cuenta,
   etc.) mediante colores o clusters.

---

## 2. Alcance: qué tablas incluir y cuáles omitir

El volcado incluye tablas propias del framework Laravel y de paquetes auxiliares que **NO deben
dibujarse** en el DER de negocio (o, si se incluyen, deben ir en un cluster separado llamado
"Sistema / Framework" y en gris). Por defecto: **excluirlas**.

### 2.1 Tablas del dominio (DIBUJAR) — 35 tablas

| Módulo | Tablas |
|---|---|
| Clientes y vehículos | `clientes`, `vehiculos`, `tipos_vehiculo` |
| Personal | `trabajadores`, `asistencias` |
| Catálogos | `categorias`, `marcas`, `productos`, `servicios`, `proveedores` |
| Lavado | `lavados`, `detalle_servicios`, `lavado_trabajadores` |
| Cambio de aceite | `cambio_aceites`, `cambio_productos`, `cambio_aceite_trabajadores` |
| Ventas | `ventas`, `detalle_ventas` |
| Devoluciones | `devoluciones_venta`, `detalle_devoluciones_venta` |
| Caja | `cajas`, `egresos_caja` |
| Compras | `compras`, `detalle_compras` |
| Inventario | `movimientos_kardex`, `ajustes_inventario`, `detalle_ajustes_inventario` |
| Web pública / carrito | `contenido_web`, `carritos`, `carrito_items` |
| Cuenta y seguridad | `users`, `customer_user_links`, `one_time_tokens`, `social_identities` |
| Auditoría | `registros_auditoria` |
| RBAC (opcional) | `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` |

### 2.2 Tablas a EXCLUIR (infraestructura de Laravel)

`migrations`, `cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, `failed_jobs`,
`password_reset_tokens`.

> Regla: si la IA decide mostrarlas, debe agruparlas en un recuadro gris "Framework/Laravel" y **no**
> trazar sus relaciones hacia el dominio.

---

## 3. Notación y formato de salida esperado

La IA debe entregar **el código fuente del diagrama**, no una imagen. Se prefieren estos formatos en
este orden:

1. **Mermaid `erDiagram`** (formato primario, renderizable en GitHub, Notion, draw.io, etc.).
2. **DBML** (dbdiagram.io) como formato secundario opcional.
3. **PlantUML** como alternativa.

### 3.1 Reglas de notación (obligatorias)

- **Cardinalidad:** usar notación *pata de gallo* (crow's foot).
  - `||--o{` = uno a muchos (obligatorio en el "uno", opcional/muchos en el otro).
  - `||--||` = uno a uno.
  - `}o--o{` = muchos a muchos (cuando exista tabla pivote, modelarla como entidad y trazar
    **dos** relaciones 1:N hacia la pivote, en lugar de una N:M directa).
- **Dirección:** la flecha siempre apunta de la tabla que **tiene la FK** hacia la tabla referenciada
  (es decir, del lado "muchos" al lado "uno").
- **Claves:** marcar `PK`, `FK` y `UK` (unique). Si una columna es FK, indicar `FK` con el destino
  entre el comentario del atributo.
- **Nombre de la relación:** etiquetar cada relación con un verbo breve en español (p. ej.
  `"pertenece a"`, `"genera"`, `"contiene"`).
- **Tipos de datos:** simplificar a tipos legibles (`bigint`, `varchar`, `decimal`, `date`,
  `datetime`, `timestamp`, `enum`, `text`, `json`, `tinyint`). No reproducir el ancho exacto salvo
  que sea relevante.
- **Idioma:** todos los nombres de entidades, atributos y descripciones van **en español** (ya lo
  están en la BD; no traducir).
- **Solo columnas relevantes:** incluir PK, FK y atributos descriptivos/importantes. Las columnas
  `created_at` y `updated_at` pueden mostrarse una sola vez de forma simbólica o resumirse como
  `auditoría: created_at, updated_at`, para no saturar el diagrama.

### 3.2 Agrupación visual por módulos (colores sugeridos)

| Módulo | Color sugerido |
|---|---|
| Clientes / Vehículos / Personal | Azul |
| Catálogos (categorías, marcas, productos, servicios, proveedores) | Verde |
| Lavado / Cambio de aceite | Naranja |
| Ventas / Devoluciones | Amarillo |
| Caja | Rojo |
| Compras / Inventario | Morado |
| Web pública / Cuenta | Cian |
| RBAC / Auditoría | Gris |

---

## 4. Diccionario de entidades (tablas y columnas)

Convenciones de la tabla: `PK` = clave primaria, `FK→t.col` = clave foránea, `UK` = única,
`NULL` = admite nulos.

### 4.1 `clientes`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| dni | varchar(8) | | NULL | Documento de identidad (8 dígitos) |
| nombre | varchar(50) | | NULL | Nombres |
| apellido_paterno | varchar(50) | | NULL | Apellido paterno |
| apellido_materno | varchar(50) | | NULL | Apellido materno |
| telefono | varchar(20) | | NULL | Teléfono de contacto |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.2 `vehiculos` (vehículo físico, antes `automotores`)

> La PK es la **placa** (no hay `id`).

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| placa | varchar(7) | PK | NO | Placa del vehículo (7 caracteres) |
| cliente_id | bigint | FK→clientes.id | NO | Dueño del vehículo (ON DELETE CASCADE) |
| marca | varchar(100) | | NULL | Marca del vehículo |
| modelo | varchar(100) | | NULL | Modelo |
| serie | varchar(100) | | NULL | Serie |
| color | varchar(50) | | NULL | Color |
| motor | varchar(100) | | NULL | N.º de motor |
| vin | varchar(100) | | NULL | VIN |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.3 `vehiculos`

> Catálogo de tipos/categorías de vehículo con su precio base asociado al lavado.

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| nombre | varchar(100) | | NO | Nombre del tipo de vehículo |
| descripcion | text | | NULL | Descripción |
| precio | decimal(10,2) | | NO | Precio base |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.4 `trabajadores`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| dni | char(8) | UK | NULL | Documento de identidad |
| nombre | varchar(50) | | NO | Nombres |
| apellido_paterno | varchar(50) | | NULL | Apellido paterno |
| apellido_materno | varchar(50) | | NULL | Apellido materno |
| foto | text | | NULL | URL de foto (Cloudinary) |
| pago_diario | decimal(8,2) | | NULL | Pago diario (default 50.00) |
| estado | tinyint(1) | | NO | Activo/inactivo |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.5 `asistencias`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| trabajador_id | bigint | FK→trabajadores.id | NO | Trabajador (CASCADE) |
| fecha | date | UK (junto a trabajador_id) | NO | Fecha de asistencia |
| hora_entrada | time | | NO | Hora de entrada |
| created_at / updated_at | timestamp | | NULL | Auditoría |

> Restricción única compuesta: (`fecha`, `trabajador_id`) → un registro por trabajador por día.

### 4.6 `categorias`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| nombre | varchar(150) | UK | NO | Nombre de categoría |
| slug | varchar(160) | UK | NULL | Slug para URL |
| descripcion | text | | NULL | Descripción |
| contador_productos | int | | NO | Contador desnormalizado de productos |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.7 `marcas`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| nombre | varchar(150) | UK | NO | Nombre de marca |
| descripcion | text | | NULL | Descripción |
| foto | varchar(255) | | NULL | Imagen de marca |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.8 `productos`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| nombre | varchar(150) | | NO | Nombre del producto |
| descripcion | varchar(100) | | NULL | Descripción breve |
| precio_compra | decimal(10,2) | | NO | Precio de compra |
| precio_venta | decimal(10,2) | | NO | Precio de venta |
| stock | int | | NO | Stock disponible para venta |
| inventario | int | | NO | Cantidad física en inventario |
| categoria_id | bigint | FK→categorias.id | NO | Categoría |
| marca_id | bigint | FK→marcas.id | NO | Marca |
| precio_incoherente | tinyint(1) | | NO | Marca de precio inconsistente |
| activo | tinyint | | NO | Activo/inactivo |
| destino | varchar(20) | | NO | Destino (`comercial`, etc.) |
| foto | text | | NULL | URL de foto (Cloudinary) |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.9 `servicios`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| nombre | varchar(100) | | NO | Nombre del servicio |
| descripcion | varchar(100) | | NULL | Descripción |
| precio | decimal(10,2) | | NO | Precio del servicio |
| activo | tinyint(1) | | NO | Activo/inactivo |
| orden | int | | NO | Orden de visualización (web) |
| icono | varchar(30) | | NULL | Icono (web) |
| imagen | varchar(255) | | NULL | Imagen (web) |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.10 `lavados`

> Entidad central del servicio de lavado (antes llamada `ingresos`).

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| cliente_id | bigint | FK→clientes.id | NO | Cliente (CASCADE) |
| automotor_id | varchar(7) | FK→vehiculos.placa | NULL | Vehículo por placa (SET NULL) |
| vehiculo_id | bigint | FK→vehiculos.id | NO | Tipo de vehículo (CASCADE) |
| fecha | date | | NO | Fecha del lavado |
| estado | enum('pendiente','confirmado') | | NO | Estado |
| foto | text | | NULL | Foto del servicio (Cloudinary) |
| precio | decimal(10,2) | | NO | Precio base |
| total | decimal(10,2) | | NO | Total cobrado |
| metodo_pago | enum('efectivo','yape','izipay','mixto') | | NO | Método de pago |
| monto_efectivo / monto_yape / monto_izipay | decimal(10,2) | | NULL | Desglose por medio de pago |
| user_id | bigint | FK→users.id | NO | Usuario que registró |
| caja_id | bigint | FK→cajas.id | NULL | Caja de la operación (SET NULL) |
| atencion_inicio_at | datetime | | NULL | Inicio de atención |
| atencion_fin_at | datetime | | NULL | Fin de atención |
| tiempo_atencion_minutos | int | | NULL | Duración calculada |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.11 `detalle_servicios` (pivote N:M lavado ↔ servicio)

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| lavado_id | bigint | FK→lavados.id | NO | Lavado (CASCADE) |
| servicio_id | bigint | FK→servicios.id | NO | Servicio (CASCADE) |
| created_at / updated_at | timestamp | | NULL | Auditoría |

> Único (`lavado_id`, `servicio_id`).

### 4.12 `lavado_trabajadores` (pivote N:M lavado ↔ trabajador)

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| lavado_id | bigint | FK→lavados.id | NO | Lavado (CASCADE) |
| trabajador_id | bigint | FK→trabajadores.id | NO | Trabajador (CASCADE) |
| created_at / updated_at | timestamp | | NULL | Auditoría |

> Único (`lavado_id`, `trabajador_id`).

### 4.13 `cambio_aceites`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| cliente_id | bigint | FK→clientes.id | NO | Cliente (CASCADE) |
| automotor_id | varchar(7) | FK→vehiculos.placa | NULL | Vehículo por placa (SET NULL) |
| ~~trabajador_id~~ | — | ELIMINADA (2026-10-10): la asignación vive solo en `cambio_aceite_trabajadores` | — |
| fecha | date | | NO | Fecha |
| estado | enum('pendiente','confirmado') | | NO | Estado |
| precio | decimal(10,2) | | NO | Precio base |
| total | decimal(10,2) | | NO | Total |
| metodo_pago | enum('efectivo','yape','izipay','mixto') | | NO | Método de pago |
| monto_efectivo / monto_yape / monto_izipay | decimal(10,2) | | NULL | Desglose por medio de pago |
| descripcion | text | | NULL | Descripción |
| foto | text | | NULL | Foto (Cloudinary) |
| user_id | bigint | FK→users.id | NO | Usuario que registró |
| caja_id | bigint | FK→cajas.id | NULL | Caja (SET NULL) |
| atencion_inicio_at / atencion_fin_at | datetime | | NULL | Tiempos de atención |
| tiempo_atencion_minutos | int | | NULL | Duración |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.14 `cambio_productos` (pivote N:M cambio_aceite ↔ producto)

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| cambio_aceite_id | bigint | FK→cambio_aceites.id | NO | Cambio de aceite (CASCADE) |
| producto_id | bigint | FK→productos.id | NO | Producto (CASCADE) |
| cantidad | int | | NO | Cantidad usada |
| precio | decimal(10,2) | | NO | Precio unitario |
| total | decimal(10,2) | | NO | Subtotal |
| created_at / updated_at | timestamp | | NULL | Auditoría |

> Único (`cambio_aceite_id`, `producto_id`).

### 4.15 `cambio_aceite_trabajadores` (pivote N:M cambio_aceite ↔ trabajador)

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| cambio_aceite_id | bigint | FK→cambio_aceites.id | NO | Cambio de aceite (CASCADE) |
| trabajador_id | bigint | FK→trabajadores.id | NO | Trabajador (CASCADE) |
| created_at / updated_at | timestamp | | NULL | Auditoría |

> Único (`cambio_aceite_id`, `trabajador_id`).

### 4.16 `ventas`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| correlativo | varchar(255) | UK | NO | Número de venta |
| observacion | text | | NULL | Observación |
| subtotal | decimal(10,2) | | NO | Subtotal |
| total | decimal(10,2) | | NO | Total |
| metodo_pago | enum('efectivo','yape','izipay','mixto') | | NO | Método de pago |
| monto_efectivo / monto_yape / monto_izipay | decimal(10,2) | | NULL | Desglose por medio de pago |
| user_id | bigint | FK→users.id | NO | Usuario |
| caja_id | bigint | FK→cajas.id | NULL | Caja (SET NULL) |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.17 `detalle_ventas`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| venta_id | bigint | FK→ventas.id | NO | Venta (CASCADE) |
| producto_id | bigint | FK→productos.id | NO | Producto |
| cantidad | int | | NO | Cantidad |
| precio_unitario | decimal(10,2) | | NO | Precio unitario |
| subtotal | decimal(10,2) | | NO | Subtotal |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.18 `devoluciones_venta`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| correlativo | varchar(255) | UK | NO | Número de devolución |
| venta_id | bigint | FK→ventas.id | NO | Venta original |
| venta_nueva_id | bigint | FK→ventas.id | NULL | Venta de cambio (SET NULL) |
| caja_id | bigint | FK→cajas.id | NULL | Caja (SET NULL) |
| tipo | enum('devolucion','cambio') | | NO | Tipo de operación |
| motivo | varchar(500) | | NO | Motivo |
| total_devuelto | decimal(10,2) | | NO | Monto devuelto |
| user_id | bigint | FK→users.id | NO | Usuario |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.19 `detalle_devoluciones_venta`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| devolucion_id | bigint | FK→devoluciones_venta.id | NO | Devolución (CASCADE) |
| detalle_venta_id | bigint | FK→detalle_ventas.id | NO | Línea de venta devuelta |
| producto_id | bigint | FK→productos.id | NO | Producto |
| cantidad | int | | NO | Cantidad devuelta |
| precio_unitario | decimal(10,2) | | NO | Precio unitario |
| subtotal | decimal(10,2) | | NO | Subtotal |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.20 `cajas`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| user_id | bigint | FK→users.id | NO | Usuario que abrió la caja |
| estado | enum('abierta','cerrada') | | NO | Estado |
| monto_inicial | decimal(10,2) | | NO | Monto inicial |
| fecha_apertura | datetime | | NO | Apertura |
| fecha_cierre | datetime | | NULL | Cierre |
| efectivo_esperado | decimal(10,2) | | NULL | Efectivo esperado |
| efectivo_contado | decimal(10,2) | | NULL | Efectivo contado |
| diferencia | decimal(10,2) | | NULL | Diferencia |
| arqueo_user_id | bigint | FK→users.id | NULL | Usuario que hizo el arqueo |
| fecha_arqueo | datetime | | NULL | Fecha de arqueo |
| observacion_arqueo | varchar(500) | | NULL | Observación del arqueo |
| created_at / updated_at | timestamp | | NULL | Auditoría |

> Nota: `user_id` y `arqueo_user_id` son **dos FK distintas hacia `users`** (dos relaciones 1:N).

### 4.21 `egresos_caja`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| caja_id | bigint | FK→cajas.id | NO | Caja |
| monto | decimal(10,2) | | NO | Monto |
| descripcion | varchar(500) | | NO | Descripción |
| tipo_pago | varchar(20) | | NO | Tipo de pago |
| categoria | varchar(30) | | NULL | Categoría/clasificación |
| trabajador_id | bigint | FK→trabajadores.id | NULL | Trabajador (p. ej. pago de jornal) |
| user_id | bigint | FK→users.id | NO | Usuario |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.22 `compras`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| correlativo | varchar(20) | UK | NULL | Número de compra |
| proveedor_id | bigint | FK→proveedores.id | NO | Proveedor |
| fecha | date | | NO | Fecha |
| tipo_documento | varchar(20) | | NULL | Tipo de documento |
| numero_documento | varchar(50) | | NULL | Número de documento |
| estado | enum('borrador','recibida','anulada') | | NO | Estado |
| subtotal | decimal(10,2) | | NO | Subtotal |
| total | decimal(10,2) | | NO | Total |
| observaciones | text | | NULL | Observaciones |
| user_id | bigint | FK→users.id | NO | Usuario |
| caja_id | bigint | FK→cajas.id | NULL | Caja |
| egreso_caja_id | bigint | FK→egresos_caja.id | NULL | Egreso asociado |
| fecha_recepcion | timestamp | | NULL | Recepción |
| fecha_anulacion | timestamp | | NULL | Anulación |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.23 `detalle_compras`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| compra_id | bigint | FK→compras.id | NO | Compra (CASCADE) |
| producto_id | bigint | FK→productos.id | NO | Producto |
| cantidad | int | | NO | Cantidad |
| costo_unitario | decimal(10,2) | | NO | Costo unitario |
| subtotal | decimal(10,2) | | NO | Subtotal |
| created_at / updated_at | timestamp | | NULL | Auditoría |

> Único (`compra_id`, `producto_id`).

### 4.24 `proveedores`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| ruc | char(11) | UK | NO | RUC |
| razon_social | varchar(150) | | NO | Razón social |
| direccion | varchar(200) | | NULL | Dirección |
| estado_tributario | varchar(100) | | NULL | Estado tributario SUNAT |
| condicion | varchar(100) | | NULL | Condición SUNAT |
| estado | char(1) | | NO | Activo (default '1') |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.25 `movimientos_kardex`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| producto_id | bigint | FK→productos.id | NO | Producto (CASCADE) |
| tipo | enum('entrada','salida') | | NO | Tipo de movimiento |
| fuente | varchar(30) | | NO | Origen lógico (`venta`, `compra`, `ajuste`, `consumo_interno`, ...) |
| origen_id | varchar(20) | | NO | Id del documento origen (referencia lógica, no FK física) |
| cantidad | int | | NO | Cantidad |
| stock_antes | int | | NO | Stock antes |
| stock_despues | int | | NO | Stock después |
| costo_unitario | decimal(10,2) | | NULL | Costo unitario |
| costo_total | decimal(12,2) | | NULL | Costo total |
| usuario_id | bigint | FK→users.id | NO | Usuario |
| fecha_movimiento | timestamp | | NO | Fecha del movimiento |
| created_at / updated_at | timestamp | | NULL | Auditoría |

> `origen_id` es una **referencia polimórfica lógica** (apunta a ventas, compras, ajustes, etc.
> según `fuente`), **no** tiene FK física. Representarla como relación punteada/discontinua.

### 4.26 `ajustes_inventario`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| correlativo | varchar(20) | UK | NO | Número de ajuste |
| tipo | varchar(20) | | NO | Tipo de ajuste |
| motivo | text | | NULL | Motivo |
| observaciones | text | | NULL | Observaciones |
| user_id | bigint | FK→users.id | NO | Usuario |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.27 `detalle_ajustes_inventario`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| ajuste_id | bigint | FK→ajustes_inventario.id | NO | Ajuste (CASCADE) |
| producto_id | bigint | FK→productos.id | NO | Producto |
| cantidad | int | | NO | Cantidad ajustada |
| stock_antes | int | | NO | Stock antes |
| stock_despues | int | | NO | Stock después |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.28 `registros_auditoria`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| modulo | varchar(40) | | NO | Módulo afectado |
| accion | varchar(30) | | NO | Acción (`crear`, `actualizar`, `eliminar`) |
| auditable_type | varchar(255) | | NO | Clase del modelo auditado (polimórfico) |
| auditable_id | varchar(50) | | NO | Id del registro auditado |
| datos_antes | json | | NULL | Estado previo |
| datos_despues | json | | NULL | Estado posterior |
| usuario_id | bigint | FK→users.id | NO | Usuario |
| fecha_movimiento | timestamp | | NO | Fecha del evento |
| created_at / updated_at | timestamp | | NULL | Auditoría |

> (`auditable_type`, `auditable_id`) es una **relación polimórfica**; dibujarla como línea
> discontinua hacia "cualquier entidad", sin FK física.

### 4.29 `carritos`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| user_id | bigint | FK→users.id, UK | NO | Usuario dueño (1:1) (CASCADE) |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.30 `carrito_items`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| carrito_id | bigint | FK→carritos.id | NO | Carrito (CASCADE) |
| producto_id | bigint | FK→productos.id | NO | Producto |
| cantidad | int | | NO | Cantidad |
| precio_unitario | decimal(10,2) | | NO | Precio unitario |
| created_at / updated_at | timestamp | | NULL | Auditoría |

> Único (`carrito_id`, `producto_id`).

### 4.31 `contenido_web`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| clave | varchar(100) | UK | NO | Clave del contenido |
| valor | text | | NULL | Valor |
| tipo | varchar(10) | | NO | Tipo (`string`, `text`, ...) |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.32 `customer_user_links`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| user_id | bigint | FK→users.id, UK | NO | Usuario (1:1) (CASCADE) |
| cliente_id | bigint | FK→clientes.id | NO | Cliente (CASCADE) |
| estado | varchar(20) | | NO | Estado (`ACTIVO`, ...) |
| rol | varchar(30) | | NULL | Rol del vínculo |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.33 `one_time_tokens`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| user_id | bigint | FK→users.id | NO | Usuario (CASCADE) |
| purpose | varchar(30) | | NO | Propósito (verificación, recuperación) |
| token_hash | varchar(255) | UK | NO | Hash del token |
| expires_at | timestamp | | NO | Expiración |
| consumed_at | timestamp | | NULL | Consumo |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.34 `social_identities`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| user_id | bigint | FK→users.id | NO | Usuario (CASCADE) |
| provider | varchar(20) | | NO | Proveedor OIDC (`google`, ...) |
| provider_subject | varchar(255) | | NO | Identificador del usuario en el proveedor |
| provider_email | varchar(255) | | NULL | Email del proveedor |
| provider_email_verified | tinyint(1) | | NULL | Verificado |
| linked_at | timestamp | | NULL | Vinculación |
| last_login_at | timestamp | | NULL | Último login |
| created_at / updated_at | timestamp | | NULL | Auditoría |

> Único (`provider`, `provider_subject`).

### 4.35 `users`

| Columna | Tipo | Clave | Nulabilidad | Descripción |
|---|---|---|---|---|
| id | bigint | PK | NO | Identificador |
| name | varchar(255) | | NO | Nombre |
| email | varchar(255) | UK | NO | Correo |
| email_verified_at | timestamp | | NULL | Verificación de correo |
| password | varchar(255) | | NO | Contraseña (hash) |
| password_changed_at | timestamp | | NULL | Último cambio de contraseña |
| failed_attempts | int | | NO | Intentos fallidos |
| locked_until | timestamp | | NULL | Bloqueo temporal |
| last_login_at | timestamp | | NULL | Último login |
| remember_token | varchar(100) | | NULL | Token "recordarme" |
| activo | tinyint | | NO | Activo/inactivo |
| status | varchar(30) | | NO | Estado (`ACTIVE`, ...) |
| created_at / updated_at | timestamp | | NULL | Auditoría |

### 4.36 Entidades RBAC (Spatie) — cluster opcional

| Tabla | Columnas clave |
|---|---|
| `roles` | id PK, name, guard_name, (UK: name+guard_name) |
| `permissions` | id PK, name, guard_name, (UK: name+guard_name) |
| `model_has_roles` | role_id FK→roles.id, model_type, model_id (PK compuesta + CASCADE) |
| `model_has_permissions` | permission_id FK→permissions.id, model_type, model_id (PK compuesta + CASCADE) |
| `role_has_permissions` | permission_id FK→permissions.id, role_id FK→roles.id (PK compuesta + CASCADE) |

> `model_type` / `model_id` son polimórficos (apuntan a `users` u otros modelos).

---

## 5. Catálogo de relaciones (cardinalidad y borrado)

Formato: **origen (columna) — cardinalidad → destino (columna) — ON DELETE**.

### 5.1 Clientes y vehículos

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| vehiculos.cliente_id | N:1 | clientes.id | CASCADE |
| vehiculos | — | (catálogo independiente) | — |
| lavados.cliente_id | N:1 | clientes.id | CASCADE |
| lavados.vehiculo_id | N:1 | vehiculos.placa | SET NULL |
| lavados.vehiculo_id | N:1 | vehiculos.id | CASCADE |
| cambio_aceites.cliente_id | N:1 | clientes.id | CASCADE |
| cambio_aceites.vehiculo_id | N:1 | vehiculos.placa | SET NULL |
| customer_user_links.cliente_id | N:1 | clientes.id | CASCADE |

### 5.2 Personal

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| asistencias.trabajador_id | N:1 | trabajadores.id | CASCADE |
| lavado_trabajadores.trabajador_id | N:1 | trabajadores.id | CASCADE |
| cambio_aceite_trabajadores.trabajador_id | N:1 | trabajadores.id | CASCADE |
| ~~cambio_aceites.trabajador_id~~ | — | ELIMINADA (2026-10-10): usar pivote `cambio_aceite_trabajadores` | — |
| egresos_caja.trabajador_id | N:1 | trabajadores.id | (sin acción) |

### 5.3 Catálogos de producto

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| productos.categoria_id | N:1 | categorias.id | (sin acción) |
| productos.marca_id | N:1 | marcas.id | (sin acción) |

### 5.4 Lavado

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| detalle_servicios.lavado_id | N:1 | lavados.id | CASCADE |
| detalle_servicios.servicio_id | N:1 | servicios.id | CASCADE |
| lavado_trabajadores.lavado_id | N:1 | lavados.id | CASCADE |
| *N:M lavados ↔ servicios* | vía `detalle_servicios` | | |
| *N:M lavados ↔ trabajadores* | vía `lavado_trabajadores` | | |

### 5.5 Cambio de aceite

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| cambio_productos.cambio_aceite_id | N:1 | cambio_aceites.id | CASCADE |
| cambio_productos.producto_id | N:1 | productos.id | CASCADE |
| cambio_aceite_trabajadores.cambio_aceite_id | N:1 | cambio_aceites.id | CASCADE |
| *N:M cambio_aceites ↔ productos* | vía `cambio_productos` | | |
| *N:M cambio_aceites ↔ trabajadores* | vía `cambio_aceite_trabajadores` | | |

### 5.6 Ventas y devoluciones

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| detalle_ventas.venta_id | N:1 | ventas.id | CASCADE |
| detalle_ventas.producto_id | N:1 | productos.id | (sin acción) |
| devoluciones_venta.venta_id | N:1 | ventas.id | (sin acción) |
| devoluciones_venta.venta_nueva_id | N:1 | ventas.id | SET NULL |
| detalle_devoluciones_venta.devolucion_id | N:1 | devoluciones_venta.id | CASCADE |
| detalle_devoluciones_venta.detalle_venta_id | N:1 | detalle_ventas.id | (sin acción) |
| detalle_devoluciones_venta.producto_id | N:1 | productos.id | (sin acción) |

### 5.7 Caja

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| cajas.user_id | N:1 | users.id | (sin acción) |
| cajas.arqueo_user_id | N:1 | users.id | (sin acción) |
| egresos_caja.caja_id | N:1 | cajas.id | (sin acción) |
| lavados.caja_id | N:1 | cajas.id | SET NULL |
| cambio_aceites.caja_id | N:1 | cajas.id | SET NULL |
| ventas.caja_id | N:1 | cajas.id | SET NULL |
| devoluciones_venta.caja_id | N:1 | cajas.id | SET NULL |
| compras.caja_id | N:1 | cajas.id | (sin acción) |

### 5.8 Compras

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| compras.proveedor_id | N:1 | proveedores.id | (sin acción) |
| compras.egreso_caja_id | N:1 | egresos_caja.id | (sin acción) |
| detalle_compras.compra_id | N:1 | compras.id | CASCADE |
| detalle_compras.producto_id | N:1 | productos.id | (sin acción) |

### 5.9 Inventario

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| movimientos_kardex.producto_id | N:1 | productos.id | CASCADE |
| detalle_ajustes_inventario.ajuste_id | N:1 | ajustes_inventario.id | CASCADE |
| detalle_ajustes_inventario.producto_id | N:1 | productos.id | (sin acción) |
| movimientos_kardex.origen_id | polimórfica (lógica) | según `fuente` | — |

### 5.10 Web pública / carrito

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| carritos.user_id | 1:1 | users.id | CASCADE |
| carrito_items.carrito_id | N:1 | carritos.id | CASCADE |
| carrito_items.producto_id | N:1 | productos.id | (sin acción) |

### 5.11 Cuenta y seguridad

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| customer_user_links.user_id | 1:1 | users.id | CASCADE |
| one_time_tokens.user_id | N:1 | users.id | CASCADE |
| social_identities.user_id | N:1 | users.id | CASCADE |

### 5.12 Catálogos administrativos que referencian usuarios

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| lavados.user_id | N:1 | users.id | (sin acción) |
| cambio_aceites.user_id | N:1 | users.id | (sin acción) |
| ventas.user_id | N:1 | users.id | (sin acción) |
| devoluciones_venta.user_id | N:1 | users.id | (sin acción) |
| egresos_caja.user_id | N:1 | users.id | (sin acción) |
| compras.user_id | N:1 | users.id | (sin acción) |
| ajustes_inventario.user_id | N:1 | users.id | (sin acción) |
| movimientos_kardex.usuario_id | N:1 | users.id | (sin acción) |
| registros_auditoria.usuario_id | N:1 | users.id | (sin acción) |

### 5.13 Auditoría (polimórfica)

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| registros_auditoria.(auditable_type, auditable_id) | polimórfica (lógica) | cualquier entidad auditada | — |

### 5.14 RBAC

| Origen | Cardinalidad | Destino | Borrado |
|---|---|---|---|
| model_has_roles.role_id | N:1 | roles.id | CASCADE |
| model_has_permissions.permission_id | N:1 | permissions.id | CASCADE |
| role_has_permissions.role_id | N:1 | roles.id | CASCADE |
| role_has_permissions.permission_id | N:1 | permissions.id | CASCADE |

---

## 6. Reglas de modelado y ambigüedades a resolver

1. **Tablas pivote explícitas.** `detalle_servicios`, `lavado_trabajadores`, `cambio_productos`,
   `cambio_aceite_trabajadores`, `detalle_ventas`, `detalle_compras`,
   `detalle_ajustes_inventario`, `detalle_devoluciones_venta`, `carrito_items` son entidades con
   PK propia. Deben dibujarse como **entidades** y no colapsarse en una relación N:M directa.
2. **Doble FK a `users`.** `cajas` referencia `users` dos veces (`user_id` y `arqueo_user_id`);
   dibujar **dos líneas independientes** con etiquetas distintas.
3. **Doble FK a `ventas`.** `devoluciones_venta` referencia `ventas` dos veces (`venta_id` y
   `venta_nueva_id`); una es la venta original y la otra la venta de cambio.
4. **PK no numérica.** `vehiculos` usa `placa` (varchar) como PK; quienes la referencian
   (`lavados`, `cambio_aceites`) guardan `vehiculo_id` como `varchar(7)` que apunta a
   `vehiculos.placa`.
5. **Referencias lógicas (sin FK física) que se dibujan discontinuas:**
   - `movimientos_kardex.origen_id` (según `fuente`).
   - `registros_auditoria.(auditable_type, auditable_id)`.
   - `model_has_*` / `role_has_permissions` (`model_type`, `model_id`) si se incluye RBAC.
6. **Entidades sin relaciones entrantes/salientes de negocio:** `vehiculos` (solo la usa `lavados`),
   `contenido_web` (aislada), `proveedores` (solo en `compras`). Dibujarlas igualmente.
7. **Soft-deletes / flags:** `activo`, `estado`, `status` son atributos de estado, no claves.
8. **No inventar columnas.** El diccionario de la sección 4 es la fuente de verdad. No agregar
   columnas que no existan en `schema.sql`.
9. **Timestamps.** `created_at` / `updated_at` están en casi todas las tablas; mostrarlos de forma
   compacta para no saturar.

---

## 7. Entregables esperados de la IA

1. **Código Mermaid `erDiagram` completo y válido** (principal), que incluya:
   - Las 35 entidades de la sección 2.1 (o 30 sin RBAC, según se pida).
   - Atributos con `PK`, `FK`, `UK`.
   - Relaciones con cardinalidad de pata de gallo y etiqueta en español.
   - Agrupación por comentarios `%% Módulo: ...` o subgrafos equivalentes.
2. Opcional: el mismo diagrama en **DBML** y/o **PlantUML**.
3. Una lista final de **relaciones N:M** y de las **referencias lógicas (discontinuas)**.
4. Un **checklist de verificación** confirmando que:
   - No se incluyeron las tablas de infraestructura de la sección 2.2.
   - Todas las FK de la sección 5 están representadas.
   - La cardinalidad coincide con la regla de borrado indicada.
   - Los nombres están en español, tal como en la base de datos.

---

## 8. Prompt sugerido (copiar y pegar junto a este documento)

> Genera el Diagrama Entidad-Relación del sistema descrito en este documento. Usa **Mermaid
> `erDiagram`**. Incluye únicamente las tablas del dominio listadas en la sección 2.1 (omite las de
> infraestructura de la sección 2.2). Representa cada tabla como una entidad con su PK, FK y UK.
> Traza todas las relaciones de la sección 5 con cardinalidad de pata de gallo: las FK físicas con
> línea continua, y las referencias polimórficas de `movimientos_kardex.origen_id` y
> `registros_auditoria` como líneas discontinuas. Agrupa visualmente por módulos con comentarios
> `%%`. No inventes columnas ni relaciones fuera de las especificadas. Entrega además la lista de
> relaciones N:M (vía tablas pivote) y un checklist de verificación.
