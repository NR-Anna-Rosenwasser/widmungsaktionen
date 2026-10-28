<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\StorefrontController;
use App\Http\Controllers\StripeWebhookController;
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
    Route::post('tenants/{tenant:slug}/orders/{token}/pay', [OrderController::class, 'pay']);
    Route::post('tenants/{tenant:slug}/orders/{token}/test-payment', [OrderController::class, 'testPayment']);
    Route::get('tenants/{tenant:slug}/orders/{token}/invoice', [OrderController::class, 'invoice']);
    Route::get('tenants/{tenant:slug}/orders/{token}/no-reminders', [OrderController::class, 'optOut'])->name('orders.reminders.opt-out');
});

Route::post('webhooks/stripe/{tenant:slug}', StripeWebhookController::class)->name('webhooks.stripe');
