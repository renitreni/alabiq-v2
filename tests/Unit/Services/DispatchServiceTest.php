<?php

namespace Tests\Unit\Services;

use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\RestaurantBranch;
use App\Models\Rider;
use App\Models\RiderLocation;
use App\Services\DispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private DispatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DispatchService;
    }

    public function test_assigns_ready_order_to_available_rider(): void
    {
        [$order, $branch] = $this->readyOrder();
        $rider = $this->onlineRiderNear($branch);

        $assignment = $this->service->assignToOrder($order, $rider);

        $this->assertSame(DeliveryAssignment::STATUS_ASSIGNED, $assignment->status);
        $this->assertSame($order->id, $assignment->order_id);
        $this->assertSame($rider->id, $assignment->rider_id);
        $this->assertSame(Order::STATUS_RIDER_ASSIGNED, $order->fresh()->status);
        $this->assertDatabaseHas('delivery_tracking_events', [
            'order_id' => $order->id,
            'event' => 'assigned',
        ]);
    }

    public function test_assignment_records_distance_from_rider_to_branch(): void
    {
        [$order, $branch] = $this->readyOrder();
        $rider = $this->onlineRiderNear($branch, 0.01, 0.01); // ~1.5 km away

        $assignment = $this->service->assignToOrder($order, $rider);

        $this->assertNotNull($assignment->distance_km);
        $this->assertGreaterThan(1.0, (float) $assignment->distance_km);
        $this->assertLessThan(2.0, (float) $assignment->distance_km);
        $this->assertSame((float) $branch->delivery_fee, (float) $assignment->delivery_fee);
    }

    public function test_refuses_order_not_in_ready_status(): void
    {
        $order = Order::factory()->placed()->create();
        $rider = Rider::factory()->online()->create();

        $this->expectException(\DomainException::class);
        $this->service->assignToOrder($order, $rider);
    }

    public function test_refuses_offline_rider(): void
    {
        [$order] = $this->readyOrder();
        $rider = Rider::factory()->offline()->create();

        $this->expectException(\DomainException::class);
        $this->service->assignToOrder($order, $rider);
    }

    public function test_refuses_rider_with_active_assignment(): void
    {
        [$order, $branch] = $this->readyOrder();
        $rider = $this->onlineRiderNear($branch);
        DeliveryAssignment::factory()->accepted()->create(['rider_id' => $rider->id]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already has an active assignment');
        $this->service->assignToOrder($order, $rider);
    }

    public function test_rejected_assignment_is_still_recorded(): void
    {
        [$order, $branch] = $this->readyOrder();
        $rider = $this->onlineRiderNear($branch);
        $assignment = $this->service->assignToOrder($order, $rider);

        $assignment = $this->service->reject($assignment, $rider, 'Too far away');

        $this->assertSame(DeliveryAssignment::STATUS_REJECTED, $assignment->status);
        $this->assertSame('Too far away', $assignment->rejected_reason);
        // Order stays rider_assigned — the restaurant must re-dispatch.
        $this->assertSame(Order::STATUS_RIDER_ASSIGNED, $order->fresh()->status);
        $this->assertDatabaseHas('delivery_tracking_events', ['event' => 'rejected']);
    }

    public function test_rider_accepts_assignment(): void
    {
        [$order, $branch] = $this->readyOrder();
        $rider = $this->onlineRiderNear($branch);
        $assignment = $this->service->assignToOrder($order, $rider);

        $assignment = $this->service->accept($assignment, $rider);

        $this->assertSame(DeliveryAssignment::STATUS_ACCEPTED, $assignment->status);
        $this->assertNotNull($assignment->accepted_at);
        $this->assertDatabaseHas('delivery_tracking_events', ['event' => 'accepted']);
    }

    public function test_rider_completes_accepted_assignment(): void
    {
        [$order, $branch] = $this->readyOrder();
        $rider = $this->onlineRiderNear($branch);
        $assignment = $this->service->assignToOrder($order, $rider);
        $assignment = $this->service->accept($assignment, $rider);

        $assignment = $this->service->complete($assignment, $rider);

        $this->assertSame(DeliveryAssignment::STATUS_COMPLETED, $assignment->status);
        $this->assertDatabaseHas('delivery_tracking_events', ['event' => 'completed']);
    }

    public function test_cannot_accept_assignment_of_another_rider(): void
    {
        [$order, $branch] = $this->readyOrder();
        $rider = $this->onlineRiderNear($branch);
        $other = Rider::factory()->online()->create();
        $assignment = $this->service->assignToOrder($order, $rider);

        $this->expectException(\DomainException::class);
        $this->service->accept($assignment, $other);
    }

    public function test_cannot_accept_twice(): void
    {
        [$order, $branch] = $this->readyOrder();
        $rider = $this->onlineRiderNear($branch);
        $assignment = $this->service->assignToOrder($order, $rider);
        $this->service->accept($assignment, $rider);

        $this->expectException(\DomainException::class);
        $this->service->accept($assignment, $rider);
    }

    public function test_cannot_reject_after_accepting(): void
    {
        [$order, $branch] = $this->readyOrder();
        $rider = $this->onlineRiderNear($branch);
        $assignment = $this->service->assignToOrder($order, $rider);
        $this->service->accept($assignment, $rider);

        $this->expectException(\DomainException::class);
        $this->service->reject($assignment, $rider, 'changed my mind');
    }

    public function test_nearest_available_orders_riders_by_distance(): void
    {
        [, $branch] = $this->readyOrder();

        $near = $this->onlineRiderNear($branch, 0.01, 0.01);
        $far = $this->onlineRiderNear($branch, 0.5, 0.5);
        Rider::factory()->online()->create();   // no location -> last
        Rider::factory()->offline()->create();  // not available at all

        $result = $this->service->nearestAvailable($branch);

        $this->assertCount(3, $result);
        $this->assertSame($near->id, $result[0]['rider']->id);
        $this->assertSame($far->id, $result[1]['rider']->id);
    }

    public function test_available_for_dispatch_scope_excludes_busy_riders(): void
    {
        [$order, $branch] = $this->readyOrder();
        $busy = $this->onlineRiderNear($branch);
        DeliveryAssignment::factory()->accepted()->create(['rider_id' => $busy->id]);
        $free = $this->onlineRiderNear($branch, 0.1, 0.1);

        $available = Rider::availableForDispatch()->pluck('id');

        $this->assertTrue($available->contains($free->id));
        $this->assertFalse($available->contains($busy->id));
    }

    /**
     * @return array{0: Order, 1: RestaurantBranch}
     */
    private function readyOrder(): array
    {
        $order = Order::factory()->ready()->create();
        $branch = RestaurantBranch::findOrFail($order->branch_id);

        return [$order, $branch];
    }

    private function onlineRiderNear(RestaurantBranch $branch, float $dLat = 0.0, float $dLng = 0.0): Rider
    {
        $rider = Rider::factory()->online()->create();
        RiderLocation::factory()->create([
            'rider_id' => $rider->id,
            'latitude' => (float) $branch->latitude + $dLat,
            'longitude' => (float) $branch->longitude + $dLng,
        ]);

        return $rider;
    }
}
