<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantProfileController extends Controller
{
    public function __construct(
        private LocationService $locationService
    ) {}

    /**
     * Show the authenticated owner's restaurant profile.
     */
    public function show(Request $request): Response
    {
        $restaurant = $this->ownedRestaurant($request);
        $this->authorize('view', $restaurant);

        return Inertia::render('restaurant/Profile', [
            'profile' => $this->profilePayload($restaurant),
        ]);
    }

    /**
     * Update the main restaurant address and map location.
     */
    public function update(Request $request): RedirectResponse
    {
        $restaurant = $this->ownedRestaurant($request);
        $this->authorize('update', $restaurant);

        $validated = $request->validate([
            'address' => ['required', 'string', 'max:300'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ]);

        $restaurant->update($validated);

        return to_route('restaurant.profile')
            ->with('success', 'Restaurant address and map location updated.');
    }

    /**
     * Geocode the restaurant's free-text address.
     */
    public function geocode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        $coordinates = $this->locationService->geocode($validated['q']);

        if (! $coordinates) {
            return response()->json([
                'message' => 'No coordinates found for the provided address.',
            ], 404);
        }

        [$lng, $lat] = $coordinates;

        return response()->json([
            'data' => [
                'latitude' => $lat,
                'longitude' => $lng,
            ],
        ]);
    }

    /**
     * Reverse geocode a pinned restaurant location into address fields.
     */
    public function reverseGeocode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $details = $this->locationService->reverseGeocodeDetails(
            (float) $validated['lat'],
            (float) $validated['lng']
        );

        if (! $details) {
            return response()->json([
                'message' => 'Could not resolve an address for these coordinates.',
            ], 404);
        }

        return response()->json(['data' => $details]);
    }

    /**
     * Resolve the restaurant owned by the authenticated account.
     */
    private function ownedRestaurant(Request $request): Restaurant
    {
        $restaurant = $request->user()?->restaurant;
        abort_if(! $restaurant, 404, 'Restaurant not found');

        return $restaurant;
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(Restaurant $restaurant): array
    {
        return [
            'id' => $restaurant->id,
            'address' => $restaurant->address,
            'city' => $restaurant->city,
            'district' => $restaurant->district,
            'postal_code' => $restaurant->postal_code,
            'latitude' => $restaurant->latitude === null ? null : (float) $restaurant->latitude,
            'longitude' => $restaurant->longitude === null ? null : (float) $restaurant->longitude,
        ];
    }
}
