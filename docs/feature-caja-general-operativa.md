# Caja general y caja chica operativa

## Objetivo

Separar el resumen administrativo de la operación diaria de caja sin crear una
segunda caja ni duplicar saldos. “Caja general” y “caja chica” son dos vistas y
conjuntos de permisos sobre la misma sesión de caja activa.

## Permisos

- `acceso-caja`: acceso base al módulo. Lo conservan todos los roles operativos.
- `gestionar-caja`: apertura y cierre. Solo Administrador por defecto.
- `ver-resumen-caja`: monto inicial, balance, métodos de pago, orígenes y
  detalle de movimientos. Solo Administrador por defecto.
- `registrar-egresos-caja`: registro de egresos manuales. Administrador y
  Vendedor por defecto.
- `historial-caja`: historial y detalle de cajas cerradas. Solo Administrador
  por defecto.

## Rutas

- `GET /caja`: panel general con `ver-resumen-caja`; panel operativo para el
  resto de usuarios con `acceso-caja`.
- `POST /caja/abrir` y `POST /caja/cerrar`: requieren `gestionar-caja`.
- `POST /caja/egresos`: requiere `registrar-egresos-caja`.
- `GET /caja/historial` y `GET /caja/{caja}`: requieren `historial-caja`.

## Comportamiento

- El panel operativo muestra únicamente estado, total de ingresos, total de
  egresos y el botón para registrar egresos.
- No muestra monto inicial, balance, ingresos por método de pago, ingresos por
  origen, detalle de movimientos ni historial.
- Si no hay caja activa, el panel operativo indica que un administrador debe
  aperturarla y no ofrece el botón “Iniciar Caja”.
- `CajaController@index` calcula el resumen completo y carga relaciones solo
  para `ver-resumen-caja`. Para el resto usa `CajaService::calcularTotales()`,
  que consulta únicamente los totales de ingresos y egresos.
- No hay migración ni cambio de esquema: ventas, cambios de aceite y lavados
  siguen asociándose a la misma caja activa.

## Archivos clave

- `database/seeders/PermissionSeeder.php`, `database/seeders/AuthSeeder.php`
- `routes/web.php`
- `app/Http/Controllers/CajaController.php`
- `app/Services/CajaService.php`
- `resources/views/caja/panel.blade.php` (Caja General)
- `resources/views/caja/panel-operativo.blade.php` (Caja Chica)
- `resources/views/caja/partials/mensajes.blade.php`
- `resources/views/caja/partials/modal-egreso.blade.php`
- `resources/js/caja/guardia-cierre.js`
- `resources/views/layouts/app.blade.php`
- `app/Providers/AppServiceProvider.php`
- `tests/Feature/CajaPermisosTest.php`
- `tests/Feature/CajaGuardiaCierreTest.php`
- `tests/js/caja/guardia-cierre.test.js`

## Guardia de cierre con caja abierta

- El layout administrativo expone `data-caja-abierta="1"` solo cuando el
  usuario tiene `acceso-caja` y existe una caja activa; `data-caja-url` contiene
  la ruta de Caja usada por el retorno forzado.
- `resources/js/caja/guardia-cierre.js` registra `beforeunload` para advertir al
  cerrar la pestaña, recargar o salir del historial del navegador.
- Si el usuario cancela el diálogo nativo, se muestra un modal no descartable
  con una única acción: **Volver a Caja**. Desde allí puede cerrar la caja.
- La navegación interna y los formularios normales se permiten sin advertencia.
- El formulario de logout lleva `data-caja-guard="always"`: bloquea el envío y
  muestra el mismo modal con la única acción de volver a Caja.
- Las navegaciones programáticas de `confirmations.js`,
  `cambio-aceite/confirmar.js` y `lavados/confirmar.js` llaman a
  `permitirSalidaCaja()` antes de enviar el formulario.
- Limitación: si el usuario confirma **Salir** en el diálogo nativo del
  navegador, la pestaña se cierra y la web no puede impedirlo; el navegador no
  permite reemplazar ese diálogo por un modal propio. En móviles el diálogo
  puede no mostrarse. Si la caja se abre o cierra en otra pestaña, el estado
  queda desactualizado hasta recargar.

## Despliegue

Tras actualizar el código, re-ejecutar el seeder de autenticación para crear los
permisos y sincronizar los roles:

```bash
php artisan db:seed --class=AuthSeeder
```

`AuthSeeder` usa `syncPermissions`, por lo que cualquier permiso personalizado
asignado manualmente al rol Vendedor debe revisarse antes de ejecutarlo.

## Verificación

```bash
php artisan test tests/Feature/CajaPermisosTest.php
php artisan test tests/Feature/CajaArqueoTest.php
php artisan test tests/Feature/CajaGuardiaCierreTest.php
npm test -- tests/js/caja/guardia-cierre.test.js
vendor/bin/pint --test app/Http/Controllers/CajaController.php app/Services/CajaService.php
```

Las pruebas de permisos cubren la visibilidad de ambas vistas, el bloqueo de
acciones administrativas, el registro de egresos y la ausencia de datos
restringidos en el HTML del panel operativo. La guardia de cierre verifica los
atributos globales, su ausencia para usuarios sin acceso a caja y las reglas
puras de navegación interna; el bloqueo del logout y el modal de retorno se
validan manualmente en el navegador.
