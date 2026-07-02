<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
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
// Admin auth
// ---------------------------------------------------------------------------
Route::get('/admin/login', [LoginController::class, 'show'])->name('admin.login');
Route::post('/admin/login', [LoginController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [LoginController::class, 'logout'])->name('admin.logout');

// ---------------------------------------------------------------------------
// Admin dashboard (auth + admin)
// ---------------------------------------------------------------------------
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::resource('products', Admin\ProductController::class);
    Route::resource('categories', Admin\CategoryController::class)->except('show');
    Route::resource('pixels', Admin\PixelController::class)->except('show');

    // Team / staff — full administrators only
    Route::resource('users', Admin\UserController::class)
        ->except('show')->middleware('fulladmin');

    Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('orders/{order}/dispatch', [Admin\OrderController::class, 'dispatch'])->name('orders.dispatch');

    Route::get('wilayas', [Admin\WilayaController::class, 'index'])->name('wilayas.index');
    Route::patch('wilayas', [Admin\WilayaController::class, 'update'])->name('wilayas.update');

    Route::middleware('fulladmin')->group(function () {
        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::patch('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
    });
});
