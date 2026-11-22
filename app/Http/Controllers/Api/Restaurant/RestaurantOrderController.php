<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DispatchService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RestaurantOrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private DispatchService $dispatchService
    ) {}

    /**
     * List orders scoped to the authenticated restaurant.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            abort(403, 'No restaurant linked to this account.');
        }

        $validated = $request->validate([
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    Order::STATUS_PENDING_PAYMENT,
                    Order::STATUS_PLACED,
                    Order::STATUS_ACCEPTED,
                    Order::STATUS_PREPARING,
                    Order::STATUS_READY,
                    Order::STATUS_RIDER_ASSIGNED,
                    Order::STATUS_PICKED_UP,
                    Order::STATUS_ON_THE_WAY,
                    Order::STATUS_DELIVERED,
                    Order::STATUS_CANCELLED,
                    Order::STATUS_REFUNDED,
                ]),
            ],
        ]);

        return response()->json([
            'data' => $this->orderService->getRestaurantOrders(
                $restaurant->id,
                $validated['status'] ?? null
            ),
        ]);
    }

    /**
     * Show one order after confirming it belongs to the restaurant.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant || $order->restaurant_id !== $restaurant->id) {
            abort(404, 'Order not found.');
        }

        $order->load(['items.menuItem', 'branch', 'user', 'customerAddress', 'statusHistories', 'assignments.rider.user:id,name']);

        return response()->json([
            'data' => $order,
        ]);
    }

    /**
     * List riders that can be dispatched for this order, nearest to the
     * order's branch first. Empty when the order cannot currently be
     * dispatched (anything but ready / rider_assigned after a rejection).
     */
    public function availableRiders(Request $request, Order $order): JsonResponse
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant || $order->restaurant_id !== $restaurant->id) {
            abort(404, 'Order not found.');
        }

        if (! in_array($order->status, [Order::STATUS_READY, Order::STATUS_RIDER_ASSIGNED], true) || ! $order->branch) {
            return response()->json(['data' => []]);
        }

        $riders = collect($this->dispatchService->nearestAvailable($order->branch))
            ->map(fn (array $entry) => [
                'id' => $entry['rider']->id,
                'name' => $entry['rider']->user?->name,
                'vehicle_type' => $entry['rider']->vehicle_type,
                'rating' => $entry['rider']->rating,
                'total_deliveries' => $entry['rider']->total_deliveries,
                'distance_km' => $entry['distance_km'] === PHP_FLOAT_MAX ? null : round($entry['distance_km'], 2),
            ])
            ->values();

        return response()->json([
            'data' => $riders,
        ]);
    }
}
