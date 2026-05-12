# Plan de Implementación: Sistema de Caja

## Visión General

Implementar el módulo de caja sobre la aplicación Laravel existente. El módulo gestiona sesiones de caja diarias (apertura → operación → cierre), consolida automáticamente los ingresos de Ventas, Cambio de Aceite e Ingresos Vehiculares, y bloquea el registro de cobros cuando no hay caja activa.

El stack es PHP 8.2 + Laravel 12, Blade + Tailwind CSS, PHPUnit/Pest para pruebas, y SQLite en desarrollo.

---

## Tareas

- [x] 1. Crear migraciones y modelos base del sistema de caja
  - Crear migración para la tabla `cajas` con columnas: `id`, `user_id` (FK), `estado` (enum abierta/cerrada), `monto_inicial` (decimal 10,2), `fecha_apertura` (datetime), `fecha_cierre` (datetime nullable), timestamps
  - Crear migración para la tabla `egresos_caja` con columnas: `id`, `caja_id` (FK), `monto` (decimal 10,2), `descripcion` (varchar 500), `tipo_pago` (enum efectivo/yape), `user_id` (FK), timestamps
  - Crear migración para añadir columna `caja_id` (bigint unsigned nullable, FK a `cajas`) a las tablas `ventas`, `cambio_aceites` e `ingresos`
  - Crear el modelo `Caja` con `$fillable`, `$casts`, relaciones (`user`, `egresos`, `ventas`, `cambioAceites`, `ingresos`) y scopes `scopeAbierta` / `scopeCerrada`
  - Crear el modelo `EgresoCaja` con `$table = 'egresos_caja'`, `$fillable`, `$casts` y relaciones (`caja`, `user`)
  - Añadir la relación `caja()` (BelongsTo) a los modelos `Venta`, `CambioAceite` e `Ingreso`
  - _Requisitos: 2.4, 8.1, 8.3_

- [x] 2. Implementar CajaService con lógica de negocio central
  - Crear `app/Services/CajaService.php` con los métodos: `getCajaActiva()`, `abrirCaja(float $montoInicial, int $userId)`, `cerrarCaja(Caja $caja)`, `registrarEgreso(Caja $caja, array $data)`, `asociarTransaccion(string $tipo, int $transaccionId, Caja $caja)` y `calcularResumen(Caja $caja)`
  - `abrirCaja` debe usar `DB::transaction` con `lockForUpdate()` para garantizar unicidad de caja abierta
  - `calcularResumen` debe retornar array con `total_ingresos`, `total_egresos` y `balance_final` calculados según la fórmula del diseño
  - Registrar `CajaService` en el contenedor de servicios (o usar inyección automática de Laravel)
  - _Requisitos: 2.4, 2.6, 3.2, 3.3, 3.4, 7.4, 8.1, 8.2, 8.3_

  - [ ]* 2.1 Escribir prueba de propiedad: Validación del monto inicial
    - **Propiedad 1: Para cualquier valor de `monto_inicial` ≤ 0, la apertura debe ser rechazada y no debe crearse ningún registro en `cajas`; si el valor es > 0, la apertura debe ser aceptada**
    - Ejecutar mínimo 100 iteraciones con valores aleatorios positivos y negativos/cero
    - **Valida: Requisito 2.3**

  - [ ]* 2.2 Escribir prueba de propiedad: Unicidad de caja abierta
    - **Propiedad 2: Para cualquier secuencia de intentos de apertura, el número de registros con `estado = 'abierta'` nunca debe superar 1**
    - Ejecutar mínimo 100 iteraciones intentando abrir múltiples cajas
    - **Valida: Requisito 2.6**

  - [ ]* 2.3 Escribir prueba de propiedad: Cálculo correcto del total de ingresos
    - **Propiedad 3: Para cualquier conjunto de transacciones asociadas a una caja, `total_ingresos` debe ser igual a la suma aritmética de los campos `total` de todas esas transacciones**
    - Ejecutar mínimo 100 iteraciones con combinaciones aleatorias de ventas, cambios de aceite e ingresos confirmados
    - **Valida: Requisitos 3.2, 8.2**

  - [ ]* 2.4 Escribir prueba de propiedad: Cálculo correcto del total de egresos
    - **Propiedad 4: Para cualquier conjunto de egresos manuales, `total_egresos` debe ser igual a la suma aritmética de los campos `monto` de todos esos egresos**
    - Ejecutar mínimo 100 iteraciones con cantidades aleatorias de egresos
    - **Valida: Requisito 3.3**

  - [ ]* 2.5 Escribir prueba de propiedad: Fórmula del balance neto
    - **Propiedad 5: Para cualquier caja, `balance_final` debe ser igual a `monto_inicial + total_ingresos − total_egresos`**
    - Ejecutar mínimo 100 iteraciones con combinaciones aleatorias de monto inicial, ingresos y egresos
    - **Valida: Requisito 3.4**

  - [ ]* 2.6 Escribir prueba de propiedad: Distribución correcta de montos por modo de pago
    - **Propiedad 6: Para transacciones con `metodo_pago` en {efectivo, yape, izipay}, el monto atribuido debe ser igual al `total`; para `metodo_pago = 'mixto'`, la suma de parciales debe ser igual al `total`**
    - Ejecutar mínimo 100 iteraciones con los cuatro modos de pago
    - **Valida: Requisitos 4.3, 4.4, 4.5**

  - [ ]* 2.7 Escribir prueba de propiedad: Validación de egresos manuales
    - **Propiedad 7: Para cualquier egreso con `monto` ≤ 0, `descripcion` vacía/solo espacios, o `tipo_pago` inválido, el egreso debe ser rechazado y `total_egresos` no debe cambiar**
    - Ejecutar mínimo 100 iteraciones con datos inválidos aleatorios
    - **Valida: Requisitos 5.3, 5.4, 5.5**

  - [ ]* 2.8 Escribir prueba de propiedad: Actualización del total de egresos al registrar un egreso
    - **Propiedad 8: Para cualquier egreso válido registrado, `total_egresos` debe incrementarse en exactamente el `monto` del egreso**
    - Ejecutar mínimo 100 iteraciones con montos aleatorios válidos
    - **Valida: Requisito 5.6**

  - [ ]* 2.9 Escribir prueba de propiedad: Estado correcto tras el cierre de caja
    - **Propiedad 11: Después de ejecutar el cierre, el registro debe tener `estado = 'cerrada'` y `fecha_cierre` no nulo**
    - Ejecutar mínimo 100 iteraciones cerrando cajas abiertas
    - **Valida: Requisito 7.4**

- [x] 3. Checkpoint — Verificar migraciones y servicio
  - Ejecutar `php artisan migrate` y confirmar que las tablas se crean correctamente
  - Asegurarse de que todos los tests del servicio pasen; consultar al usuario si surgen dudas

- [x] 4. Implementar CajaController y rutas
  - Crear `app/Http/Controllers/CajaController.php` con los métodos: `index()`, `abrir()`, `cerrar()`, `registrarEgreso()`, `historial()` y `detalle()`
  - `index()` debe obtener la caja activa via `CajaService::getCajaActiva()` y pasar el resumen calculado a la vista
  - `abrir()` debe validar `monto_inicial` (numeric, gt:0) y llamar a `CajaService::abrirCaja()`
  - `cerrar()` debe verificar que existe caja activa y llamar a `CajaService::cerrarCaja()`
  - `registrarEgreso()` debe validar `monto` (numeric, gt:0), `descripcion` (required, string), `tipo_pago` (in:efectivo,yape) y llamar a `CajaService::registrarEgreso()`
  - `historial()` y `detalle()` son solo para rol Administrador
  - Añadir las rutas en `routes/web.php` dentro del grupo `middleware('auth')` con prefijo `caja` y nombre `caja.*`, incluyendo el subgrupo `middleware('role:Administrador')` para historial y detalle
  - _Requisitos: 1.2, 2.1, 2.2, 2.3, 2.4, 2.5, 5.1, 5.2, 5.3, 5.4, 5.5, 7.1, 7.2, 9.2, 9.4_

  - [ ]* 4.1 Escribir pruebas de integración HTTP para CajaController
    - `POST /caja/abrir` con monto válido → 302 redirect, caja creada en DB
    - `POST /caja/abrir` con caja ya abierta → error, sin nuevo registro
    - `POST /caja/abrir` con monto ≤ 0 → error de validación
    - `POST /caja/egresos` con datos válidos → egreso guardado
    - `POST /caja/cerrar` con caja activa → caja cerrada en DB
    - `GET /caja/historial` como Administrador → 200
    - `GET /caja/historial` como usuario sin rol → 403
    - _Requisitos: 2.1, 2.2, 2.3, 5.1, 7.1, 9.4_

- [x] 5. Integrar restricción de caja activa en los módulos de cobro
  - Modificar `VentaController::store()` para verificar caja activa al inicio del método; si no existe, retornar `back()->with('error_caja', true)`
  - Modificar `CambioAceiteController::store()` con la misma verificación
  - Modificar `IngresoController::procesarConfirmacion()` con la misma verificación
  - En los tres métodos, cuando la caja existe, asignar `caja_id` al crear/actualizar el registro dentro de la transacción DB existente
  - Para `IngresoController::procesarConfirmacion()`, asignar `caja_id` en el `$ingreso->update()` al cambiar estado a `confirmado`
  - _Requisitos: 6.1, 6.3, 8.1, 8.3_

  - [ ]* 5.1 Escribir prueba de propiedad: Bloqueo de transacciones sin caja activa
    - **Propiedad 9: Para cualquier intento de guardar en Ventas, Cambio de Aceite o Ingresos Vehiculares sin caja activa, el sistema debe rechazar la operación y el conteo de registros no debe aumentar**
    - Ejecutar mínimo 100 iteraciones intentando guardar registros sin caja activa
    - **Valida: Requisitos 6.1, 6.3**

  - [ ]* 5.2 Escribir prueba de propiedad: Asociación automática de transacciones a la caja activa
    - **Propiedad 10: Para cualquier transacción guardada exitosamente con caja activa, el campo `caja_id` debe ser igual al `id` de la caja activa**
    - Ejecutar mínimo 100 iteraciones guardando registros con caja activa
    - **Valida: Requisitos 8.1, 8.3**

  - [ ]* 5.3 Escribir prueba de propiedad: Bloqueo de operaciones en caja cerrada
    - **Propiedad 12: Para cualquier intento de registrar un egreso o asociar una transacción a una caja con `estado = 'cerrada'`, el sistema debe rechazar la operación**
    - Ejecutar mínimo 100 iteraciones intentando operar sobre cajas cerradas
    - **Valida: Requisito 7.6**

- [x] 6. Checkpoint — Verificar integración con módulos de cobro
  - Asegurarse de que todos los tests de integración pasen; consultar al usuario si surgen dudas

- [ ] 7. Crear vistas Blade del panel de caja
  - Crear `resources/views/caja/panel.blade.php` con:
    - Tres tarjetas de resumen: "Monto Inicial", "Total Ingresos", "Total Egresos" y balance neto
    - Botón "Iniciar Caja" (habilitado solo si no hay caja activa)
    - Botón "Cerrar Caja" (habilitado solo si hay caja activa)
    - Botón "Registrar Egreso" (visible solo si hay caja activa)
    - Modal de apertura de caja (solicita monto inicial)
    - Modal de registro de egreso (solicita monto, descripción y tipo de pago)
    - Modal de confirmación de cierre con resumen completo
    - Listado de ingresos agrupado por fuente (Venta, Cambio de Aceite, Ingreso Vehicular) con subtotales por modo de pago
    - Listado de egresos con monto, descripción y tipo de pago
  - Los modales se controlan con JavaScript vanilla (consistente con el resto del proyecto)
  - Mostrar el modal de "caja requerida" cuando la sesión flash contenga `error_caja = true` (para las vistas de Ventas, Cambio de Aceite e Ingresos)
  - _Requisitos: 2.1, 2.2, 3.1, 3.4, 4.1, 4.2, 4.5, 5.1, 5.2, 7.1, 7.2, 7.3_

  - [ ]* 7.1 Escribir pruebas unitarias para las vistas del panel
    - Panel muestra botón "Iniciar Caja" habilitado cuando no hay caja activa
    - Panel muestra botón "Cerrar Caja" habilitado cuando hay caja activa
    - Panel muestra las tres tarjetas de resumen con valores correctos
    - Modal de apertura se renderiza correctamente
    - Modal de egreso se renderiza correctamente
    - _Requisitos: 2.1, 2.5, 3.1, 5.1_

- [ ] 8. Crear vistas Blade de historial y detalle de caja
  - Crear `resources/views/caja/historial.blade.php` con listado paginado de cajas cerradas ordenadas por `fecha_cierre` descendente, mostrando: fecha apertura, fecha cierre, monto inicial, total ingresos, total egresos y balance final
  - Crear `resources/views/caja/detalle.blade.php` con el detalle completo de una caja cerrada: todos los campos del resumen más el listado de ingresos por fuente/modo de pago y el listado de egresos con descripción
  - _Requisitos: 9.2, 9.3, 9.4, 7.3_

  - [ ]* 8.1 Escribir prueba de propiedad: Persistencia y ordenamiento del historial
    - **Propiedad 13: Para cualquier conjunto de cajas cerradas con distintas `fecha_cierre`, la lista del historial debe contener todas esas cajas ordenadas de forma descendente por `fecha_cierre`**
    - Ejecutar mínimo 100 iteraciones con conjuntos aleatorios de cajas cerradas
    - **Valida: Requisitos 9.1, 9.2**

  - [ ]* 8.2 Escribir prueba de propiedad: Completitud del resumen de caja cerrada
    - **Propiedad 14: Para cualquier caja cerrada, el detalle debe incluir `fecha_apertura`, `fecha_cierre`, `monto_inicial`, `total_ingresos`, `total_egresos` y `balance_final` con valores correctos**
    - Ejecutar mínimo 100 iteraciones con cajas cerradas de distintas configuraciones
    - **Valida: Requisitos 7.3, 9.3**

- [ ] 9. Añadir enlace "Caja" al sidebar y modal de caja requerida en módulos de cobro
  - Modificar `resources/views/layouts/app.blade.php` para añadir la variable `$cajaActive` y el enlace directo "Caja" entre el Dashboard y "Gestión de Ventas", visible para todos los usuarios autenticados, con el ícono de caja registradora y resaltado activo cuando `request()->routeIs('caja.*')`
  - Añadir el enlace "Caja" también en la barra de navegación móvil (bottom nav)
  - Añadir el modal de "caja requerida" en las vistas `ventas/create.blade.php`, `cambio-aceite/create.blade.php` e `ingresos/confirmar.blade.php`; el modal debe mostrarse cuando la sesión flash contenga `error_caja = true` y ofrecer un botón que redirija a `route('caja.index')`
  - _Requisitos: 1.1, 1.2, 1.3, 6.1, 6.2_

  - [ ]* 9.1 Escribir pruebas unitarias para el sidebar y el modal de caja requerida
    - Enlace "Caja" presente en el sidebar para usuarios autenticados
    - Enlace "Caja" resaltado como activo cuando se está en rutas `caja.*`
    - Modal de "caja requerida" se muestra cuando la sesión flash contiene `error_caja = true`
    - Modal ofrece botón de redirección al panel de caja
    - _Requisitos: 1.1, 1.2, 1.3, 6.2_

- [ ] 10. Crear factories para pruebas
  - Crear `database/factories/CajaFactory.php` con estados `abierta()` y `cerrada()`
  - Crear `database/factories/EgresoCajaFactory.php`
  - Actualizar `database/factories/IngresoFactory.php` para incluir el campo `caja_id` (nullable)
  - Añadir soporte de `caja_id` en las factories de `Venta` y `CambioAceite` si no existen, o crear `VentaFactory.php` y `CambioAceiteFactory.php`
  - _Requisitos: (soporte para todas las pruebas de propiedades)_

- [ ] 11. Checkpoint final — Asegurarse de que todos los tests pasen
  - Ejecutar `php artisan test` y verificar que todos los tests (unitarios, de propiedades e integración) pasen sin errores
  - Consultar al usuario si surgen dudas o ajustes necesarios

---

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia los requisitos específicos para trazabilidad
- Los checkpoints garantizan validación incremental antes de continuar
- Las pruebas de propiedades ejecutan mínimo 100 iteraciones con datos generados aleatoriamente usando `fake()` de Faker dentro de bucles
- Cada prueba de propiedad incluye el comentario: `// Feature: sistema-caja, Property {N}: {texto}`
- La columna `caja_id` es nullable en las tres tablas de cobro para preservar registros históricos existentes
- El lock pesimista en `abrirCaja()` previene race conditions en aperturas simultáneas
