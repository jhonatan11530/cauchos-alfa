<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendedorController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureUserIsAdmin;

// Pagina web publica
Route::get('/', [SiteController::class, 'home'])->name('site.home');
Route::get('/catalogo', [SiteController::class, 'catalog'])->name('site.catalog');
Route::middleware('auth')->group(function () {
    Route::post('/vendedor/pedido/agregar', [SiteController::class, 'addToCart'])->name('site.seller.add');
    Route::post('/vendedor/pedido/quitar', [SiteController::class, 'removeCartItem'])->name('site.seller.remove');
    Route::get('/vendedor/pedido', [SiteController::class, 'showSellerOrder'])->name('site.seller.order');
});

Route::get('/contacto', [SiteController::class, 'contact'])->name('site.contact');
Route::post('/contacto', [SiteController::class, 'sendContact'])->name('site.contact.send');

// Acceso de vendedores desde la web publica
Route::get('/vendedor', [SiteController::class, 'showSellerLogin'])->name('site.seller.login');
Route::post('/vendedor', [SiteController::class, 'sellerLogin'])->name('site.seller.auth');
Route::post('/vendedor/salir', [SiteController::class, 'sellerLogout'])->name('site.seller.logout');

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('login.store');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    // Zona administrativa (los vendedores son redirigidos al catalogo)
    Route::middleware('no-seller')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('clientes', ClientController::class);
        Route::resource('categoria', CategoryController::class);
        Route::resource('productos', ProductController::class)->except('show');
        Route::get('catalogos/{catalogo}/pdf', [CatalogController::class, 'pdf'])->name('catalogos.pdf');
        Route::resource('catalogos', CatalogController::class);
        Route::get('pedidos', [OrderController::class, 'index'])->name('pedidos.index');
        Route::get('pedidos/{pedido}', [OrderController::class, 'show'])->name('pedidos.show');
        Route::get('pedidos/{pedido}/edit', [OrderController::class, 'edit'])->name('pedidos.edit');
        Route::match(['put', 'patch'], 'pedidos/{pedido}', [OrderController::class, 'update'])->name('pedidos.update');
        Route::delete('pedidos/{pedido}', [OrderController::class, 'destroy'])->name('pedidos.destroy');
        Route::patch('pedidos/{pedido}/estado', [OrderController::class, 'updateStatus'])->name('pedidos.estado');
        Route::middleware(EnsureUserIsAdmin::class)->group(function () {
            Route::resource('usuarios', UserController::class)->except('show');
            Route::resource('estados-pedido', OrderStatusController::class)->except('show');
            Route::resource('vendedores', VendedorController::class)->except('show');
        });
    });

    // Creacion de pedidos: permitida tambien para vendedores (misma logica del dashboard)
    Route::get('pedidos/create', [OrderController::class, 'create'])->name('pedidos.create');
    Route::post('pedidos', [OrderController::class, 'store'])->name('pedidos.store');
});
