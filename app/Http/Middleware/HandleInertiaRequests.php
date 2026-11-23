<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use App\Services\CartService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $cartService = new CartService;

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar' => $user->avatar,
                    'email_verified_at' => $user->email_verified_at?->toISOString(),
                    'created_at' => $user->created_at?->toISOString(),
                    'restaurant' => $user->restaurant ? [
                        'id' => $user->restaurant->id,
                        'name' => $user->restaurant->name,
                        'slug' => $user->restaurant->slug,
                        'logo' => $user->restaurant->logo,
                    ] : null,
                    'rider' => $user->rider ? [
                        'id' => $user->rider->id,
                        'is_online' => $user->rider->is_online,
                        'vehicle_type' => $user->rider->vehicle_type,
                    ] : null,
                ] : null,
            ],
            'restaurant' => [
                'menuUrl' => ($r = Restaurant::single()) ? route('restaurants.show', ['slug' => $r->slug]) : null,
            ],
            'cart' => [
                'item_count' => $cartService->getItemCount(),
                'subtotal' => $cartService->getSubtotal(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app' => [
                'name' => config('app.name'),
                'locale' => app()->getLocale(),
                'timezone' => config('app.timezone'),
                'currency' => config('alabiq.currency', 'SAR'),
                'mapbox_token' => config('alabiq.mapbox.token'),
                'map_provider' => config('alabiq.map_provider', 'free'),
            ],
        ]);
    }
}
