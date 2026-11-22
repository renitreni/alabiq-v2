<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryTrackingEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\RestaurantBranch;
use App\Models\Rider;
use App\Models\User;

/**
 * Rider dispatch: assignment lifecycle on top of the order state machine.
 *
 * Invariants:
 *  - An order is dispatched from "ready"; after a rider rejection it stays
 *    "rider_assigned" and can only be re-dispatched once no active
 *    assignment remains.
 *  - Only online + active riders without an active assignment are eligible.
 *  - Rejecting an assignment does NOT roll the order back to ready; the
 *    restaurant must re-dispatch (order stays rider_assigned).
 */
class DispatchService
{
    public function __construct(
        private ?OrderService $orderService = null,
        private ?LocationService $locationService = null,
    ) {
        $this->orderService ??= new OrderService(new CartService);
        $this->locationService ??= new LocationService;
    }

    /**
     * Offer an order to a rider.
     *
     * @throws \DomainException When the order or rider is not dispatchable.
     */
    public function assignToOrder(Order $order, Rider $rider, ?User $actor = null): DeliveryAssignment
    {
        if (! in_array($order->status, [Order::STATUS_READY, Order::STATUS_RIDER_ASSIGNED], true)) {
            throw new \DomainException("Order must be ready for pickup before dispatch (current: {$order->status}).");
        }

        if ($order->status === Order::STATUS_RIDER_ASSIGNED
            && $order->assignments()->whereIn('status', DeliveryAssignment::ACTIVE_STATUSES)->exists()) {
            throw new \DomainException('Order already has an active rider assignment.');
        }

        if (! $rider->is_active || ! $rider->is_online) {
            throw new \DomainException('Rider must be online and active to receive assignments.');
        }

        if ($rider->assignments()->whereIn('status', DeliveryAssignment::ACTIVE_STATUSES)->exists()) {
            throw new \DomainException('Rider already has an active assignment.');
        }

        $branch = RestaurantBranch::find($order->branch_id);
        $distanceKm = null;

        if ($branch && $rider->latestLocation) {
            $distanceKm = round($this->locationService->haversineKm(
                (float) $rider->latestLocation->latitude,
                (float) $rider->latestLocation->longitude,
                (float) $branch->latitude,
                (float) $branch->longitude,
            ), 2);
        }

        $assignment = DeliveryAssignment::create([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => DeliveryAssignment::STATUS_ASSIGNED,
            'distance_km' => $distanceKm,
            'delivery_fee' => $branch?->delivery_fee,
            'offered_at' => now(),
        ]);

        $changedBy = $actor?->id ?? $order->restaurant?->owner_id ?? $order->user_id;

        if ($order->status === Order::STATUS_READY) {
            if (! $this->orderService->assignRider($order, $changedBy)) {
                throw new \DomainException('Order could not transition to rider_assigned.');
            }
        } else {
            // Re-dispatch after a rejection: status stays rider_assigned,
            // just record that a new rider was offered the order.
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $order->status,
                'to_status' => $order->status,
                'changed_by' => $changedBy,
                'changed_by_type' => 'restaurant',
                'note' => 'Re-dispatched to a new rider',
            ]);
        }

        DeliveryTrackingEvent::create([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'event' => 'assigned',
            'latitude' => $rider->latestLocation?->latitude,
            'longitude' => $rider->latestLocation?->longitude,
            'metadata' => ['assignment_id' => $assignment->id],
        ]);

        return $assignment;
    }

    /**
     * Rider accepts an offered assignment.
     */
    public function accept(DeliveryAssignment $assignment, Rider $rider): DeliveryAssignment
    {
        $this->assertOwnerAndStatus($assignment, $rider, DeliveryAssignment::STATUS_ASSIGNED);

        $assignment->update([
            'status' => DeliveryAssignment::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        DeliveryTrackingEvent::create([
            'order_id' => $assignment->order_id,
            'rider_id' => $rider->id,
            'event' => 'accepted',
            'metadata' => ['assignment_id' => $assignment->id],
        ]);

        return $assignment->refresh();
    }

    /**
     * Rider rejects an offered assignment; the order remains rider_assigned
     * and the restaurant must re-dispatch.
     */
    public function reject(DeliveryAssignment $assignment, Rider $rider, string $reason): DeliveryAssignment
    {
        $this->assertOwnerAndStatus($assignment, $rider, DeliveryAssignment::STATUS_ASSIGNED);

        $assignment->update([
            'status' => DeliveryAssignment::STATUS_REJECTED,
            'rejected_reason' => $reason,
        ]);

        DeliveryTrackingEvent::create([
            'order_id' => $assignment->order_id,
            'rider_id' => $rider->id,
            'event' => 'rejected',
            'metadata' => ['assignment_id' => $assignment->id, 'reason' => $reason],
        ]);

        return $assignment->refresh();
    }

    /**
     * Close an assignment once the rider delivered the order.
     */
    public function complete(DeliveryAssignment $assignment, Rider $rider): DeliveryAssignment
    {
        $this->assertOwnerAndStatus($assignment, $rider, DeliveryAssignment::STATUS_ACCEPTED);

        $assignment->update(['status' => DeliveryAssignment::STATUS_COMPLETED]);

        DeliveryTrackingEvent::create([
            'order_id' => $assignment->order_id,
            'rider_id' => $rider->id,
            'event' => 'completed',
            'metadata' => ['assignment_id' => $assignment->id],
        ]);

        return $assignment->refresh();
    }

    /**
     * Nearest available riders to a branch, closest first.
     *
     * @return array<int, array{rider: Rider, distance_km: float}>
     */
    public function nearestAvailable(RestaurantBranch $branch, int $limit = 5): array
    {
        $riders = Rider::availableForDispatch()
            ->with('latestLocation')
            ->get();

        $distances = $riders
            ->map(fn (Rider $rider) => [
                'rider' => $rider,
                'distance_km' => $rider->latestLocation
                    ? $this->locationService->haversineKm(
                        (float) $rider->latestLocation->latitude,
                        (float) $rider->latestLocation->longitude,
                        (float) $branch->latitude,
                        (float) $branch->longitude,
                    )
                    : PHP_FLOAT_MAX,
            ])
            ->sortBy('distance_km')
            ->take($limit)
            ->values();

        return $distances->all();
    }

    private function assertOwnerAndStatus(DeliveryAssignment $assignment, Rider $rider, string $expectedStatus): void
    {
        if ($assignment->rider_id !== $rider->id) {
            throw new \DomainException('Assignment belongs to a different rider.');
        }

        if ($assignment->status !== $expectedStatus) {
            throw new \DomainException("Assignment must be {$expectedStatus} (current: {$assignment->status}).");
        }
    }
}
