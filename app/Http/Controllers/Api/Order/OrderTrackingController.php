<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use App\Models\DeliveryTrackingEvent;
use App\Models\Order;
use App\Services\EtaService;
use App\Services\RiderLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class OrderTrackingController extends Controller
{
    public function __construct(
        private EtaService $etaService,
    ) {}

    /**
     * Live tracking payload for an order: status, latest rider position,
     * recent tracking events and ETA to the customer address.
     *
     * Authorized for the customer who placed it, the owning restaurant,
     * or a super admin (OrderPolicy::view).
     */
    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $assignment = $order->deliveryAssignment?->loadMissing('rider');
        $rider = $assignment?->rider;
        $location = null;
        $etaMinutes = null;

        if ($rider) {
            $location = Cache::get(RiderLocationService::cacheKey($rider->id))
                ?? $rider->latestLocation?->only(['latitude', 'longitude', 'recorded_at']);

            $address = $order->customerAddress;

            if ($address && $address->latitude !== null && $address->longitude !== null) {
                $etaMinutes = $this->etaService->estimateForRider(
                    $rider,
                    (float) $address->latitude,
                    (float) $address->longitude
                );
            }
        }

        $order->loadMissing(['branch', 'restaurant']);
        $origin = $order->branch ?? $order->restaurant;
        $restaurantLocation = $origin && $origin->latitude !== null && $origin->longitude !== null
            ? [
                'name' => $order->restaurant?->name,
                'latitude' => (float) $origin->latitude,
                'longitude' => (float) $origin->longitude,
            ]
            : null;

        $events = DeliveryTrackingEvent::query()
            ->where('order_id', $order->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status,
                'assignment' => $assignment?->only(['id', 'status', 'offered_at', 'accepted_at', 'distance_km', 'delivery_fee']),
                'rider' => $rider
                    ? $rider->only(['id', 'vehicle_type', 'rating']) + ['name' => $rider->user?->name]
                    : null,
                'location' => $location,
                'restaurant' => $restaurantLocation,
                'eta_minutes' => $etaMinutes,
                'events' => $events,
            ],
        ]);
    }
}
