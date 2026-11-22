<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantSetupController extends Controller
{
    /**
     * Show the one-time restaurant setup form.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->restaurantUser($request);

        if ($user->restaurant) {
            return redirect()->route('restaurant.dashboard');
        }

        if (Restaurant::single()) {
            return redirect()
                ->route('restaurant.profile')
                ->with('error', 'This system already serves its restaurant. Ask an administrator to link your account.');
        }

        return Inertia::render('restaurant/Setup');
    }

    /**
     * Create the single restaurant this deployment serves.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $this->restaurantUser($request);

        if ($user->restaurant || Restaurant::single()) {
            return redirect()
                ->route('restaurant.dashboard')
                ->with('error', 'This system already serves its restaurant.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', 'unique:restaurants,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:300'],
            'city' => ['required', 'string', 'max:100'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'minimum_order' => ['nullable', 'numeric', 'min:0'],
            'estimated_delivery_time' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        Restaurant::create([
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'phone' => $validated['phone'],
            'email' => $user->email,
            'address' => $validated['address'],
            'city' => $validated['city'],
            'delivery_fee' => $validated['delivery_fee'] ?? config('alabiq.default_delivery_fee'),
            'minimum_order' => $validated['minimum_order'] ?? 0,
            'estimated_delivery_time' => $validated['estimated_delivery_time'] ?? 30,
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
            'approved_at' => now(),
        ]);

        return redirect()
            ->route('restaurant.dashboard')
            ->with('success', 'Your restaurant is ready. Start by adding menu items.');
    }

    /**
     * Resolve the authenticated restaurant user.
     */
    private function restaurantUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasRole(User::ROLE_RESTAURANT), 403);

        return $user;
    }
}
