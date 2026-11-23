<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Restaurant\RestaurantSetupController;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Public storefront — one deployment serves one restaurant, so guests land
// directly on its menu instead of a restaurant directory.
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route(match (auth()->user()->role) {
            User::ROLE_RESTAURANT => 'restaurant.dashboard',
            User::ROLE_RIDER => 'rider.dashboard',
            User::ROLE_SUPER_ADMIN => 'admin.dashboard',
            default => 'customer.dashboard',
        });
    }

    $restaurant = Restaurant::single();

    if ($restaurant) {
        return redirect()->route('restaurants.show', ['slug' => $restaurant->slug]);
    }

    return Inertia::render('marketplace/Home');
})->name('home');

Route::get('/restaurants', function () {
    $restaurant = Restaurant::single();

    if ($restaurant) {
        return redirect()->route('restaurants.show', ['slug' => $restaurant->slug]);
    }

    return redirect('/');
})->name('restaurants.index');

Route::get('/restaurants/{slug}', fn () => Inertia::render('marketplace/RestaurantShow'))->name('restaurants.show');

Route::get('/search', fn () => redirect('/'))->name('search');

// Auth routes
Route::get('/login', [AuthenticatedSessionController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('guest')->name('login.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/register', fn () => Inertia::render('auth/Register'))->middleware('guest')->name('register');
Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('guest');
Route::get('/forgot-password', fn () => Inertia::render('auth/ForgotPassword'))->middleware('guest')->name('password.request');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('guest')->name('password.email');
Route::get('/reset-password/{token}', fn (Request $request) => Inertia::render('auth/ResetPassword', [
    'token' => $request->route('token'),
    'email' => $request->input('email'),
]))->middleware('guest')->name('password.reset');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('guest')->name('password.update');

// Guest pages
Route::get('/how-it-works', fn () => Inertia::render('shared/HowItWorks'))->name('how-it-works');
Route::get('/about', fn () => Inertia::render('shared/About'))->name('about');
Route::get('/contact', fn () => Inertia::render('shared/Contact'))->name('contact');
Route::get('/privacy', fn () => Inertia::render('shared/Privacy'))->name('privacy');
Route::get('/terms', fn () => Inertia::render('shared/Terms'))->name('terms');

// Customer dashboard routes (protected by auth + customer role)
Route::middleware(['auth', 'verified', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('customer/Dashboard'))->name('dashboard');
    Route::get('/orders', fn () => Inertia::render('customer/Orders'))->name('orders');
    Route::get('/orders/{order}', fn () => Inertia::render('customer/OrderShow'))->name('orders.show');
    Route::get('/cart', fn () => Inertia::render('customer/Cart'))->name('cart');
    Route::get('/checkout', fn () => Inertia::render('customer/Checkout'))->name('checkout');
    Route::get('/addresses', fn () => Inertia::render('customer/Addresses'))->name('addresses');
    Route::get('/profile', fn () => Inertia::render('customer/Profile'))->name('profile');
});

// Restaurant dashboard routes (protected by auth + restaurant role)
Route::middleware(['auth', 'verified', 'role:restaurant'])->prefix('restaurant')->name('restaurant.')->group(function () {
    // One-time onboarding: create the single restaurant this system serves.
    Route::get('/setup', [RestaurantSetupController::class, 'create'])->name('setup');
    Route::post('/setup', [RestaurantSetupController::class, 'store'])->name('setup.store');

    Route::middleware('restaurant.onboarded')->group(function () {
        Route::get('/dashboard', fn () => Inertia::render('restaurant/Dashboard'))->name('dashboard');
        Route::get('/orders', fn () => Inertia::render('restaurant/Orders'))->name('orders');
        Route::get('/orders/{order}', fn () => Inertia::render('restaurant/OrderShow'))->name('order.show');
        Route::get('/menu', fn () => Inertia::render('restaurant/Menu'))->name('menu');
        Route::get('/menu/create', fn () => Inertia::render('restaurant/MenuCreate'))->name('menu.create');
        Route::get('/menu/{menuItem}/edit', fn () => Inertia::render('restaurant/MenuEdit'))->name('menu.edit');
        Route::get('/profile', fn () => Inertia::render('restaurant/Profile'))->name('profile');
        Route::get('/settings', fn () => Inertia::render('restaurant/Settings'))->name('settings');
    });
});

// Rider dashboard routes (protected by auth + rider role)
Route::middleware(['auth', 'verified', 'role:rider'])->prefix('rider')->name('rider.')->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('rider/Dashboard'))->name('dashboard');
    Route::get('/deliveries', fn () => Inertia::render('rider/Deliveries'))->name('deliveries');
    Route::get('/deliveries/{delivery}', fn () => Inertia::render('rider/DeliveryShow'))->name('deliveries.show');
    Route::get('/tracking/{delivery}', fn () => Inertia::render('rider/Tracking'))->name('tracking');
    Route::get('/earnings', fn () => Inertia::render('rider/Earnings'))->name('earnings');
    Route::get('/profile', fn () => Inertia::render('rider/Profile'))->name('profile');
});

// Super Admin dashboard routes (protected by auth + super_admin role)
Route::middleware(['auth', 'verified', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('admin/Dashboard'))->name('dashboard');
    Route::get('/riders', fn () => Inertia::render('admin/Riders'))->name('riders');
    Route::get('/riders/{rider}', fn () => Inertia::render('admin/RiderShow'))->name('riders.show');
    Route::get('/reports', fn () => Inertia::render('admin/Reports'))->name('reports');
    Route::get('/settings', fn () => Inertia::render('admin/Settings'))->name('settings');
    Route::get('/users', fn () => Inertia::render('admin/Users'))->name('users');
});

// Fallback for 404
Route::fallback(fn () => Inertia::render('shared/NotFound'))->name('fallback');
