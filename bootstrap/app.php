<?php

use App\Http\Middleware\EnsureRestaurantOnboarded;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Allow Sanctum to authenticate API requests via the session cookie (SPA mode),
        // matching the axios client which sends withCredentials + CSRF token.
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // Where the "guest" middleware sends already-authenticated users
        // (mirrors AuthenticatedSessionController::dashboardFor). Falls back to
        // home for guests without a verified email, since dashboard routes
        // require the "verified" middleware.
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if (! $user instanceof User || ! $user->email_verified_at) {
                return '/';
            }

            return match ($user->role) {
                User::ROLE_RESTAURANT => '/restaurant/dashboard',
                User::ROLE_RIDER => '/rider/dashboard',
                User::ROLE_SUPER_ADMIN => '/admin/dashboard',
                default => '/customer/dashboard',
            };
        });

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'restaurant.onboarded' => EnsureRestaurantOnboarded::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
