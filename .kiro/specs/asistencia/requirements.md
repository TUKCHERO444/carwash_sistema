# Documento de Requisitos — Panel de Asistencia

## Introducción

Este feature añade un **panel de asistencia** para la entidad **Trabajador** dentro de la sección de Gestión de usuarios del sistema. El panel permite consultar, mediante un **calendario mensual**, qué trabajadores asistieron en cada fecha y a qué hora marcaron su entrada. Al hacer clic en un día se abre un **modal de resumen** que muestra, con identificadores de color, el conteo de asistentes y no asistentes junto con la lista de nombres y horas de entrada.

La marcación de asistencia es **manual**: el administrador registra, desde el propio panel, quiénes asistieron cada día y la hora de entrada de cada uno. No se detectan tardanzas en esta versión (no existe concepto de horario de trabajo en el sistema). El universo de personas consideradas para el conteo "no asistió" son los **trabajadores con `estado = true`** (activos) sin marca registrada esa fecha.

El diseño visual respeta las guías `.kiro/guia-modo-oscuro.md` y `.kiro/reajuste-listados.md`, y los patrones de código siguen los módulos existentes (modal global `<x-modal>` + `resources/js/modal.js`, JS modularizado por vista con delegación de eventos y `fetch`, rutas AJAX antes que resources).

## Glosario

- **Asistencia**: Registro de una marca de entrada de un Trabajador en una fecha concreta. Se almacena en la tabla `asistencias` con los campos `trabajador_id`, `fecha`, `hora_entrada`.
- **Asistente**: Trabajador que tiene un registro de Asistencia para la fecha consultada.
- **No asistente**: Trabajador **activo** (`estado = true`) sin registro de Asistencia para la fecha consultada. El sistema no almacena ausencias: se calculan por diferencia.
- **Calendario**: Cuadrícula mensual renderizada en JavaScript (vanilla, sin librerías) con navegación entre meses.
- **Modal de resumen**: Modal global creado con el componente `<x-modal>` que muestra el detalle de una fecha: conteos, asistentes con hora de entrada y no asistentes.
- **Gestión / Modo de marcación**: Estado del modal en el que el administrador marca/desmarca trabajadores activos y asigna hora de entrada para una fecha no futura.
- **Sincronización (full-sync)**: Estrategia de guardado de las marcas de una fecha: la colección enviada reemplaza por completo las marcas existentes del día (inserta, actualiza y elimina lo omitido).
- **Trabajador activo**: Trabajador con `estado = true`. Es el único universo considerado para los conteos.
- **acceso-asistencia**: Permiso RBAC nuevo (Spatie) que protege todas las rutas del módulo. Lo recibe el rol **Administrador** vía `syncPermissions(Permission::all())`.

---

## Requisitos

### Requisito 1: Acceso al panel de asistencia desde el menú

**User Story:** Como Administrador, quiero acceder al panel de asistencia desde la navegación lateral, para consultar y registrar la asistencia del personal sin buscar la URL.

#### Criterios de Aceptación

1. WHEN el usuario autenticado con permiso `acceso-asistencia` abre el layout, THE Sistema SHALL mostrar una entrada "Asistencia" dentro del dropdown "Gestión de usuarios" (junto a "Trabajadores") en el sidebar de escritorio y en la navegación inferior móvil.
2. THE entrada SHALL estar protegida con `@can('acceso-asistencia')`.
3. WHEN la ruta activa pertenece al grupo `asistencia.*`, THE entrada SHALL resaltarse con el mismo estilo activo que las demás entradas del menú.
4. WHEN una entrada del dropdown "Gestión de usuarios" está activa (incluyendo `asistencia.*`), THE dropdown SHALL permanecer abierto (`data-persistent`).

**Valida:** Acceso a la feature.

---

### Requisito 2: Calendario mensual consultable

**User Story:** Como Administrador, quiero ver un calendario del mes con la información de asistencia, para consultar rápidamente el estado de cada día.

#### Criterios de Aceptación

1. WHEN el Administrador accede a `GET /asistencia`, THE AsistenciaController SHALL devolver la vista `asistencia.index` con el mes actual como mes visible.
2. THE Calendario SHALL ser una cuadrícula de semanas con encabezados de día de la semana (L M X J V S D) construida en JavaScript vanilla sin librerías externas.
3. THE Calendario SHALL mostrar una barra de navegación con el nombre del mes y el año en la vista, y botones para ir al mes anterior y siguiente.
4. THE Calendario SHALL deshabilitar visualmente los días del mes futuro (no consultables ni gestionables).
5. THE vista SHALL mostrar un botón "Registrar asistencia de hoy" que abre el modal de detalle del día actual en modo de gestión.
6. WHEN no existen registros de Asistencia en el mes visible, THE Calendario SHALL mostrarse sin indicadores de color en ningún día.

**Valida:** Propiedades de navegación y datos.

---

### Requisito 3: Indicadores de color por día del calendario

**User Story:** Como Administrador, quiero ver de un vistazo cómo fue la asistencia de cada día del mes, para detectar días con inasistencias sin abrir cada detalle.

#### Criterios de Aceptación

1. THE Calendario SHALL consultar `GET /asistencia/por-mes?mes=YYYY-MM` al renderizar y al cambiar de mes, obteniendo por cada día del mes el conteo de asistentes y el total de trabajadores activos.
2. THE Sistema SHALL aplicar el color **verde** al día cuando todos los trabajadores activos asistieron.
3. THE Sistema SHALL aplicar el color **ámbar** al día cuando asistió al menos uno pero no todos los trabajadores activos.
4. THE Sistema SHALL aplicar el color **rojo** al día cuando ningún trabajador activo asistió y existe al menos un trabajador activo.
5. WHEN el día pertenece a un mes futuro, THE Sistema SHALL no aplicar indicador de color alguno.
6. THE Sistema SHALL mostrar una leyenda al pie del calendario explicando los indicadores de color (verde, ámbar, rojo y sin datos).

**Valida:** Requisito 3 de los criterios visuales.

---

### Requisito 4: Detalle de un día en modal

**User Story:** Como Administrador, quiero hacer clic en una fecha del calendario para abrir un modal con el detalle de asistencia de ese día, para ver quiénes asistieron y a qué hora.

#### Criterios de Aceptación

1. WHEN el Administrador hace clic en un día no futuro del calendario, THE Sistema SHALL llamar a `GET /asistencia/por-fecha?fecha=YYYY-MM-DD`.
2. IF la consulta tiene éxito, THEN THE Sistema SHALL abrir el modal `<x-modal id="modal-asistencia">` con `maxWidth="2xl"`.
3. THE título del modal SHALL mostrar la fecha en formato peruano descriptivo (p. ej. "Sábado, 12 de septiembre de 2026") con `Carbon::createFromFormat(...)` y locale correspondiente.
4. WHEN un trabajador con `estado = false` es omitido del conteo y de las listas.
5. THE modal SHALL mostrar, en todo momento, el resumen con conteos y las listas descritas en los requisitos 5 y 6.
6. IF el día consultado es no futuro (hoy o pasado), THEN el modal SHALL incluir el modo de gestión descrito en el requisito 7.
7. WHEN la consulta falla (error de red o respuesta no satisfactoria), THEN THE Sistema SHALL cerrar/mantener el modal cerrado y mostrar un mensaje de error, sin dejar el modal a medias.

**Valida:** Requisito 4 de consulta y modal.

---

### Requisito 5: Resumen con conteos de asistencia y no asistencia

**User Story:** Como Administrador, quiero ver en el modal un resumen con cuántos trabajadores asistieron y cuántos no, con identificadores de color, para entender el día de un vistazo.

#### Criterios de Aceptación

1. THE modal SHALL mostrar una sección de resumen con dos tarjetas.
2. THE tarjeta "Asistieron" SHALL mostrar el número de trabajadores activos con marca y un indicador de color **verde**.
3. THE tarjeta "No asistieron" SHALL mostrar el número de trabajadores activos sin marca y un indicador de color **rojo**.
4. THE Sistema SHALL garantizar que la suma de ambos conteos es igual al total de trabajadores activos.

**Valida:** Requisito 5 de resumen; **Propiedad 1** (conteos particionan el universo activo).

---

### Requisito 6: Listado de asistentes y no asistentes

**User Story:** Como Administrador, quiero ver debajo del resumen los nombres de los trabajadores que asistieron con su hora de entrada y los que no asistieron, para identificar personas concretas.

#### Criterios de Aceptación

1. Debajo del resumen, THE modal SHALL mostrar la lista de **asistentes**: fila por trabajador con indicador de color **verde**, foto (si existe), nombre completo (`nombre_completo`) y hora de entrada en formato `H:i`.
2. THE lista de asistentes SHALL ordenarse por `hora_entrada` ascendente y, en caso de empate, por nombre completo.
3. THE modal SHALL mostrar a continuación la lista de **no asistentes**: fila por trabajador con indicador de color **rojo**, foto (si existe) y nombre completo, sin hora.
4. THE lista de no asistentes SHALL ordenarse por nombre completo.
5. WHEN no hay asistentes, THE Sistema SHALL mostrar un mensaje "Ningún trabajador asistió" en lugar de la lista.
6. WHEN todos los activos asistieron, THE Sistema SHALL mostrar en la lista de no asistentes el mensaje "Todos los trabajadores asistieron" y la sección vacía.
7. THE indicador de color SHALL ser un elemento visual pequeño (círculo) alineado al inicio de cada fila.

**Valida:** Requisito 6 de listado.

---

### Requisito 7: Registro manual de asistencia (marcación)

**User Story:** Como Administrador, quiero registrar manualmente la asistencia del día (o corregir una fecha pasada) marcando quiénes asistieron y la hora de entrada de cada uno, para alimentar el panel.

#### Criterios de Aceptación

1. WHEN el modal está en modo de gestión (fecha no futura), THE Sistema SHALL mostrar un bloque "Registrar asistencia" con una fila por trabajador **activo**: checkbox + nombre completo + input de hora (`type="time"`).
2. THE input de hora SHALL estar prellenado con la hora actual (`now()->format('H:i')`) para trabajadores sin marca previa, y con la hora ya registrada para los que sí tienen marca.
3. WHEN el Administrador guarda, THE Sistema SHALL enviar `POST /asistencia/marcar` con la fecha y el mapa `marcas = { trabajador_id: "HH:MM" }` de los trabajadores marcados.
4. THE guardado SHALL ser una **sincronización total** (full-sync): inserta las marcas nuevas, actualiza las marcas existentes que cambian de hora y elimina las marcas de trabajadores activos que quedan desmarcados.
5. THE guardado SHALL ser **idempotente**: reenviar exactamente el mismo payload a una fecha sin cambios intermedios no altera el estado de la base de datos.
6. THE Sistema SHALL ignorar en el guardado cualquier `trabajador_id` que apunte a un trabajador inactivo (`estado = false`).
7. IF la validación falla (fecha inválida o futura, formato de hora incorrecto), THEN THE Sistema SHALL responder HTTP 422 y NO modificar ninguna marca.
8. WHEN el guardado tiene éxito, THE Sistema SHALL refrescar el contenido del modal con el nuevo resumen y el calendario con los nuevos indicadores, sin recargar la página.
9. THE Sistema SHALL mostrar feedback visual (breve mensaje de éxito) tras guardar.

**Valida:** Requisito 7 de marcación; **Propiedades 2 y 3** (full-sync e idempotencia).

---

### Requisito 8: Control de acceso, autenticación y rutas

**User Story:** Como Administrador, quiero que el panel de asistencia esté protegido por autenticación y por un permiso específico, para que solo el personal autorizado lo consulte y gestione.

#### Criterios de Aceptación

1. THE Sistema SHALL registrar el permiso `acceso-asistencia` en `database/seeders/PermissionSeeder.php`, dentro del bloque `// Personal`.
2. THE rol Administrador SHALL recibir el permiso automáticamente (el `AuthSeeder` sincroniza todos los permisos).
3. THE rol Vendedor SHALL **no** recibir el permiso.
4. Todas las rutas del módulo SHALL estar dentro del grupo `middleware(['auth', 'permission:acceso-asistencia'])` en `routes/web.php`.
5. WHEN un usuario no autenticado intenta acceder a cualquier ruta del módulo, THE Sistema SHALL redirigirlo a `/login`.
6. WHEN un usuario autenticado sin el permiso `acceso-asistencia` intenta acceder a cualquier ruta del módulo, THE Sistema SHALL responder HTTP 403.
7. THE rutas AJAX del módulo (`por-fecha`, `por-mes`, `marcar`) SHALL declararse ANTES de cualquier ruta con parámetro dinámico para evitar conflictos de Route Model Binding (convención explícita del proyecto).

**Valida:** Requisito 8 de control de acceso; **Propiedades 4 y 5**.

---

### Requisito 9: Auditoría de las marcas de asistencia

**User Story:** Como Administrador, quiero que las altas, modificaciones y bajas de marcas de asistencia queden registradas en la auditoría, para poder revisar quién y cuándo modificó la asistencia.

#### Criterios de Aceptación

1. THE modelo `Asistencia` SHALL registrarse como auditable en `app/Providers/AuditServiceProvider.php`.
2. THE modelo `Asistencia` SHALL añadirse a la constante `AUDITABLE` y al mapa `$modulos` (como `'asistencias'`) de `app/Services/AuditService.php`.
3. WHEN una Asistencia se crea, actualiza o elimina, THE AuditModelObserver SHALL escribir un registro en `registros_auditoria` con el identificador de la Asistencia.
4. THE guardado full-sync de `marcar` SHALL producir una auditoría coherente: creaciones y actualizaciones de marcas por trabajador, y eliminación de las marcas desmarcadas.

**Valida:** Requisito 9 de auditoría.

---

## Convenciones que la implementación debe respetar

- Todo el código (controlador, modelo, vistas, JS, mensajes, commit messages) en español.
- Sin librerías JS nuevas: el calendario se construye en vanilla JS, alineado con la convención "sin dependencias" de `.kiro/specs/stock-update-modal/design.md`.
- Sin Form Requests: la validación es inline en el controlador con `$request->validate([...])`, como el resto del proyecto.
- La guía de listados (`reajuste-listados.md`) aplica al modal: `overflow-y-auto` en zonas largas y padding `py-6`/`py-8` en filas. No aplica paginación al calendario.
- El panel **no** tiene acoplamiento con Caja: registrar asistencia no es una transacción de dinero y no aplica el modal `error_caja`.
- `resources/js/asistencia/index.js` debe añadirse al `input` de `vite.config.js` y cargarse con `@vite(...)` al final de `@section('content')`.