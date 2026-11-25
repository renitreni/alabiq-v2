<?php

namespace Tests\Feature\Api;

use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Rider;
use App\Models\RiderAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderApiTest extends TestCase
{
    use RefreshDatabase;

    private User $riderUser;

    private Rider $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->riderUser = User::factory()->role(User::ROLE_RIDER)->create();
        $this->rider = Rider::factory()->online()->create(['user_id' => $this->riderUser->id]);
    }

    public function test_availability_endpoints_require_authentication(): void
    {
        $this->putJson('/api/rider/availability', ['status' => 'online'])->assertUnauthorized();
        $this->postJson('/api/rider/location', ['latitude' => 24.7, 'longitude' => 46.6])->assertUnauthorized();
        $this->getJson('/api/rider/assignments')->assertUnauthorized();
    }

    public function test_rider_goes_online(): void
    {
        Sanctum::actingAs($this->riderUser);

        $this->putJson('/api/rider/availability', ['status' => 'online'])
            ->assertOk()
            ->assertJsonPath('data.status', 'online')
            ->assertJsonPath('data.is_online', true);

        $this->assertDatabaseHas('riders', ['id' => $this->rider->id, 'is_online' => true]);
        $this->assertDatabaseHas('rider_availabilities', [
            'rider_id' => $this->rider->id,
            'status' => RiderAvailability::STATUS_ONLINE,
        ]);
    }

    public function test_rider_goes_offline_and_is_marked_not_online(): void
    {
        Sanctum::actingAs($this->riderUser);

        $this->putJson('/api/rider/availability', ['status' => 'offline'])->assertOk();

        $this->assertDatabaseHas('riders', ['id' => $this->rider->id, 'is_online' => false]);
    }

    public function test_invalid_availability_status_is_rejected(): void
    {
        Sanctum::actingAs($this->riderUser);

        $this->putJson('/api/rider/availability', ['status' => 'sleeping'])->assertUnprocessable();
    }

    public function test_non_rider_cannot_use_rider_endpoints(): void
    {
        $customer = User::factory()->role(User::ROLE_CUSTOMER)->create();
        Sanctum::actingAs($customer);

        $this->putJson('/api/rider/availability', ['status' => 'online'])->assertForbidden();
        $this->postJson('/api/rider/location', ['latitude' => 24.7, 'longitude' => 46.6])->assertForbidden();
    }

    public function test_location_endpoint_validates_coordinates(): void
    {
        Sanctum::actingAs($this->riderUser);

        $this->postJson('/api/rider/location', ['latitude' => 91, 'longitude' => 46.6])
            ->assertUnprocessable();

        $this->postJson('/api/rider/location', ['latitude' => 24.7, 'longitude' => 181])
            ->assertUnprocessable();
    }

    public function test_location_endpoint_persists_valid_update(): void
    {
        Sanctum::actingAs($this->riderUser);

        $this->postJson('/api/rider/location', [
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'speed' => 12.5,
        ])->assertOk()->assertJsonPath('data.persisted', true);

        $this->assertDatabaseHas('rider_locations', [
            'rider_id' => $this->rider->id,
            'latitude' => 24.7136,
        ]);
    }

    public function test_forced_location_is_logged_even_when_rider_has_not_moved(): void
    {
        Sanctum::actingAs($this->riderUser);
        $payload = ['latitude' => 24.7136, 'longitude' => 46.6753];

        $this->postJson('/api/rider/location', $payload)->assertJsonPath('data.persisted', true);
        $this->postJson('/api/rider/location', $payload)->assertJsonPath('data.persisted', false);
        $this->postJson('/api/rider/location', $payload + ['force' => true])->assertJsonPath('data.persisted', true);

        $this->assertDatabaseCount('rider_locations', 2);
    }

    public function test_online_rider_sees_orders_waiting_for_a_rider(): void
    {
        $placed = Order::factory()->create(['status' => Order::STATUS_PLACED]);
        Order::factory()->create(['status' => Order::STATUS_DELIVERED]);
        Sanctum::actingAs($this->riderUser);

        $this->getJson('/api/rider/available-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $placed->id);
    }

    public function test_offline_rider_sees_no_available_orders(): void
    {
        Order::factory()->create(['status' => Order::STATUS_PLACED]);
        $this->rider->update(['is_online' => false]);
        Sanctum::actingAs($this->riderUser);

        $this->getJson('/api/rider/available-orders')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_rider_lists_own_assignments(): void
    {
        Sanctum::actingAs($this->riderUser);
        DeliveryAssignment::factory()->count(2)->create(['rider_id' => $this->rider->id]);
        DeliveryAssignment::factory()->create(); // another rider's

        $response = $this->getJson('/api/rider/assignments')->assertOk();

        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_rider_accepts_assignment(): void
    {
        Sanctum::actingAs($this->riderUser);
        $assignment = DeliveryAssignment::factory()->create([
            'rider_id' => $this->rider->id,
            'status' => DeliveryAssignment::STATUS_ASSIGNED,
        ]);

        $this->postJson("/api/rider/assignments/{$assignment->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_rider_rejects_assignment_with_reason(): void
    {
        Sanctum::actingAs($this->riderUser);
        $assignment = DeliveryAssignment::factory()->create([
            'rider_id' => $this->rider->id,
            'status' => DeliveryAssignment::STATUS_ASSIGNED,
        ]);

        $this->postJson("/api/rider/assignments/{$assignment->id}/reject", ['reason' => 'Too far'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejected_reason', 'Too far');
    }

    public function test_rider_cannot_accept_another_riders_assignment(): void
    {
        Sanctum::actingAs($this->riderUser);
        $assignment = DeliveryAssignment::factory()->create([
            'status' => DeliveryAssignment::STATUS_ASSIGNED,
        ]);

        // Ownership violation is a 409 from DispatchService.
        $this->postJson("/api/rider/assignments/{$assignment->id}/accept")
            ->assertConflict();
    }

    public function test_restaurant_dispatches_ready_order_via_api(): void
    {
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        $restaurant = Restaurant::factory()->create(['owner_id' => $owner->id]);
        $order = Order::factory()->ready()->create(['restaurant_id' => $restaurant->id]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/orders/{$order->id}/assign-rider", ['rider_id' => $this->rider->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'assigned');

        $this->assertSame(Order::STATUS_RIDER_ASSIGNED, $order->fresh()->status);
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'rider_id' => $this->rider->id,
        ]);
    }

    public function test_other_restaurant_cannot_dispatch_order(): void
    {
        $otherOwner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        $order = Order::factory()->ready()->create();

        Sanctum::actingAs($otherOwner);

        $this->postJson("/api/orders/{$order->id}/assign-rider", ['rider_id' => $this->rider->id])
            ->assertForbidden();
    }

    public function test_dispatching_non_ready_order_returns_conflict(): void
    {
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        $restaurant = Restaurant::factory()->create(['owner_id' => $owner->id]);
        $order = Order::factory()->placed()->create(['restaurant_id' => $restaurant->id]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/orders/{$order->id}/assign-rider", ['rider_id' => $this->rider->id])
            ->assertConflict();
    }
}
