<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerAddressController extends Controller
{
    public function __construct(
        private LocationService $locationService
    ) {}
    /**
     * List the authenticated user's saved delivery addresses.
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->customerAddresses()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $addresses]);
    }

    /**
     * Save a new delivery address. The first address becomes the default;
     * setting is_default on a later address demotes the previous default.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateAddress($request);

        $address = DB::transaction(function () use ($request, $validated) {
            $isFirst = ! $request->user()->customerAddresses()->where('is_active', true)->exists();

            if (($validated['is_default'] ?? false) || $isFirst) {
                $request->user()->customerAddresses()->update(['is_default' => false]);
                $validated['is_default'] = true;
            }

            return $request->user()->customerAddresses()->create($validated);
        });

        return response()->json(['data' => $address->refresh()], 201);
    }

    /**
     * Show a single address (owner or super admin only).
     */
    public function show(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->authorize('view', $address);

        return response()->json(['data' => $address]);
    }

    /**
     * Update an address (owner or super admin only).
     */
    public function update(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->authorize('update', $address);

        $validated = $this->validateAddress($request);

        DB::transaction(function () use ($request, $address, $validated) {
            if ($validated['is_default'] ?? false) {
                $request->user()->customerAddresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update($validated);
        });

        return response()->json(['data' => $address->refresh()]);
    }

    /**
     * Soft-delete an address by marking it inactive (keeps order history).
     */
    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->authorize('delete', $address);

        $address->update(['is_active' => false]);

        return response()->json(['message' => 'Address removed']);
    }

    /**
     * Mark an address as the default delivery address.
     */
    public function setDefault(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->authorize('update', $address);

        DB::transaction(function () use ($request, $address) {
            $request->user()->customerAddresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);

            $address->update(['is_default' => true]);
        });

        return response()->json(['data' => $address->refresh()]);
    }

    /**
     * Geocode a free-text address into coordinates (free OSM/Nominatim by default).
     */
    public function geocode(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        $coordinates = $this->locationService->geocode($request->string('q'));

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
                'place_id' => null,
            ],
        ]);
    }

    /**
     * Reverse geocode coordinates into a display address.
     */
    public function reverseGeocode(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $address = $this->locationService->reverseGeocode(
            $request->float('lat'),
            $request->float('lng')
        );

        if (! $address) {
            return response()->json([
                'message' => 'Could not resolve an address for these coordinates.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'address' => $address,
                'street' => null,
                'city' => null,
                'district' => null,
                'postal_code' => null,
            ],
        ]);
    }

    /**
     * Validation rules shared by store/update.
     *
     * Coordinates are optional (manual pin arrives in Phase 5 with the
     * Mapbox integration); an address without them simply skips the
     * coverage check at checkout.
     */
    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:50'],
            'floor' => ['nullable', 'string', 'max:20'],
            'apartment' => ['nullable', 'string', 'max:20'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}
