<?php

use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\RiderApprovalController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\SettlementController;
use App\Http\Controllers\Api\Admin\StatsController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Customer\CustomerAddressController;
use App\Http\Controllers\Api\Marketplace\CartController;
use App\Http\Controllers\Api\Order\DispatchController;
use App\Http\Controllers\Api\Order\OrderController;
use App\Http\Controllers\Api\Order\OrderTrackingController;
use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Payment\TapPaymentsWebhookController;
use App\Http\Controllers\Api\Restaurant\ReportsController as RestaurantReportsController;
use App\Http\Controllers\Api\Restaurant\RestaurantMenuController;
use App\Http\Controllers\Api\Restaurant\RestaurantOrderController;
use App\Http\Controllers\Api\Restaurant\RestaurantSettingsController;
use App\Http\Controllers\Api\Restaurant\RestaurantStorefrontController;
use App\Http\Controllers\Api\Rider\ReportsController as RiderReportsController;
use App\Http\Controllers\Api\Rider\RiderController;
use Illuminate\Support\Facades\Route;

// Public storefront API — one deployment serves one restaurant, so there is
// no restaurant directory/search; guests go straight to the storefront.
Route::prefix('marketplace')->name('marketplace.')->group(function () {
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
    Route::get('/addresses/geocode', [CustomerAddressController::class, 'geocode'])->name('addresses.geocode');
    Route::get('/addresses/reverse-geocode', [CustomerAddressController::class, 'reverseGeocode'])->name('addresses.reverse-geocode');
    Route::apiResource('addresses', CustomerAddressController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
});

// Protected Order API routes (require authentication)
Route::middleware('auth:sanctum')->prefix('orders')->name('orders.')->group(function () {
    Route::post('/checkout', [OrderController::class, 'store'])->name('checkout');
    Route::get('/', [OrderController::class, 'index'])->name('index');
    Route::get('/{order}', [OrderController::class, 'show'])->name('show');
    Route::patch('/{order}/status', [OrderController::class, 'updateStatus'])->name('update-status');
    Route::post('/{order}/assign-rider', [DispatchController::class, 'assignRider'])->name('assign-rider');
    Route::get('/{order}/tracking', [OrderTrackingController::class, 'show'])->name('tracking');
});

// Protected Rider API routes (require authentication)
Route::middleware('auth:sanctum')->prefix('rider')->name('rider.')->group(function () {
    Route::put('/availability', [RiderController::class, 'updateAvailability'])->name('availability');
    Route::post('/location', [RiderController::class, 'updateLocation'])->name('location');
    Route::get('/assignments', [RiderController::class, 'assignments'])->name('assignments');
    Route::post('/assignments/{assignment}/accept', [RiderController::class, 'accept'])->name('assignments.accept');
    Route::post('/assignments/{assignment}/reject', [RiderController::class, 'reject'])->name('assignments.reject');
    Route::get('/reports/earnings', [RiderReportsController::class, 'earnings'])->name('reports.earnings');
    Route::get('/reports/deliveries', [RiderReportsController::class, 'deliveries'])->name('reports.deliveries');
});

// Protected Restaurant reporting routes
Route::middleware(['auth:sanctum', 'role:restaurant'])->prefix('restaurant')->name('restaurant.')->group(function () {
    Route::get('/settings', [RestaurantSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/branches/{branch}', [RestaurantSettingsController::class, 'updateBranch'])->name('settings.branches.update');
    Route::get('/orders', [RestaurantOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [RestaurantOrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/available-riders', [RestaurantOrderController::class, 'availableRiders'])->name('orders.available-riders');
    Route::get('/menu', [RestaurantMenuController::class, 'index'])->name('menu.index');
    Route::post('/menu', [RestaurantMenuController::class, 'store'])->name('menu.store');
    Route::get('/menu/{menuItem}', [RestaurantMenuController::class, 'show'])->name('menu.show');
    Route::put('/menu/{menuItem}', [RestaurantMenuController::class, 'update'])->name('menu.update');
    Route::delete('/menu/{menuItem}', [RestaurantMenuController::class, 'destroy'])->name('menu.destroy');

    // Menu categories
    Route::post('/menu/categories', [RestaurantMenuController::class, 'storeCategory'])->name('menu.categories.store');
    Route::put('/menu/categories/{category}', [RestaurantMenuController::class, 'updateCategory'])->name('menu.categories.update');
    Route::delete('/menu/categories/{category}', [RestaurantMenuController::class, 'destroyCategory'])->name('menu.categories.destroy');
    Route::get('/reports/sales', [RestaurantReportsController::class, 'sales'])->name('reports.sales');
    Route::get('/settlements', [SettlementController::class, 'own'])->name('settlements.own');
});

// Super Admin API routes (require authentication + super_admin role)
Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/stats', [StatsController::class, 'index'])->name('stats');
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.role');
    Route::get('/riders', [RiderApprovalController::class, 'index'])->name('riders.index');
    Route::patch('/riders/{rider}/approval', [RiderApprovalController::class, 'updateApproval'])->name('riders.approval');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/settlements', [SettlementController::class, 'index'])->name('settlements.index');
    Route::post('/settlements/generate', [SettlementController::class, 'generate'])->name('settlements.generate');
    Route::post('/settlements/{settlement}/pay', [SettlementController::class, 'pay'])->name('settlements.pay');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs');
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
