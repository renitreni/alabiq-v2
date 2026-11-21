<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantSettlement;
use App\Services\SettlementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettlementController extends Controller
{
    public function __construct(
        private SettlementService $settlementService
    ) {}

    /**
     * All settlements (admin), filterable by status and restaurant.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RestaurantSettlement::class);

        $settlements = RestaurantSettlement::query()
            ->with('restaurant:id,name')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('restaurant_id'), fn ($q, $id) => $q->where('restaurant_id', $id))
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json(['data' => $settlements]);
    }

    /**
     * The authenticated restaurant's own settlements.
     */
    public function own(): JsonResponse
    {
        $restaurant = Auth::user()->restaurant;

        if (! $restaurant) {
            abort(403, 'No restaurant linked to this account.');
        }

        $settlements = RestaurantSettlement::query()
            ->where('restaurant_id', $restaurant->id)
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json(['data' => $settlements]);
    }

    /**
     * Generate a settlement for a restaurant and period (idempotent).
     */
    public function generate(Request $request): JsonResponse
    {
        $this->authorize('create', RestaurantSettlement::class);

        $validated = $request->validate([
            'restaurant_id' => 'required|integer|exists:restaurants,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $restaurant = Restaurant::findOrFail($validated['restaurant_id']);

        try {
            $settlement = $this->settlementService->generate(
                $restaurant,
                Carbon::parse($validated['period_start']),
                Carbon::parse($validated['period_end']),
                Auth::user(),
                $request->ip()
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $settlement], 201);
    }

    /**
     * Pay a pending settlement (balanced ledger journals per order).
     */
    public function pay(Request $request, RestaurantSettlement $settlement): JsonResponse
    {
        $this->authorize('pay', $settlement);

        try {
            $settlement = $this->settlementService->markPaid($settlement, Auth::user(), $request->ip());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $settlement]);
    }
}
