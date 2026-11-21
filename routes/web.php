<?php

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

// Public marketplace routes
Route::get('/', fn () => Inertia::render('marketplace/Home'))->name('home');
Route::get('/restaurants', fn () => Inertia::render('marketplace/Search'))->name('restaurants.index');
Route::get('/restaurants/{slug}', fn () => Inertia::render('marketplace/RestaurantShow'))->name('restaurants.show');
Route::get('/search', fn () => Inertia::render('marketplace/Search'))->name('search');

// Auth routes (will be handled by Laravel Breeze or custom)
Route::get('/login', fn () => Inertia::render('auth/Login'))->name('login');
Route::get('/register', fn () => Inertia::render('auth/Register'))->name('register');
Route::get('/forgot-password', fn () => Inertia::render('auth/ForgotPassword'))->name('password.request');
Route::get('/reset-password/{token}', fn () => Inertia::render('auth/ResetPassword'))->name('password.reset');

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
    Route::get('/dashboard', fn () => Inertia::render('restaurant/Dashboard'))->name('dashboard');
    Route::get('/orders', fn () => Inertia::render('restaurant/Orders'))->name('orders');
    Route::get('/orders/{order}', fn () => Inertia::render('restaurant/OrderShow'))->name('orders.show');
    Route::get('/menu', fn () => Inertia::render('restaurant/Menu'))->name('menu');
    Route::get('/menu/create', fn () => Inertia::render('restaurant/MenuCreate'))->name('menu.create');
    Route::get('/menu/{menuItem}/edit', fn () => Inertia::render('restaurant/MenuEdit'))->name('menu.edit');
    Route::get('/profile', fn () => Inertia::render('restaurant/Profile'))->name('profile');
    Route::get('/settings', fn () => Inertia::render('restaurant/Settings'))->name('settings');
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
    Route::get('/restaurants', fn () => Inertia::render('admin/Restaurants'))->name('restaurants');
    Route::get('/restaurants/{restaurant}', fn () => Inertia::render('admin/RestaurantShow'))->name('restaurants.show');
    Route::get('/riders', fn () => Inertia::render('admin/Riders'))->name('riders');
    Route::get('/riders/{rider}', fn () => Inertia::render('admin/RiderShow'))->name('riders.show');
    Route::get('/reports', fn () => Inertia::render('admin/Reports'))->name('reports');
    Route::get('/settings', fn () => Inertia::render('admin/Settings'))->name('settings');
    Route::get('/users', fn () => Inertia::render('admin/Users'))->name('users');
});

// Fallback for 404
Route::fallback(fn () => Inertia::render('shared/NotFound'))->name('fallback');
