<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendedorController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

// Pagina web publica
Route::get('/', [SiteController::class, 'home'])->name('site.home');
Route::get('/catalogo', [SiteController::class, 'catalog'])->name('site.catalog');
Route::get('/catalogo/{categoriaId}-{slug}', [SiteController::class, 'catalog'])->name('site.catalog.category');
Route::get('/producto/{id}-{slug}', [SiteController::class, 'product'])->name('site.product');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('site.sitemap');
Route::get('/feed.xml', [SitemapController::class, 'feed'])->name('site.feed');
Route::middleware('auth')->group(function () {
    Route::post('/vendedor/pedido/agregar', [SiteController::class, 'addToCart'])->name('site.seller.add');
    Route::post('/vendedor/pedido/quitar', [SiteController::class, 'removeCartItem'])->name('site.seller.remove');
    Route::get('/vendedor/pedido', [SiteController::class, 'showSellerOrder'])->name('site.seller.order');
    Route::get('/vendedor/pedidos', [SiteController::class, 'sellerOrders'])->name('site.seller.orders');
    Route::get('/vendedor/pedidos-despachados', [SiteController::class, 'sellerOrders'])->name('site.seller.deliveries');
    Route::match(['post', 'patch'], '/vendedor/pedidos/{pedido}/entregar', [SiteController::class, 'deliverOrder'])->name('site.seller.orders.deliver');
    Route::match(['post', 'patch'], '/vendedor/pedidos/{pedido}/entrega', [SiteController::class, 'deliverOrder'])->name('site.seller.deliver');
    Route::get('/vendedor/catalogo/descargar-ultimo', [SiteController::class, 'downloadLatestCatalog'])->name('site.seller.catalog.latest');
});

// Acceso de vendedores desde la web publica
Route::get('/vendedor', [SiteController::class, 'showSellerLogin'])->name('site.seller.login');
Route::post('/vendedor', [SiteController::class, 'sellerLogin'])->name('site.seller.auth');
Route::post('/vendedor/salir', [SiteController::class, 'sellerLogout'])->name('site.seller.logout');

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('login.store');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');
Route::get('catalogos/{catalogo}/public-pdf', [CatalogController::class, 'publicPdf'])
    ->name('catalogos.public-pdf');

Route::middleware('auth')->group(function () {
    // Creacion de pedidos: permitida tambien para vendedores (debe ir antes de pedidos/{pedido})
    Route::get('pedidos/create', [OrderController::class, 'create'])->name('pedidos.create');
    Route::post('pedidos', [OrderController::class, 'store'])->name('pedidos.store');

    // Zona administrativa (los vendedores son redirigidos al catalogo)
    Route::middleware('no-seller')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('clientes', ClientController::class);

        // Rutas de eliminación definitiva
        Route::delete('categoria/{categoria}/force-delete', [CategoryController::class, 'forceDelete'])->name('categoria.forceDelete');
        Route::delete('productos/{producto}/force-delete', [ProductController::class, 'forceDelete'])->name('productos.forceDelete');

        Route::resource('categoria', CategoryController::class);
        Route::resource('productos', ProductController::class)->except('show');
        Route::delete('productos/{producto}/imagenes/{imagen}', [ProductController::class, 'destroyImage'])
            ->middleware(EnsureUserIsAdmin::class)
            ->name('productos.images.destroy');

        Route::post('productos/{producto}/replace-main-image', [ProductController::class, 'replaceMainImage'])
            ->name('productos.images.replaceMain');

        Route::post('productos/{producto}/upload-flyer', [ProductController::class, 'uploadFlyer'])
            ->name('productos.images.uploadFlyer');

        Route::post('productos/imagenes/{imagen}/replace', [ProductController::class, 'replaceGalleryImage'])
            ->name('productos.images.replaceGallery');

        Route::get('catalogos/{catalogo}/pdf', [CatalogController::class, 'pdf'])->name('catalogos.pdf');
        Route::resource('catalogos', CatalogController::class);
        Route::get('pedidos', [OrderController::class, 'index'])->name('pedidos.index');
        Route::get('pedidos/{pedido}', [OrderController::class, 'show'])->name('pedidos.show');
        Route::get('pedidos/{pedido}/pdf', [OrderController::class, 'pdf'])->name('pedidos.pdf');
        Route::get('pedidos/{pedido}/edit', [OrderController::class, 'edit'])->name('pedidos.edit');
        Route::match(['put', 'patch'], 'pedidos/{pedido}', [OrderController::class, 'update'])->name('pedidos.update');
        Route::delete('pedidos/{pedido}', [OrderController::class, 'destroy'])->name('pedidos.destroy');
        Route::patch('pedidos/{pedido}/estado', [OrderController::class, 'updateStatus'])->name('pedidos.estado');
        Route::middleware(EnsureUserIsAdmin::class)->group(function () {
            Route::resource('usuarios', UserController::class)->except('show');
            Route::resource('estados-pedido', OrderStatusController::class)->except('show');
            Route::resource('vendedores', VendedorController::class)
                ->except('show')
                ->parameters(['vendedores' => 'vendedor']);
            Route::get('configuracion', [SettingController::class, 'index'])->name('settings.index');
            Route::put('configuracion', [SettingController::class, 'update'])->name('settings.update');
            Route::post('configuracion/reset', [SettingController::class, 'resetPrompt'])->name('settings.reset');
        });
    });
});
