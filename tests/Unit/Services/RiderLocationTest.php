<?php

namespace Tests\Unit\Services;

use App\Events\RiderLocationUpdated;
use App\Models\DeliveryAssignment;
use App\Models\Rider;
use App\Models\RiderLocation;
use App\Services\RiderLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RiderLocationTest extends TestCase
{
    use RefreshDatabase;

    private RiderLocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->service = new RiderLocationService;
    }

    public function test_first_update_persists_row(): void
    {
        $rider = Rider::factory()->create();

        $result = $this->service->update($rider, 24.7136, 46.6753);

        $this->assertTrue($result['persisted']);
        $this->assertDatabaseHas('rider_locations', [
            'rider_id' => $rider->id,
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);
    }

    public function test_tiny_movement_is_throttled(): void
    {
        $rider = Rider::factory()->create();
        $this->service->update($rider, 24.7136, 46.6753);

        // ~11 m north — under the 25 m minimum.
        $result = $this->service->update($rider, 24.7137, 46.6753);

        $this->assertFalse($result['persisted']);
        $this->assertCount(1, $rider->locations()->get());
    }

    public function test_significant_movement_persists_new_row(): void
    {
        $rider = Rider::factory()->create();
        $this->service->update($rider, 24.7136, 46.6753);

        // ~1.1 km away — well above the threshold.
        $result = $this->service->update($rider, 24.7236, 46.6753);

        $this->assertTrue($result['persisted']);
        $this->assertCount(2, $rider->locations()->get());
    }

    public function test_latest_location_is_cached_with_ttl(): void
    {
        $rider = Rider::factory()->create();
        $this->service->update($rider, 24.7136, 46.6753);

        $cached = Cache::get(RiderLocationService::cacheKey($rider->id));

        $this->assertNotNull($cached);
        $this->assertSame(24.7136, $cached['latitude']);
        $this->assertSame(46.6753, $cached['longitude']);
    }

    public function test_throttled_update_does_not_refresh_cache_reference(): void
    {
        $rider = Rider::factory()->create();
        $this->service->update($rider, 24.7136, 46.6753);

        // Throttled: the tiny move must not become the new reference point.
        $this->service->update($rider, 24.71365, 46.6753);

        $this->assertCount(1, RiderLocation::where('rider_id', $rider->id)->get());
    }

    public function test_broadcasts_only_when_rider_has_active_assignment(): void
    {
        $rider = Rider::factory()->create();
        $assignment = DeliveryAssignment::factory()->create(['rider_id' => $rider->id]);

        $this->service->update($rider, 24.7136, 46.6753);

        Event::assertDispatched(RiderLocationUpdated::class, function (RiderLocationUpdated $event) use ($assignment, $rider) {
            return $event->orderId === $assignment->order_id
                && $event->riderId === $rider->id
                && $event->lat === 24.7136
                && $event->lng === 46.6753;
        });
    }

    public function test_no_broadcast_without_active_assignment(): void
    {
        $rider = Rider::factory()->create();

        $this->service->update($rider, 24.7136, 46.6753);

        Event::assertNotDispatched(RiderLocationUpdated::class);
    }

    public function test_broadcast_targets_the_tracking_channel(): void
    {
        $rider = Rider::factory()->create();
        $assignment = DeliveryAssignment::factory()->create(['rider_id' => $rider->id]);

        $this->service->update($rider, 24.7136, 46.6753);

        Event::assertDispatched(RiderLocationUpdated::class, function (RiderLocationUpdated $event) use ($assignment) {
            return $event->broadcastOn()->name === "tracking.{$assignment->order_id}";
        });
    }
}
