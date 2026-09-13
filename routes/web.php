<?php

use App\Http\Controllers\AccionesAuditoriaController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AutomotorController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CambioAceiteController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ContenidoWebController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\LavadoController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\PaginaInicioController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\TrabajadorController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserToggleController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\VentaController;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/test-cloudinary', function () {
    $result = Cloudinary::uploadApi()->upload(
        public_path('test.jpg')
    );

    return $result['secure_url'];
});

// Página pública de inicio
Route::get('/', [PaginaInicioController::class, 'index'])->name('inicio');

// Página pública de servicios.
// NOTA: NO se usa '/servicios' porque ese URI lo ocupa el resource del panel
// (servicios.index); en Laravel 12 una misma URI+método reemplaza a la ruta
// previa. Por eso la pública usa '/nuestros-servicios'.
Route::get('/nuestros-servicios', [PaginaInicioController::class, 'servicios'])->name('publica.servicios');

// Página pública de productos.
// NOTA: igual que '/servicios', '/productos' lo ocupa el resource del panel
// (productos.index); en Laravel 12 una misma URI+método reemplaza a la ruta
// previa. Por eso la pública usa '/nuestros-productos'.
Route::get('/nuestros-productos', [PaginaInicioController::class, 'productos'])->name('publica.productos');

// Vista filtrada de productos por categoría (dropdown del navbar).
// NOTA ruta estática primero: se declara tras '/nuestros-productos' y usa
// binding por slug ({categoria:slug}), así que no colisiona con el resource
// del panel ni con la página estática. Ruta no encontrada => 404 automático.
Route::get('/nuestros-productos/{categoria:slug}', [PaginaInicioController::class, 'productosCategoria'])->name('publica.productos.categoria');

// Detalle de producto (visto desde una categoría).
// Se declara después de la de categoría: binding por slug de la categoría
// y por id del producto ({producto}); un producto de otra categoría, inactivo
// o inexistente responde 404 desde el propio controlador.
Route::get('/nuestros-productos/{categoria:slug}/{producto}', [PaginaInicioController::class, 'productoDetalle'])->name('publica.productos.detalle');

// Página pública de marcas.
// NOTA: igual que '/productos', '/marcas' lo ocupa el resource del panel
// (marcas.index); en Laravel 12 una misma URI+método reemplaza a la ruta
// previa. Por eso la pública usa '/nuestras-marcas'.
Route::get('/nuestras-marcas', [PaginaInicioController::class, 'marcas'])->name('publica.marcas');

// Página pública "Cotiza con nosotros" (formulario de contacto).
// No colisiona con ninguna ruta del panel, pero se mantiene en la zona
// de rutas públicas junto a las demás páginas del sitio.
Route::get('/cotiza', [PaginaInicioController::class, 'cotiza'])->name('publica.cotiza');

// Página pública "¿Quiénes somos?" (misión, visión y valores).
Route::get('/quienes-somos', [PaginaInicioController::class, 'quienesSomos'])->name('publica.quienes-somos');

// Auth routes (guests only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Logout (requires auth)
Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Protected dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

// User management & Settings (Administrador permissions)
Route::middleware(['auth'])->group(function () {
    Route::middleware('permission:acceso-usuarios')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::patch('users/{user}/toggle', [UserToggleController::class, 'toggle'])->name('users.toggle');
    });

    Route::middleware('permission:acceso-roles')->group(function () {
        Route::resource('roles', RoleController::class)->except(['show']);
    });

    Route::middleware('permission:acceso-trabajadores')->group(function () {
        Route::patch('trabajadores/{trabajador}/toggle-status', [TrabajadorController::class, 'toggleStatus'])->name('trabajadores.toggleStatus');

        // Ajax route — must be registered BEFORE the resource to avoid Route Model Binding conflicts
        Route::get('/trabajadores/consultar-dni', [TrabajadorController::class, 'consultarDni'])->name('trabajadores.consultarDni');

        Route::resource('trabajadores', TrabajadorController::class)->except(['show'])->parameters(['trabajadores' => 'trabajador']);
    });

    Route::middleware('permission:acceso-asistencia')->group(function () {
        // Ajax routes — must be registered BEFORE other routes to avoid conflicts
        Route::get('/asistencia/por-fecha', [AsistenciaController::class, 'porFecha'])->name('asistencia.porFecha');
        Route::get('/asistencia/por-mes', [AsistenciaController::class, 'porMes'])->name('asistencia.porMes');
        Route::post('/asistencia/marcar', [AsistenciaController::class, 'marcar'])->name('asistencia.marcar');

        Route::get('/asistencia', [AsistenciaController::class, 'index'])->name('asistencia.index');
    });

    Route::middleware('permission:acceso-inventario')->group(function () {
        Route::get('productos/buscar', [ProductoController::class, 'buscar'])->name('productos.buscar');
        Route::patch('productos/{producto}/stock', [ProductoController::class, 'updateStock'])->name('productos.updateStock');
        Route::patch('productos/{producto}/toggle-status', [ProductoController::class, 'toggleStatus'])->name('productos.toggleStatus');
        Route::resource('productos', ProductoController::class)->except(['show']);
        Route::resource('categorias', CategoriaController::class)->except(['show']);
        Route::resource('marcas', MarcaController::class)->except(['show']);
    });

    Route::middleware('permission:acceso-servicios')->group(function () {
        // AJAX route — must be registered BEFORE the resource to avoid Route Model Binding conflicts
        Route::patch('servicios/{servicio}/toggle-status', [ServicioController::class, 'toggleStatus'])
            ->name('servicios.toggleStatus');

        Route::resource('servicios', ServicioController::class)
            ->except(['show'])
            ->parameters(['servicios' => 'servicio']);
    });

    Route::middleware('permission:acceso-contenido-web')->group(function () {
        // Pantalla única de contenidos de la web (GET / PUT)
        Route::get('contenido-web', [ContenidoWebController::class, 'edit'])->name('contenido-web.edit');
        Route::put('contenido-web', [ContenidoWebController::class, 'update'])->name('contenido-web.update');
    });

    Route::middleware('permission:acceso-vehiculos')->group(function () {
        Route::resource('vehiculos', VehiculoController::class)
            ->except(['show'])
            ->parameters(['vehiculos' => 'vehiculo']);
    });

    Route::middleware('permission:acceso-clientes')->group(function () {
        // Ajax route — must be registered BEFORE the resource to avoid Route Model Binding conflicts
        Route::get('/clientes/consultar-dni', [ClienteController::class, 'consultarDni'])->name('clientes.consultarDni');

        Route::resource('clientes', ClienteController::class)
            ->except(['show'])
            ->parameters(['clientes' => 'cliente']);
    });

    Route::middleware('permission:acceso-automotores')->group(function () {
        // Ajax route — must be registered BEFORE the resource to avoid Route Model Binding conflicts
        Route::get('/automotores/consultar-placa', [AutomotorController::class, 'consultarPlaca'])
            ->name('automotores.consultarPlaca');

        Route::resource('automotores', AutomotorController::class)
            ->except(['show'])
            ->parameters(['automotores' => 'automotor']);
    });
});

// Ventas (Protected by 'acceso-ventas')
Route::middleware(['auth', 'permission:acceso-ventas'])->group(function () {
    // Ajax route — must be registered BEFORE the resource to avoid Route Model Binding conflicts
    Route::get('/clientes/buscar-por-placa', [ClienteController::class, 'buscarPorPlaca'])
        ->name('clientes.buscar-por-placa');

    Route::get('/ventas/buscar-productos', [VentaController::class, 'buscarProductos'])
        ->name('ventas.buscar-productos');

    Route::resource('ventas', VentaController::class)
        ->only(['index', 'create', 'store', 'show', 'destroy']);

    Route::get('/ventas/{venta}/ticket', [VentaController::class, 'ticket'])
        ->name('ventas.ticket');

    // Ticket route — must be registered BEFORE the resource to avoid Route Model Binding conflicts
    Route::get('lavados/{lavado}/ticket', [LavadoController::class, 'ticket'])
        ->name('lavados.ticket');

    // Ajax route — must be registered BEFORE the resource to avoid Route Model Binding conflicts
    Route::get('/lavados/buscar-servicios', [LavadoController::class, 'buscarServicios'])
        ->name('lavados.buscar-servicios');

    // Rutas nuevas — deben ir ANTES del resource para evitar que Laravel interprete
    // 'confirmados' como un parámetro {lavado}
    Route::get('/lavados/confirmados', [LavadoController::class, 'confirmados'])
        ->name('lavados.confirmados');

    Route::get('/lavados/{lavado}/confirmar', [LavadoController::class, 'confirmar'])
        ->name('lavados.confirmar');

    Route::post('/lavados/{lavado}/confirmar', [LavadoController::class, 'procesarConfirmacion'])
        ->name('lavados.procesarConfirmacion');

    Route::resource('lavados', LavadoController::class);

    // Cambio de Aceite — Ajax route BEFORE resource to avoid Route Model Binding conflicts
    Route::get('/cambio-aceite/buscar-productos', [CambioAceiteController::class, 'buscarProductos'])
        ->name('cambio-aceite.buscar-productos');

    // Rutas nuevas — deben ir ANTES del resource para evitar que Laravel interprete
    // 'confirmados' o 'confirmar' como un parámetro {cambioAceite}
    Route::get('/cambio-aceite/confirmados', [CambioAceiteController::class, 'confirmados'])
        ->name('cambio-aceite.confirmados');

    Route::get('/cambio-aceite/{cambioAceite}/confirmar', [CambioAceiteController::class, 'confirmar'])
        ->name('cambio-aceite.confirmar');

    Route::post('/cambio-aceite/{cambioAceite}/confirmar', [CambioAceiteController::class, 'procesarConfirmacion'])
        ->name('cambio-aceite.procesarConfirmacion');

    Route::put('/cambio-aceite/{cambioAceite}/actualizar-ticket', [CambioAceiteController::class, 'actualizarTicket'])
        ->name('cambio-aceite.actualizarTicket');

    Route::resource('cambio-aceite', CambioAceiteController::class);

    Route::get('/cambio-aceite/{cambioAceite}/ticket', [CambioAceiteController::class, 'ticket'])
        ->name('cambio-aceite.ticket');
});

// Caja (Protected by 'acceso-caja' for operations, 'historial-caja' for history)
Route::middleware(['auth', 'permission:acceso-caja'])->prefix('caja')->name('caja.')->group(function () {
    Route::get('/', [CajaController::class, 'index'])->name('index');
    Route::post('/abrir', [CajaController::class, 'abrir'])->name('abrir');
    Route::post('/cerrar', [CajaController::class, 'cerrar'])->name('cerrar');
    Route::post('/egresos', [CajaController::class, 'registrarEgreso'])->name('egresos.store');

    // Solo con historial-caja
    Route::middleware('permission:historial-caja')->group(function () {
        Route::get('/historial', [CajaController::class, 'historial'])->name('historial');
        Route::get('/{caja}', [CajaController::class, 'detalle'])->name('detalle');
    });
});

// Auditoría / Kardex (Protected by 'acceso-auditoria')
Route::middleware(['auth', 'permission:acceso-auditoria'])->prefix('auditoria')->name('kardex.')->group(function () {
    Route::get('/kardex', [KardexController::class, 'index'])->name('index');
    Route::get('/kardex/producto/{producto}', [KardexController::class, 'porProducto'])->name('porProducto');
});

// Auditoría de Acciones (Protected by 'acceso-auditoria')
Route::middleware(['auth', 'permission:acceso-auditoria'])->prefix('auditoria')->name('auditoria.')->group(function () {
    Route::get('/acciones', [AccionesAuditoriaController::class, 'index'])->name('acciones.index');
    Route::get('/acciones/{registroAuditoria}', [AccionesAuditoriaController::class, 'show'])->name('acciones.show');
});

// Reportes (Protected by 'acceso-reportes') — solo lectura, todas las rutas estáticas
Route::middleware(['auth', 'permission:acceso-reportes'])->prefix('reportes')->name('reportes.')->group(function () {
    Route::get('/', [ReporteController::class, 'index'])->name('index');
    Route::get('/ingresos', [ReporteController::class, 'ingresos'])->name('ingresos');
    Route::get('/ventas', [ReporteController::class, 'ventas'])->name('ventas');
    Route::get('/lavados', [ReporteController::class, 'lavados'])->name('lavados');
    Route::get('/cambio-aceite', [ReporteController::class, 'cambioAceite'])->name('cambioAceite');
    Route::get('/inventario', [ReporteController::class, 'inventario'])->name('inventario');
    Route::get('/clientes', [ReporteController::class, 'clientes'])->name('clientes');
    Route::get('/caja', [ReporteController::class, 'caja'])->name('caja');
    Route::get('/personal', [ReporteController::class, 'personal'])->name('personal');
    Route::get('/kardex', [ReporteController::class, 'kardex'])->name('kardex');
});
