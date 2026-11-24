<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Services\AuditLogService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RestaurantSettingsController extends Controller
{
    public function __construct(
        private SettingsService $settingsService,
        private AuditLogService $auditLogService
    ) {}

    /**
     * Aggregate restaurant settings for the operations page.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->ownedRestaurant($request);
        abort_if(! $restaurant, 404, 'Restaurant not found');

        return response()->json([
            'data' => [
                'restaurant' => [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                    'accepts_orders' => $restaurant->accepts_orders,
                ],
                'branches' => $restaurant->branches()
                    ->orderBy('id')
                    ->get()
                    ->map(fn (RestaurantBranch $branch) => $this->branchPayload($branch))
                    ->values(),
                'payments' => [
                    'cod_available' => (bool) $this->settingsService->get('cod_enabled', config('alabiq.cod_enabled')),
                    'online_payments_available' => (bool) $this->settingsService->get('online_payments_enabled', config('alabiq.online_payments_enabled')),
                ],
            ],
        ]);
    }

    /**
     * Create a new branch for the authenticated restaurant.
     */
    public function storeBranch(Request $request): JsonResponse
    {
        $restaurant = $this->ownedRestaurant($request);
        abort_if(! $restaurant, 404, 'Restaurant not found');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:restaurant_branches,slug'],
            'address' => ['required', 'string', 'max:300'],
            'phone' => ['nullable', 'string', 'max:30'],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'minimum_order' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'estimated_delivery_time' => ['nullable', 'integer', 'min:1', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'accepts_orders' => ['nullable', 'boolean'],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']) ?: 'branch';
        $base = $slug;
        $suffix = 2;

        while (RestaurantBranch::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        $branch = RestaurantBranch::create([
            'restaurant_id' => $restaurant->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'address' => $validated['address'],
            'phone' => $validated['phone'] ?? null,
            'latitude' => 0.0,
            'longitude' => 0.0,
            'opens_at' => $validated['opens_at'] ?? '08:00',
            'closes_at' => $validated['closes_at'] ?? '23:00',
            'delivery_fee' => $validated['delivery_fee'] ?? 0,
            'minimum_order' => $validated['minimum_order'] ?? 0,
            'estimated_delivery_time' => $validated['estimated_delivery_time'] ?? 30,
            'is_active' => $request->boolean('is_active', true),
            'accepts_orders' => $request->boolean('accepts_orders', true),
        ]);

        $this->auditLogService->record(
            Auth::user(),
            'restaurant.branch_created',
            $branch,
            [],
            $this->auditableValues($branch),
            $request->ip()
        );

        return response()->json(['data' => $this->branchPayload($branch)], 201);
    }

    /**
     * Update operational settings for a branch owned by the restaurant user.
     */
    public function updateBranch(Request $request, RestaurantBranch $branch): JsonResponse
    {
        $restaurant = $this->ownedRestaurant($request);
        abort_if(! $restaurant || $branch->restaurant_id !== $restaurant->id, 404, 'Branch not found');

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'accepts_orders' => ['required', 'boolean'],
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i'],
            'delivery_fee' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'minimum_order' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'estimated_delivery_time' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $old = $this->auditableValues($branch);
        $branch->update($validated);

        $new = $this->auditableValues($branch->refresh());
        $this->auditLogService->record(
            Auth::user(),
            'restaurant.branch_settings_updated',
            $branch,
            $old,
            $new,
            $request->ip()
        );

        return response()->json(['data' => $this->branchPayload($branch)]);
    }

    /**
     * Restaurant owned by the authenticated account, when present.
     */
    private function ownedRestaurant(Request $request): ?Restaurant
    {
        return $request->user()?->restaurant;
    }

    /**
     * API representation of a branch and its operational settings.
     *
     * @return array<string, mixed>
     */
    private function branchPayload(RestaurantBranch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'slug' => $branch->slug,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'is_active' => (bool) $branch->is_active,
            'accepts_orders' => (bool) $branch->accepts_orders,
            'opens_at' => $branch->opens_at?->format('H:i') ?? '00:00',
            'closes_at' => $branch->closes_at?->format('H:i') ?? '23:59',
            'delivery_fee' => (float) $branch->delivery_fee,
            'minimum_order' => (float) $branch->minimum_order,
            'estimated_delivery_time' => (int) $branch->estimated_delivery_time,
            'is_open' => $branch->isOpen(),
        ];
    }

    /**
     * Persisted fields included in the settings audit record.
     *
     * @return array<string, mixed>
     */
    private function auditableValues(RestaurantBranch $branch): array
    {
        return [
            'is_active' => (bool) $branch->is_active,
            'accepts_orders' => (bool) $branch->accepts_orders,
            'opens_at' => $branch->opens_at?->format('H:i') ?? '00:00',
            'closes_at' => $branch->closes_at?->format('H:i') ?? '23:59',
            'delivery_fee' => (float) $branch->delivery_fee,
            'minimum_order' => (float) $branch->minimum_order,
            'estimated_delivery_time' => (int) $branch->estimated_delivery_time,
        ];
    }
}
