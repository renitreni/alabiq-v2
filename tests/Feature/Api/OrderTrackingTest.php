<?php

namespace Tests\Feature\Api;

use App\Models\CustomerAddress;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryTrackingEvent;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Rider;
use App\Models\RiderLocation;
use App\Models\User;
use App\Services\RiderLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Order $order;

    private Rider $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->role(User::ROLE_CUSTOMER)->create();
        $address = CustomerAddress::factory()->create([
            'user_id' => $this->customer->id,
            'latitude' => 24.75,
            'longitude' => 46.70,
        ]);
        $this->order = Order::factory()->riderAssigned()->create([
            'user_id' => $this->customer->id,
            'customer_address_id' => $address->id,
        ]);
        $this->rider = Rider::factory()->online()->create();
        DeliveryAssignment::factory()->accepted()->create([
            'order_id' => $this->order->id,
            'rider_id' => $this->rider->id,
        ]);
        DeliveryTrackingEvent::factory()->create([
            'order_id' => $this->order->id,
            'rider_id' => $this->rider->id,
            'event' => 'accepted',
        ]);
    }

    public function test_customer_sees_latest_rider_location_and_events(): void
    {
        RiderLocation::factory()->create([
            'rider_id' => $this->rider->id,
            'latitude' => 24.74,
            'longitude' => 46.69,
        ]);
        Cache::put(RiderLocationService::cacheKey($this->rider->id), [
            'latitude' => 24.745,
            'longitude' => 46.695,
            'recorded_at' => now(),
        ], 300);

        Sanctum::actingAs($this->customer);

        $response = $this->getJson("/api/orders/{$this->order->id}/tracking")->assertOk();

        $response->assertJsonPath('data.order_id', $this->order->id);
        $response->assertJsonPath('data.status', 'rider_assigned');
        $response->assertJsonPath('data.location.latitude', 24.745); // cached wins
        $response->assertJsonPath('data.assignment.status', 'accepted');
        $this->assertNotEmpty($response->json('data.events'));
    }

    public function test_eta_is_present_when_address_has_coordinates(): void
    {
        RiderLocation::factory()->create([
            'rider_id' => $this->rider->id,
            'latitude' => 24.72,
            'longitude' => 46.68,
        ]);

        Sanctum::actingAs($this->customer);

        // No Mapbox token in tests -> haversine fallback ETA.
        $this->getJson("/api/orders/{$this->order->id}/tracking")
            ->assertOk()
            ->assertJsonStructure(['data' => ['eta_minutes']]);
    }

    public function test_stranger_cannot_track_order(): void
    {
        $stranger = User::factory()->role(User::ROLE_CUSTOMER)->create();
        Sanctum::actingAs($stranger);

        $this->getJson("/api/orders/{$this->order->id}/tracking")->assertForbidden();
    }

    public function test_owning_restaurant_can_track_order(): void
    {
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        $restaurant = Restaurant::factory()->create(['owner_id' => $owner->id]);
        $this->order->update(['restaurant_id' => $restaurant->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/orders/{$this->order->id}/tracking")->assertOk();
    }

    public function test_tracking_requires_authentication(): void
    {
        $this->getJson("/api/orders/{$this->order->id}/tracking")->assertUnauthorized();
    }
}
