<?php

use App\Http\Controllers\Api\Customer\CustomerAddressController;
use App\Http\Controllers\Api\Marketplace\CartController;
use App\Http\Controllers\Api\Marketplace\MarketplaceController;
use App\Http\Controllers\Api\Order\OrderController;
use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Payment\TapPaymentsWebhookController;
use App\Http\Controllers\Api\Restaurant\RestaurantStorefrontController;
use Illuminate\Support\Facades\Route;

// Public Marketplace API routes
Route::prefix('marketplace')->name('marketplace.')->group(function () {
    Route::get('/restaurants', [MarketplaceController::class, 'search'])->name('restaurants.search');
    Route::get('/restaurants/featured', [MarketplaceController::class, 'featured'])->name('restaurants.featured');
    Route::get('/restaurants/nearby', [MarketplaceController::class, 'nearby'])->name('restaurants.nearby');
    Route::get('/cuisines', [MarketplaceController::class, 'cuisines'])->name('cuisines');
    Route::get('/popular-items', [MarketplaceController::class, 'popularItems'])->name('popular-items');

    // Restaurant storefront
    Route::get('/restaurants/{slug}', [RestaurantStorefrontController::class, 'show'])->name('restaurants.show');
    Route::post('/restaurants/{slug}/check-coverage', [RestaurantStorefrontController::class, 'checkCoverage'])->name('restaurants.check-coverage');

    // Cart
    Route::get('/cart', [CartController::class, 'index'])->name('cart');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.add');
    Route::put('/cart/{itemKey}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{itemKey}', [CartController::class, 'destroy'])->name('cart.remove');
    Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
    Route::put('/cart/branch', [CartController::class, 'setBranch'])->name('cart.set-branch');
    Route::get('/cart/validate', [CartController::class, 'validateCheckout'])->name('cart.validate');
    Route::post('/cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');
});

// Protected customer routes (require authentication)
Route::middleware('auth:sanctum')->prefix('customer')->name('customer.')->group(function () {
    Route::patch('/addresses/{address}/default', [CustomerAddressController::class, 'setDefault'])->name('addresses.default');
    Route::apiResource('addresses', CustomerAddressController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
});

// Protected Order API routes (require authentication)
Route::middleware('auth:sanctum')->prefix('orders')->name('orders.')->group(function () {
    Route::post('/checkout', [OrderController::class, 'store'])->name('checkout');
    Route::get('/', [OrderController::class, 'index'])->name('index');
    Route::get('/{order}', [OrderController::class, 'show'])->name('show');
    Route::patch('/{order}/status', [OrderController::class, 'updateStatus'])->name('update-status');
});

// Payment routes (require authentication)
Route::middleware('auth:sanctum')->prefix('payments')->name('payments.')->group(function () {
    Route::post('/orders/{order}/initiate', [PaymentController::class, 'initiate'])->name('initiate');
    Route::get('/orders/{order}/status', [PaymentController::class, 'status'])->name('status');
    Route::post('/transactions/{transaction}/refund', [PaymentController::class, 'refund'])->name('transactions.refund');
});

// Tap Payments webhook (public, signature-validated)
Route::post('/payments/tap/webhook', [TapPaymentsWebhookController::class, 'handle'])->name('payments.tap.webhook');
Route::get('/payments/tap/return', [TapPaymentsWebhookController::class, 'return'])->name('payments.tap.return');

// Future: Protected customer/restaurant/rider/admin API routes will go here
