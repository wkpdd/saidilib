<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Storefront
// ---------------------------------------------------------------------------
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/a-propos', [HomeController::class, 'about'])->name('about');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');

Route::get('/boutique', [CatalogController::class, 'index'])->name('catalog');
Route::get('/categorie/{slug}', [CatalogController::class, 'category'])->name('category');
Route::get('/produit/{slug}', [CatalogController::class, 'show'])->name('product');

Route::get('/panier', [CartController::class, 'index'])->name('cart.index');
Route::post('/panier/ajouter', [CartController::class, 'add'])->name('cart.add');
Route::patch('/panier', [CartController::class, 'update'])->name('cart.update');
Route::delete('/panier', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/panier/vider', [CartController::class, 'clear'])->name('cart.clear');

Route::get('/commande', [CheckoutController::class, 'index'])->name('checkout.index');
Route::get('/commande/frais', [CheckoutController::class, 'fee'])->name('checkout.fee');
Route::post('/commande', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/commande/confirmee/{reference}', [CheckoutController::class, 'success'])->name('checkout.success');

Route::get('/langue/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// ---------------------------------------------------------------------------
// Customer accounts (storefront, "client" guard)
// ---------------------------------------------------------------------------
Route::prefix('compte')->name('account.')->group(function () {
    Route::get('/inscription', [Auth\ClientAuthController::class, 'showRegister'])->name('register');
    Route::post('/inscription', [Auth\ClientAuthController::class, 'register'])->name('register.post')
        ->middleware('throttle:10,1');
    Route::get('/connexion', [Auth\ClientAuthController::class, 'showLogin'])->name('login');
    Route::post('/connexion', [Auth\ClientAuthController::class, 'login'])->name('login.post')
        ->middleware('throttle:10,1');
    Route::post('/deconnexion', [Auth\ClientAuthController::class, 'logout'])->name('logout');

    Route::middleware('auth:client')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::get('/tarifs.pdf', [AccountController::class, 'priceList'])->name('pricelist');
        Route::get('/commandes/{order}', [AccountController::class, 'order'])->name('order');
    });
});

// ---------------------------------------------------------------------------
// Admin auth
// ---------------------------------------------------------------------------
Route::get('/admin/login', [LoginController::class, 'show'])->name('admin.login');
Route::post('/admin/login', [LoginController::class, 'login'])->name('admin.login.post')->middleware('throttle:10,1');
Route::post('/admin/logout', [LoginController::class, 'logout'])->name('admin.logout');

// ---------------------------------------------------------------------------
// Admin dashboard (auth + admin)
// ---------------------------------------------------------------------------
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Notifications — available to any back-office user.
    Route::get('notifications', [Admin\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [Admin\NotificationController::class, 'markAllRead'])->name('notifications.read');

    Route::middleware('perm:products')->group(function () {
        // Excel import — declared BEFORE the resource so /products/import isn't
        // captured by the /products/{product} wildcard.
        Route::get('products/import', [Admin\ProductImportController::class, 'form'])->name('products.import.form');
        Route::get('products/import/template', [Admin\ProductImportController::class, 'template'])->name('products.import.template');
        Route::post('products/import', [Admin\ProductImportController::class, 'import'])->name('products.import');
        Route::resource('products', Admin\ProductController::class);
    });
    Route::middleware('perm:categories')->group(function () {
        Route::resource('categories', Admin\CategoryController::class)->except('show');
    });
    Route::middleware('perm:pixels')->group(function () {
        Route::resource('pixels', Admin\PixelController::class)->except('show');
    });

    // Clients + debt ledger
    Route::middleware('perm:clients')->group(function () {
        Route::get('clients/pricelist.pdf', [Admin\ClientController::class, 'priceList'])->name('clients.pricelist');
        Route::resource('clients', Admin\ClientController::class);
        Route::post('clients/{client}/transactions', [Admin\ClientController::class, 'addTransaction'])->name('clients.transactions.store');
    });

    // Lost / broken inventory incidents
    Route::middleware('perm:incidents')->group(function () {
        Route::resource('incidents', Admin\IncidentController::class)->only(['index', 'create', 'store', 'destroy']);
    });

    // Suppliers & stock receiving (purchasing)
    Route::middleware('perm:purchasing')->group(function () {
        Route::resource('suppliers', Admin\SupplierController::class)->except('show');
        Route::get('receipts/{receipt}/document', [Admin\StockReceiptController::class, 'document'])->name('receipts.document');
        Route::post('receipts/{receipt}/receive', [Admin\StockReceiptController::class, 'receive'])->name('receipts.receive');
        Route::resource('receipts', Admin\StockReceiptController::class);
    });

    // Social publishing (real posts)
    Route::middleware('perm:social')->group(function () {
        Route::get('social', [Admin\SocialController::class, 'index'])->name('social.index');
        Route::post('social/publish', [Admin\SocialController::class, 'publish'])->name('social.publish');
    });

    // Team / staff — requires the "users" permission.
    Route::middleware('perm:users')->group(function () {
        Route::resource('users', Admin\UserController::class)->except('show');
    });

    Route::middleware('perm:orders')->group(function () {
        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
        Route::get('orders/{order}/slip', [Admin\OrderController::class, 'slip'])->name('orders.slip');
        Route::get('orders/{order}/noest-label', [Admin\OrderController::class, 'noestLabel'])->name('orders.noest.label');
        Route::patch('orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('orders/{order}/dispatch', [Admin\OrderController::class, 'dispatch'])->name('orders.dispatch');
        Route::post('orders/{order}/validate', [Admin\OrderController::class, 'validateShipment'])->name('orders.validate');
        Route::post('orders/{order}/refund', [Admin\OrderController::class, 'refund'])->name('orders.refund');
    });

    Route::middleware('perm:wilayas')->group(function () {
        Route::get('wilayas', [Admin\WilayaController::class, 'index'])->name('wilayas.index');
        Route::patch('wilayas', [Admin\WilayaController::class, 'update'])->name('wilayas.update');
    });

    Route::middleware('perm:settings')->group(function () {
        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::patch('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/telegram-test', [Admin\SettingController::class, 'telegramTest'])->name('settings.telegram.test');
        // Reset stays restricted to full administrators (checked in the controller).
        Route::post('settings/reset-data', [Admin\SettingController::class, 'resetData'])->name('settings.reset');
    });
});
