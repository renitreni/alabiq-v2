<?php

namespace Tests\Feature\Api;

use App\Models\DeliveryAssignment;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Rider;
use App\Models\RiderLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RestaurantManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private MenuCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        $this->restaurant = Restaurant::factory()->create([
            'owner_id' => $this->owner->id,
        ]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->category = MenuCategory::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_restaurant_can_list_and_filter_own_orders(): void
    {
        $placed = Order::factory()->placed()->create([
            'user_id' => User::factory()->role(User::ROLE_CUSTOMER)->create()->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        Order::factory()->accepted()->create([
            'user_id' => User::factory()->role(User::ROLE_CUSTOMER)->create()->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        Sanctum::actingAs($this->owner);

        $this->getJson('/api/restaurant/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/restaurant/orders?status=placed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $placed->id);
    }

    public function test_restaurant_can_list_available_riders_for_dispatch(): void
    {
        $order = Order::factory()->ready()->create([
            'user_id' => User::factory()->role(User::ROLE_CUSTOMER)->create()->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $near = Rider::factory()->online()->create();
        RiderLocation::factory()->create([
            'rider_id' => $near->id,
            'latitude' => $this->branch->latitude,
            'longitude' => $this->branch->longitude,
        ]);
        $busy = Rider::factory()->online()->create();
        DeliveryAssignment::factory()->accepted()->create(['rider_id' => $busy->id]);
        Rider::factory()->offline()->create();

        Sanctum::actingAs($this->owner);

        $this->getJson("/api/restaurant/orders/{$order->id}/available-riders")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $near->id)
            ->assertJsonPath('data.0.name', $near->user->name);
    }

    public function test_available_riders_is_empty_when_order_is_not_dispatchable(): void
    {
        $order = Order::factory()->preparing()->create([
            'user_id' => User::factory()->role(User::ROLE_CUSTOMER)->create()->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        Sanctum::actingAs($this->owner);

        $this->getJson("/api/restaurant/orders/{$order->id}/available-riders")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_restaurant_cannot_list_riders_for_another_restaurants_order(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $otherBranch = RestaurantBranch::factory()->create(['restaurant_id' => $otherRestaurant->id]);
        $order = Order::factory()->ready()->create([
            'restaurant_id' => $otherRestaurant->id,
            'branch_id' => $otherBranch->id,
        ]);

        Sanctum::actingAs($this->owner);

        $this->getJson("/api/restaurant/orders/{$order->id}/available-riders")->assertNotFound();
    }

    public function test_restaurant_can_assign_rider_to_ready_order(): void
    {
        $order = Order::factory()->ready()->create([
            'user_id' => User::factory()->role(User::ROLE_CUSTOMER)->create()->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $rider = Rider::factory()->online()->create();

        Sanctum::actingAs($this->owner);

        $this->postJson("/api/orders/{$order->id}/assign-rider", ['rider_id' => $rider->id])
            ->assertCreated()
            ->assertJsonPath('data.rider_id', $rider->id)
            ->assertJsonPath('data.status', DeliveryAssignment::STATUS_ASSIGNED);

        $this->assertSame(Order::STATUS_RIDER_ASSIGNED, $order->fresh()->status);
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'rider_id' => $rider->id,
        ]);
    }

    public function test_restaurant_can_reject_rider_and_dispatch_again(): void
    {
        $order = Order::factory()->ready()->create([
            'user_id' => User::factory()->role(User::ROLE_CUSTOMER)->create()->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $first = Rider::factory()->online()->create();
        $second = Rider::factory()->online()->create();

        Sanctum::actingAs($this->owner);

        $this->postJson("/api/orders/{$order->id}/assign-rider", ['rider_id' => $first->id])
            ->assertCreated();

        // First rider rejects the offer.
        Sanctum::actingAs($first->user);
        $assignment = DeliveryAssignment::where('order_id', $order->id)->sole();
        $this->postJson("/api/rider/assignments/{$assignment->id}/reject", ['reason' => 'Too far away'])
            ->assertOk();

        // The order stays rider_assigned; the restaurant can re-dispatch.
        $this->assertSame(Order::STATUS_RIDER_ASSIGNED, $order->fresh()->status);

        Sanctum::actingAs($this->owner);

        $response = $this->getJson("/api/restaurant/orders/{$order->id}");
        $response->assertOk()
            ->assertJsonPath('data.assignments.0.status', DeliveryAssignment::STATUS_REJECTED);

        $this->postJson("/api/orders/{$order->id}/assign-rider", ['rider_id' => $second->id])
            ->assertCreated();

        $this->assertSame(Order::STATUS_RIDER_ASSIGNED, $order->fresh()->status);
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'rider_id' => $second->id,
            'status' => DeliveryAssignment::STATUS_ASSIGNED,
        ]);
    }

    public function test_restaurant_cannot_view_another_restaurants_order(): void
    {
        $order = Order::factory()->placed()->create([
            'user_id' => User::factory()->role(User::ROLE_CUSTOMER)->create()->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $otherOwner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        Restaurant::factory()->create(['owner_id' => $otherOwner->id]);

        Sanctum::actingAs($otherOwner);

        $this->getJson("/api/restaurant/orders/{$order->id}")->assertNotFound();
    }

    public function test_customer_cannot_access_restaurant_management_api(): void
    {
        Sanctum::actingAs(User::factory()->role(User::ROLE_CUSTOMER)->create());

        $this->getJson('/api/restaurant/orders')->assertForbidden();
        $this->getJson('/api/restaurant/menu')->assertForbidden();
    }

    public function test_restaurant_can_create_menu_item_with_new_category(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/restaurant/menu', [
            'category_name' => 'Grill',
            'name' => 'Chicken kebab',
            'description' => 'Grilled chicken with saffron rice',
            'price' => 32.50,
            'preparation_time' => '20 mins',
            'is_available' => true,
            'is_popular' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Chicken kebab')
            ->assertJsonPath('data.category.name', 'Grill');

        $this->assertDatabaseHas('menu_categories', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Grill',
        ]);
        $this->assertDatabaseHas('menu_items', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Chicken kebab',
            'price' => 32.50,
        ]);
    }

    public function test_restaurant_can_update_and_delete_menu_item(): void
    {
        $item = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $this->category->id,
            'name' => 'Original item',
            'price' => 20.00,
            'is_available' => true,
        ]);

        Sanctum::actingAs($this->owner);

        $this->putJson("/api/restaurant/menu/{$item->id}", [
            'menu_category_id' => $this->category->id,
            'name' => 'Updated item',
            'price' => 24.00,
            'is_available' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated item')
            ->assertJsonPath('data.is_available', false);

        $this->deleteJson("/api/restaurant/menu/{$item->id}")
            ->assertOk();

        $this->assertSoftDeleted('menu_items', ['id' => $item->id]);
    }

    public function test_restaurant_cannot_use_another_restaurants_category(): void
    {
        $otherCategory = MenuCategory::factory()->create();

        Sanctum::actingAs($this->owner);

        $this->postJson('/api/restaurant/menu', [
            'menu_category_id' => $otherCategory->id,
            'name' => 'Invalid category item',
            'price' => 15.00,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['menu_category_id']);
    }
}
