<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserToggleController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TrabajadorController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\CambioAceiteController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CategoriaController;
use Illuminate\Support\Facades\Route;

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
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

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
        Route::resource('trabajadores', TrabajadorController::class)->except(['show'])->parameters(['trabajadores' => 'trabajador']);
    });

    Route::middleware('permission:acceso-inventario')->group(function () {
        Route::get('productos/buscar', [ProductoController::class, 'buscar'])->name('productos.buscar');
        Route::patch('productos/{producto}/stock', [ProductoController::class, 'updateStock'])->name('productos.updateStock');
        Route::patch('productos/{producto}/toggle-status', [ProductoController::class, 'toggleStatus'])->name('productos.toggleStatus');
        Route::resource('productos', ProductoController::class)->except(['show']);
        Route::resource('categorias', CategoriaController::class)->except(['show']);
    });

    Route::middleware('permission:acceso-servicios')->group(function () {
        Route::resource('servicios', ServicioController::class)
             ->except(['show'])
             ->parameters(['servicios' => 'servicio']);
    });

    Route::middleware('permission:acceso-vehiculos')->group(function () {
        Route::resource('vehiculos', VehiculoController::class)
             ->except(['show'])
             ->parameters(['vehiculos' => 'vehiculo']);
    });

    Route::middleware('permission:acceso-clientes')->group(function () {
        Route::resource('clientes', ClienteController::class)
             ->except(['show'])
             ->parameters(['clientes' => 'cliente']);
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
    Route::get('ingresos/{ingreso}/ticket', [IngresoController::class, 'ticket'])
         ->name('ingresos.ticket');

    // Ajax route — must be registered BEFORE the resource to avoid Route Model Binding conflicts
    Route::get('/ingresos/buscar-servicios', [IngresoController::class, 'buscarServicios'])
         ->name('ingresos.buscar-servicios');

    // Rutas nuevas — deben ir ANTES del resource para evitar que Laravel interprete
    // 'confirmados' como un parámetro {ingreso}
    Route::get('/ingresos/confirmados', [IngresoController::class, 'confirmados'])
         ->name('ingresos.confirmados');

    Route::get('/ingresos/{ingreso}/confirmar', [IngresoController::class, 'confirmar'])
         ->name('ingresos.confirmar');

    Route::post('/ingresos/{ingreso}/confirmar', [IngresoController::class, 'procesarConfirmacion'])
         ->name('ingresos.procesarConfirmacion');

    Route::resource('ingresos', IngresoController::class);

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
    Route::get('/',         [CajaController::class, 'index'])->name('index');
    Route::post('/abrir',   [CajaController::class, 'abrir'])->name('abrir');
    Route::post('/cerrar',  [CajaController::class, 'cerrar'])->name('cerrar');
    Route::post('/egresos', [CajaController::class, 'registrarEgreso'])->name('egresos.store');

    // Solo con historial-caja
    Route::middleware('permission:historial-caja')->group(function () {
        Route::get('/historial', [CajaController::class, 'historial'])->name('historial');
        Route::get('/{caja}',    [CajaController::class, 'detalle'])->name('detalle');
    });
});
