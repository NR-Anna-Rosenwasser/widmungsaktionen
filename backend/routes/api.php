<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\StorefrontController;
use Illuminate\Support\Facades\Route;

// Public storefront API, consumed by the Nuxt server (proxied under the storefront's own origin).
Route::middleware('throttle:storefront')->group(function () {
    Route::get('resolve', [StorefrontController::class, 'resolve']);
    Route::get('tenants', [StorefrontController::class, 'index']);
    Route::get('tenants/{tenant:slug}', [StorefrontController::class, 'show']);

    Route::post('tenants/{tenant:slug}/orders', [OrderController::class, 'store']);
    Route::post('tenants/{tenant:slug}/orders/lookup', [OrderController::class, 'lookup'])->middleware('throttle:order-lookup');
    Route::get('tenants/{tenant:slug}/orders/{token}', [OrderController::class, 'show']);
    Route::patch('tenants/{tenant:slug}/orders/{token}', [OrderController::class, 'update']);
    Route::post('tenants/{tenant:slug}/orders/{token}/submit', [OrderController::class, 'submit']);
});
