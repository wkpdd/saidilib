<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff app API (v1) — bearer-token auth, RBAC enforced per section
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    Route::post('login', [Api\AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('api.token')->group(function () {
        Route::get('me', [Api\AuthController::class, 'me']);
        Route::post('logout', [Api\AuthController::class, 'logout']);
        Route::post('fcm-token', [Api\AuthController::class, 'fcmToken']);

        Route::get('dashboard', [Api\DashboardController::class, 'index']);

        Route::get('notifications', [Api\NotificationController::class, 'index']);
        Route::post('notifications/read', [Api\NotificationController::class, 'markAllRead']);

        // Reference lists for pickers (any authenticated staff).
        Route::get('meta/categories', [Api\MetaController::class, 'categories']);
        Route::get('meta/wilayas', [Api\MetaController::class, 'wilayas']);

        Route::middleware('perm:orders')->group(function () {
            Route::get('orders', [Api\OrderController::class, 'index']);
            Route::post('orders', [Api\OrderController::class, 'store']);
            Route::get('orders/{order}', [Api\OrderController::class, 'show']);
            Route::patch('orders/{order}/status', [Api\OrderController::class, 'updateStatus']);
            Route::post('orders/{order}/prices', [Api\OrderController::class, 'editPrices']);
            Route::post('orders/{order}/dispatch', [Api\OrderController::class, 'dispatchOrder']);
            Route::post('orders/{order}/refund', [Api\OrderController::class, 'refund']);
        });

        Route::middleware('perm:products')->group(function () {
            Route::get('products/lookup', [Api\ProductController::class, 'lookup']);
            Route::get('products', [Api\ProductController::class, 'index']);
            Route::post('products', [Api\ProductController::class, 'store']);
            Route::get('products/{product}', [Api\ProductController::class, 'show']);
            Route::patch('products/{product}', [Api\ProductController::class, 'quickUpdate']);
            Route::put('products/{product}', [Api\ProductController::class, 'fullUpdate']);
            Route::post('products/{product}/images', [Api\ProductController::class, 'storeImage']);
            Route::delete('products/{product}/images/{image}', [Api\ProductController::class, 'deleteImage']);
            Route::post('products/{product}/images/{image}/main', [Api\ProductController::class, 'setMainImage']);
            Route::post('products/{product}/images/{image}/rotate', [Api\ProductController::class, 'rotateImage']);
        });

        Route::middleware('perm:purchasing')->group(function () {
            Route::get('meta/suppliers', [Api\MetaController::class, 'suppliers']);
            Route::get('receipts', [Api\ReceiptController::class, 'index']);
            Route::post('receipts', [Api\ReceiptController::class, 'store']);
        });

        Route::middleware('perm:clients')->group(function () {
            Route::get('clients', [Api\ClientController::class, 'index']);
            Route::get('clients/{client}', [Api\ClientController::class, 'show']);
            Route::post('clients/{client}/transactions', [Api\ClientController::class, 'addTransaction']);
        });
    });
});
