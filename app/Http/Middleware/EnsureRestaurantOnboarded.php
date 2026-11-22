<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRestaurantOnboarded
{
    /**
     * Ensure the authenticated restaurant user has completed setup.
     *
     * One deployment serves one restaurant; until the user has created it,
     * every restaurant dashboard page redirects to onboarding.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->restaurant) {
            return redirect()->route('restaurant.setup');
        }

        return $next($request);
    }
}
