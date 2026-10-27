# Estructura de componentes del sistema

Árbol de carpetas y archivos del proyecto **sin** `.kiro/` (ni dependencias: `vendor/`, `node_modules/`).

Leyenda: `📁` carpeta · `📄` archivo · `…` se omiten archivos repetitivos del mismo patrón (se indica el conteo).

```
carwash_sistema/
│
├── 📄 artisan                     # Consola Laravel
├── 📄 vite.config.js              # Vite 7 + Tailwind 4 + inputs de módulos JS
├── 📄 composer.json / composer.lock
├── 📄 package.json / package-lock.json
├── 📄 phpunit.xml                 # Tests PHPUnit (SQLite en memoria)
├── 📄 Dockerfile
├── 📄 .dockerignore               # Excluye .env, .git, dependencias y datos
├── 📄 docker/000-default.conf     # Apache (vhost)
├── 📄 start.sh
├── 📄 AGENTS.md
├── 📄 README.md                   # Boilerplate Laravel sin modificar
├── 📄 .env / .env.example
├── 📄 .editorconfig / .gitattributes / .gitignore
│
├── 📁 app/
│   ├── 📁 Console/Commands/
│   │   └── 📄 DataCleanCommand.php            # php artisan data:clean
│   │
│   ├── 📁 Http/
│   │   ├── 📁 Controllers/
│   │   │   ├── 📄 Controller.php
│   │   │   ├── 📄 DashboardController.php
│   │   │   ├── 📄 VentaController.php
│   │   │   ├── 📄 DevolucionVentaController.php
│   │   │   ├── 📄 LavadoController.php
│   │   │   ├── 📄 CambioAceiteController.php
│   │   │   ├── 📄 CajaController.php
│   │   │   ├── 📄 ProductoController.php
│   │   │   ├── 📄 CategoriaController.php
│   │   │   ├── 📄 MarcaController.php
│   │   │   ├── 📄 ServicioController.php
│   │   │   ├── 📄 CompraController.php
│   │   │   ├── 📄 ProveedorController.php
│   │   │   ├── 📄 AjusteInventarioController.php
│   │   │   ├── 📄 KardexController.php
│   │   │   ├── 📄 ClienteController.php
│   │   │   ├── 📄 VehiculoController.php
│   │   │   ├── 📄 AutomotorController.php
│   │   │   ├── 📄 TrabajadorController.php
│   │   │   ├── 📄 AsistenciaController.php
│   │   │   ├── 📄 ReporteController.php
│   │   │   ├── 📄 UserController.php
│   │   │   ├── 📄 UserToggleController.php
│   │   │   ├── 📄 RoleController.php
│   │   │   ├── 📄 ContenidoWebController.php
│   │   │   ├── 📄 PaginaInicioController.php
│   │   │   ├── 📄 AccionesAuditoriaController.php
│   │   │   │
│   │   │   ├── 📁 Auth/
│   │   │   │   └── 📄 LoginController.php
│   │   │   │
│   │   │   └── 📁 Cuenta/                    # Zona pública de clientes
│   │   │       ├── 📄 CuentaController.php
│   │   │       ├── 📄 LoginController.php
│   │   │       ├── 📄 RegistroController.php
│   │   │       ├── 📄 VerificacionController.php
│   │   │       ├── 📄 RecuperacionController.php
│   │   │       ├── 📄 GoogleController.php
│   │   │       ├── 📄 CarritoController.php
│   │   │       └── 📄 VehiculoController.php
│   │   │
│   │   └── 📁 Middleware/
│   │       ├── 📄 CheckUserActivo.php
│   │       ├── 📄 EnsureCliente.php
│   │       └── 📄 SecurityHeaders.php        # CSP, HSTS y cabeceras defensivas
│   │
│   ├── 📁 Mail/                                # Correos de la zona cuenta
│   │   ├── 📄 CuentaGoogleMail.php
│   │   ├── 📄 CuentaRecuperacionMail.php
│   │   └── 📄 CuentaVerificacionMail.php
│   │
│   ├── 📁 Models/                              # 34 modelos
│   │   ├── 📄 User.php
│   │   ├── 📄 Cliente.php
│   │   ├── 📄 Vehiculo.php
│   │   ├── 📄 Automotor.php
│   │   ├── 📄 Trabajador.php
│   │   ├── 📄 Asistencia.php
│   │   ├── 📄 Servicio.php / DetalleServicio.php
│   │   ├── 📄 Producto.php / Categoria.php / Marca.php
│   │   ├── 📄 Venta.php / DetalleVenta.php / DevolucionVenta.php / DetalleDevolucionVenta.php
│   │   ├── 📄 Lavado.php / LavadoTrabajador.php
│   │   ├── 📄 CambioAceite.php / CambioProducto.php
│   │   ├── 📄 Caja.php / EgresoCaja.php
│   │   ├── 📄 Compra.php / DetalleCompra.php / Proveedor.php
│   │   ├── 📄 AjusteInventario.php / DetalleAjusteInventario.php
│   │   ├── 📄 MovimientoKardex.php
│   │   ├── 📄 RegistroAuditoria.php
│   │   ├── 📄 Carrito.php / CarritoItem.php
│   │   ├── 📄 ContenidoWeb.php
│   │   ├── 📄 CustomerUserLink.php / SocialIdentity.php / OneTimeToken.php
│   │   └── (… )
│   │
│   ├── 📁 Observers/
│   │   └── 📄 AuditModelObserver.php          # Escribe en registros_auditoria
│   │
│   ├── 📁 Policies/
│   │   └── 📄 AutomotorPolicy.php
│   │
│   ├── 📁 Providers/
│   │   ├── 📄 AppServiceProvider.php          # Gate::before Administrador
│   │   └── 📄 AuditServiceProvider.php        # Lista de modelos auditables
│   │
│   ├── 📁 Services/                           # Lógica de negocio (30 archivos)
│   │   ├── 📄 CajaService.php                 # Único entrypoint de caja_id
│   │   ├── 📄 AuditService.php
│   │   ├── 📄 DataNormalizer.php              # DNI/tel/placa/nombres
│   │   ├── 📄 DniApiService.php               # api.json.pe (resiliente)
│   │   ├── 📄 AutomotorApiService.php         # api.json.pe (resiliente)
│   │   ├── 📄 GoogleOidcService.php / GoogleOidcException.php
│   │   ├── 📄 CarritoService.php
│   │   ├── 📄 ClienteAutomotorService.php
│   │   ├── 📄 ContenidoWebService.php
│   │   ├── 📄 CostoPromedioService.php
│   │   ├── 📄 KardexService.php
│   │   ├── 📄 AjusteInventarioService.php
│   │   ├── 📄 AsistenciaService.php
│   │   ├── 📄 DashboardService.php
│   │   ├── 📄 TiempoAtencionService.php
│   │   │
│   │   └── 📁 Reportes/                       # Un servicio por reporte
│   │       ├── 📄 DateRangeFiltro.php
│   │       ├── 📄 ReporteCajaService.php
│   │       ├── 📄 ReporteCambioAceiteService.php
│   │       ├── 📄 ReporteClientesService.php
│   │       ├── 📄 ReporteComprasService.php
│   │       ├── 📄 ReporteCsvService.php
│   │       ├── 📄 ReporteIngresosService.php
│   │       ├── 📄 ReporteInventarioService.php
│   │       ├── 📄 ReporteKardexService.php
│   │       ├── 📄 ReporteLavadosService.php
│   │       ├── 📄 ReportePdfService.php
│   │       ├── 📄 ReportePersonalService.php
│   │       ├── 📄 ReporteVehiculoService.php
│   │       └── 📄 ReporteVentasService.php
│   │
│   ├── 📁 Rules/
│   │   └── 📄 PasswordSegura.php              # Política de contraseñas del panel
│   │
│   └── 📁 Support/
│       ├── 📄 helpers.php                     # Funciones globales
│       ├── 📄 ProductionSecurityGuard.php     # Falla arranque inseguro en producción
│       └── 📄 RoleNames.php                   # Nombres de roles del sistema
│
├── 📁 bootstrap/
│   ├── 📄 app.php                 # App, rutas y middleware (Laravel 12)
│   ├── 📄 providers.php           # Registro de providers
│   └── 📁 cache/                  # cache de packages/services
│
├── 📁 config/
│   ├── 📄 app.php                             # timezone = America/Lima
│   ├── 📄 auth.php / database.php / session.php
│   ├── 📄 cache.php / queue.php / logging.php / mail.php
│   ├── 📄 filesystems.php / services.php / permission.php
│   ├── 📄 seed.php                            # ADMIN_NAME/EMAIL/PASSWORD
│   └── 📄 carwash.php                         # Parámetros del negocio
│
├── 📁 database/
│   ├── 📄 database.sqlite                     # Dev (SQLite)
│   ├── 📄 schema.sql
│   ├── 📁 migrations/                         # 82 migraciones
│   │   ├── 📄 0001_01_01_000000_create_users_table.php
│   │   ├── 📄 0001_01_01_000001_create_cache_table.php
│   │   ├── 📄 0001_01_01_000002_create_jobs_table.php
│   │   ├── 📄 2024_01_01_0000NN_create_* (clientes, vehiculos, trabajadores,
│   │   │       productos, servicios, cambio_aceites, ventas, detalle_*, …)
│   │   ├── 📄 2026_*_create_permission_tables.php  # spatie/laravel-permission
│   │   ├── 📄 2026_*_create_cajas / egresos_caja / categorias / marcas
│   │   ├── 📄 2026_*_create_automotores / movimientos_kardex
│   │   ├── 📄 2026_*_create_registros_auditoria / asistencias
│   │   ├── 📄 2026_*_create_contenido_web / proveedores / compras
│   │   ├── 📄 2026_*_create_ajustes_inventario / carritos / carrito_items
│   │   ├── 📄 2026_*_create_customer_user_links / one_time_tokens / social_identities
│   │   ├── 📄 2026_*_create_devoluciones_venta
│   │   ├── 📄 2026_10_25_*_add_client_context_to_registros_auditoria
│   │   └── 📄 (… alteraciones: add_*, rename_ingresos_to_lavados, …)
│   │
│   ├── 📁 factories/                          # 14 factories
│   │   ├── 📄 UserFactory.php / ClienteFactory.php / VehiculoFactory.php
│   │   ├── 📄 TrabajadorFactory.php / ProductoFactory.php / ServicioFactory.php
│   │   ├── 📄 VentaFactory.php / LavadoFactory.php / CambioAceiteFactory.php
│   │   ├── 📄 CajaFactory.php / EgresoCajaFactory.php / CompraFactory.php
│   │   ├── 📄 DetalleCompraFactory.php / ProveedorFactory.php
│   │   └── (… )
│   │
│   └── 📁 seeders/                           # 22 seeders
│       ├── 📄 DatabaseSeeder.php             # Orquestador
│       ├── 📄 AuthSeeder.php                 # admin demo solo local/testing
│       ├── 📄 PermissionSeeder.php           # Permisos RBAC en español
│       ├── 📄 CategoriaSeeder.php / MarcaSeeder.php / ProductoSeeder.php
│       ├── 📄 ServicioSeeder.php / DetalleServicioSeeder.php
│       ├── 📄 ClienteSeeder.php / VehiculoSeeder.php / AutomotorSeeder.php
│       ├── 📄 TrabajadorSeeder.php / LavadoTrabajadorSeeder.php
│       ├── 📄 LavadoSeeder.php / CambioAceiteSeeder.php / CambioProductoSeeder.php
│       ├── 📄 VentaSeeder.php / DetalleVentaSeeder.php
│       ├── 📄 ProveedorSeeder.php / CompraSeeder.php / KardexSeeder.php
│       └── (… )
│
├── 📁 docs/                                  # Documentación funcional
│   ├── 📄 resumen-sistema-procesos-negocio.md
│   ├── 📄 design.md
│   ├── 📄 feature-*.md                       # 15 documentos de feature
│   ├── 📄 fix-*.md / plan-*.md / flujo-*.md / modulo-*.md / patrones
│   ├── 📁 pagina/                            # Textos del sitio público
│   │   ├── 📄 inicio.txt / servicios.txt / productos.txt / marcas.txt
│   │   ├── 📄 quienes_somos.txt / contacto.txt / ofertas.txt
│   │   └── 📄 detalleproducto.txt / productosfiltrados.txt
│   └── (… 41 archivos)
│
├── 📁 public/
│   ├── 📄 index.php                           # Front controller
│   ├── 📄 .htaccess / robots.txt / favicon.ico
│   └── 📁 storage/                            # Symlink a storage/app/public
│
├── 📁 resources/
│   ├── 📁 css/
│   │   ├── 📄 app.css                         # Tokens @theme modo oscuro (.bg-surface, .input-main, …)
│   │   └── 📁 publica/
│   │       └── 📄 publica.css
│   │
│   ├── 📁 js/                                 # 60 módulos modulares (1 por vista)
│   │   ├── 📄 app.js / bootstrap.js           # Entry points globales
│   │   ├── 📄 confirmations.js                # SweetAlert2
│   │   ├── 📄 dashboard.js                    # Chart.js
│   │   ├── 📄 modal.js / stock-modal.js / image-viewer.js / toggle-status.js
│   │   ├── 📄 buscador-placa.js
│   │   │
│   │   ├── 📁 ventas/               └── 📄 create.js
│   │   ├── 📁 devoluciones/         └── 📄 create.js
│   │   ├── 📁 lavados/              └── 📄 create.js, edit.js, confirmar.js, shared.js
│   │   ├── 📁 cambio-aceite/        └── 📄 create.js, edit.js, confirmar.js, shared.js
│   │   ├── 📁 productos/            └── 📄 index.js, create.js, edit.js, shared.js
│   │   ├── 📁 servicios/            └── 📄 index.js, create.js, edit.js
│   │   ├── 📁 compras/              └── 📄 create.js, edit.js, buscar-productos.js, buscar-proveedores.js
│   │   ├── 📁 ajustes/              └── 📄 create.js
│   │   ├── 📁 caja/                 └── 📄 cerrar.js
│   │   ├── 📁 clientes/             └── 📄 index.js, validate.js
│   │   ├── 📁 categorias/           └── 📄 validate.js
│   │   ├── 📁 marcas/               └── 📄 validate.js
│   │   ├── 📁 proveedores/          └── 📄 validate.js
│   │   ├── 📁 vehiculos/            └── 📄 create.js
│   │   ├── 📁 automotores/          └── 📄 validate.js
│   │   ├── 📁 trabajadores/         └── 📄 index.js, create.js, edit.js
│   │   ├── 📁 asistencia/           └── 📄 index.js
│   │   ├── 📁 roles/                └── 📄 validate.js
│   │   ├── 📁 users/                └── 📄 toggle.js, validate.js
│   │   ├── 📁 reportes/             └── 📄 ingresos.js, print.js
│   │   ├── 📁 contenido-web/        └── 📄 edit.js, reparto.js
│   │   ├── 📁 cuenta/               └── 📄 carrito.js
│   │   ├── 📁 utils/                └── 📄 consultarDni.js, input-filters.js, validation.js
│   │   └── 📁 publica/              └── 📄 app.js, productos-filtros.js, nav-productos.js,
│   │                                  nav-productos-core.js, animaciones.js, mobile-menu.js
│   │
│   └── 📁 views/                              # 142 Blade templates
│       ├── 📄 dashboard.blade.php
│       ├── 📄 welcome.blade.php
│       │
│       ├── 📁 layouts/
│       │   ├── 📄 app.blade.php               # Panel admin (sidebar)
│       │   ├── 📄 auth.blade.php
│       │   └── 📄 publica.blade.php           # Sitio público
│       │
│       ├── 📁 components/     └── 📄 modal.blade.php
│       ├── 📁 partials/       └── 📄 theme-script.blade.php
│       │
│       ├── 📁 auth/            └── 📄 login.blade.php
│       │
│       ├── 📁 ventas/          └── 📄 index, create, show, ticket + devoluciones/{create}
│       ├── 📁 devoluciones/    └── 📄 index, show
│       ├── 📁 lavados/         └── 📄 index, create, edit, show, confirmar, confirmados, pendientes, ticket
│       ├── 📁 cambio-aceite/   └── 📄 index, create, edit, show, confirmar, confirmados, pendientes, ticket
│       ├── 📁 caja/            └── 📄 panel, detalle, historial
│       ├── 📁 productos/       └── 📄 index, create, edit
│       ├── 📁 servicios/       └── 📄 index, create, edit
│       ├── 📁 categorias/      └── 📄 index, create, edit
│       ├── 📁 marcas/          └── 📄 index, create, edit
│       ├── 📁 compras/         └── 📄 index, create, edit, show
│       ├── 📁 proveedores/     └── 📄 index, create, edit
│       ├── 📁 ajustes/         └── 📄 index, create, show
│       ├── 📁 kardex/          └── 📄 index, por-producto
│       ├── 📁 clientes/        └── 📄 index, create, edit
│       ├── 📁 vehiculos/       └── 📄 index, create, edit
│       ├── 📁 automotores/     └── 📄 index, create, edit
│       ├── 📁 trabajadores/    └── 📄 index, create, edit
│       ├── 📁 asistencia/      └── 📄 index
│       ├── 📁 roles/           └── 📄 index, create, edit
│       ├── 📁 users/           └── 📄 index, create, edit
│       ├── 📁 auditoria/acciones/ └── 📄 index, show
│       ├── 📁 contenido-web/   └── 📄 edit
│       │
│       ├── 📁 reportes/
│       │   ├── 📄 index, caja, cambio-aceite, clientes, compras-proveedor,
│       │   │   ingresos, inventario, kardex, lavados, personal, vehiculo, ventas
│       │   ├── 📁 partials/    └── 📄 filtros, acciones
│       │   └── 📁 pdf/         └── 📄 base + 12 reportes PDF
│       │
│       ├── 📁 emails/cuenta/   └── 📄 google, recuperacion, verificacion
│       │
│       ├── 📁 cuenta/                          # Zona pública de clientes
│       │   ├── 📄 panel, login, registro, verificacion, recuperacion,
│       │   │   nueva-clave, google-vincular
│       │   ├── 📁 carrito/     └── 📄 index, pdf
│       │   └── 📁 vehiculos/   └── 📄 index, show
│       │
│       └── 📁 publica/                        # Sitio web público
│           ├── 📄 inicio, servicios, productos, productos-categoria,
│           │   producto-detalle, marcas, quienes-somos, cotiza
│           └── 📁 partials/
│               ├── 📄 header, footer, boton-whatsapp, sellos-confianza
│               ├── 📄 card-servicio-{grande,pequena,item}
│               ├── 📄 card-producto-{grande,pequena}
│               ├── 📄 grilla-marcas, icono-producto, icono-servicio
│               ├── 📄 promo-categoria-producto, anim-script
│               └── (… )
│
├── 📁 routes/
│   ├── 📄 web.php                             # Rutas (estáticas ANTES de resources)
│   └── 📄 console.php                         # Comandos programados
│
├── 📁 storage/
│   ├── 📁 app/
│   │   ├── 📁 public/                         # Subidas (Cloudinary es el prod)
│   │   │   └── 📁 carrito-pdfs/
│   │   └── 📁 private/
│   ├── 📁 framework/                          # cache, sessions, views compiladas
│   └── 📁 logs/
│
├── 📁 tests/                                  # 100 PHP + 14 JS
│   ├── 📄 TestCase.php
│   │
│   ├── 📁 Feature/                            # Tests de integración (PHPUnit)
│   │   ├── 📄 Venta*/Producto*/Servicio*/Marca*/Proveedor*Test.php
│   │   ├── 📄 Compra*Test.php (3) / KardexTest / ConsumoInternoTest
│   │   ├── 📄 Publica*Test.php (7) / ContenidoWeb* / SeederWebFlowTest
│   │   ├── 📄 *CloudinaryTest.php (4)        # Cloudinary mockeado
│   │   ├── 📄 LayoutTest / DashboardServiceTest / StockUpdateModalTest
│   │   ├── 📁 Asistencia/      └── 📄 AsistenciaTest, AsistenciaPropertyTest
│   │   ├── 📁 Auditoria/       └── 📄 Registro, Consulta, Permiso, Etiquetas, Sesiones
│   │   ├── 📁 Auth/            └── 📄 LoginTest, LoginPropertyTest
│   │   ├── 📁 CambioAceite/     └── 📄 Store, Confirmar, Destroy, ActualizarTicket, Tablas
│   │   ├── 📁 Cuenta/          └── 📄 Carrito*, CuentaGoogle*, CuentaRegistro*, …
│   │   ├── 📁 Lavado/          └── 📄 StoreTest
│   │   ├── 📁 OrdenAtencion/   └── 📄 OrdenAtencionTest, TiempoAtencionPropertyTest
│   │   ├── 📁 Reportes/        └── 📄 19 tests (uno por reporte + property)
│   │   ├── 📁 Seguridad/       └── 📄 XSS, password, sesión, credenciales, rutas
│   │   └── 📁 UserActivationToggle/ └── 📄 3 tests
│   │
│   ├── 📁 Unit/                             # Servicios aislados
│   │   ├── 📄 DniApiServiceTest / AutomotorApiServiceTest
│   │   ├── 📄 GoogleOidcServiceTest
│   │   └── 📄 ExampleTest
│   │
│   ├── 📁 Support/
│   │   └── 📄 GoogleOidcLlaves.php          # Llaves dummy para tests
│   │
│   └── 📁 js/                               # Vitest + fast-check (14)
│       ├── 📄 buscar-placa.property.test.js
│       ├── 📁 asistencia/  caja/  cambio-aceite/  clientes/  compras/
│       │   contenido-web/  devoluciones/  publica/  reportes/
│       │   servicios/  users/  ventas/
│       └── 📄 *.property.test.js
│
└── 📁 docker/                                # Configuración Apache
```

## Conteos rápidos

| Componente | Cantidad |
|---|---|
| Modelos (`app/Models`) | 34 |
| Controladores (`app/Http/Controllers`) | 36 |
| Services (`app/Services`) | 30 |
| Migraciones (`database/migrations`) | 82 |
| Seeders | 22 |
| Factories | 14 |
| Vistas Blade (`resources/views`) | 142 |
| Módulos JS (`resources/js`) | 60 |
| Entradas en `vite.config.js` | 43 |
| Tests PHPUnit (`tests/Feature` + `tests/Unit`) | 100 |
| Tests Vitest (`tests/js`) | 14 |
| Docs (`docs/`) | 41 |

## Mapa por módulo de negocio

```
VENTA ──────────── VentaController → Venta, DetalleVenta, DevolucionVenta
                    └─ VentaController / DevolucionVentaController
                    └─ views/ventas/*  ·  js/ventas/create.js

LAVADO ─────────── LavadoController → Lavado, LavadoTrabajador, DetalleServicio
                    └─ views/lavados/* · js/lavados/*

CAMBIO DE ACEITE ─ CambioAceiteController → CambioAceite, CambioProducto
                    └─ views/cambio-aceite/* · js/cambio-aceite/*

CAJA ────────────── CajaController → Caja, EgresoCaja   (CajaService = entrypoint)
                    └─ views/caja/*  ·  js/caja/cerrar.js

INVENTARIO ─────── ProductoController, CategoriaController, MarcaController
                    AjusteInventarioController, KardexController
                    └─ KardexService, CostoPromedioService, AjusteInventarioService

COMPRAS ────────── CompraController, ProveedorController → Compra, DetalleCompra
                    └─ views/compras/* · js/compras/*

CLIENTES/VEHÍC. ── ClienteController, VehiculoController, AutomotorController
                    └─ ClienteAutomotorService · DniApiService · AutomotorApiService

PERSONAL ────────── TrabajadorController, AsistenciaController
                    └─ AsistenciaService, TiempoAtencionService

REPORTES ────────── ReporteController → app/Services/Reportes/* (14 servicios)
                    └─ views/reportes/* + reportes/pdf/* · js/reportes/*

AUDITORÍA ──────── AuditServiceProvider + AuditModelObserver + AuditService
                    └─ RegistroAuditoria → views/auditoria/acciones/*

SEGURIDAD/RBAC ─── spatie/laravel-permission (config/permission.php, PermissionSeeder)
                    └─ Gate::before en AppServiceProvider (rol Administrador)

CUENTA PÚBLICA ──── Cuenta/*Controller → User, Carrito, SocialIdentity, OneTimeToken
                    └─ GoogleOidcService · views/cuenta/* · js/cuenta/carrito.js

SITIO PÚBLICO ───── PaginaInicioController, ContenidoWebController
                    └─ ContenidoWebService · views/publica/* · js/publica/*
```

## Reglas de composición relevantes

- **Frontend:** cada módulo de `resources/js/<modulo>/` debe estar en el `input` de `vite.config.js` **y** cargado con `@vite([...])` en la vista Blade; no hay `<script>` inline salvo datos (`@json`).
- **Rutas:** las estáticas/AJAX van **antes** que los `Route::resource` en `routes/web.php`.
- **Auditoría:** modelo nuevo auditable → registrar en `$modelos` de `AuditServiceProvider`; `AuditService` añade IP/user-agent.
- **Caja:** todo guardado de venta/cambio-aceite pasa por `CajaService` para asignar `caja_id`.
- **Zona horaria:** única fuente `config/app.php` → `America/Lima`.
