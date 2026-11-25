<?php

namespace Tests\Feature\Api;

use App\Models\CustomerAddress;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $restaurantOwner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->role(User::ROLE_CUSTOMER)->create();
        $this->restaurantOwner = User::factory()->role(User::ROLE_RESTAURANT)->create();

        $this->restaurant = Restaurant::factory()->create([
            'owner_id' => $this->restaurantOwner->id,
        ]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'minimum_order' => 0,
        ]);

        $category = MenuCategory::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->menuItem = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $category->id,
            'price' => 25.00,
            'is_available' => true,
        ]);
    }

    /**
     * Cart payload matching CartService's session format.
     */
    private function cartSession(): array
    {
        return [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'items' => [
                $this->menuItem->id => [
                    'menu_item_id' => $this->menuItem->id,
                    'name' => $this->menuItem->name,
                    'quantity' => 2,
                    'price' => 25.00,
                    'options' => [],
                    'subtotal' => 50.00,
                ],
            ],
            'subtotal' => 50.00,
        ];
    }

    private function customerAddress(array $attributes = []): CustomerAddress
    {
        return CustomerAddress::factory()->create(array_merge(
            ['user_id' => $this->customer->id, 'latitude' => null, 'longitude' => null],
            $attributes
        ));
    }

    private function checkoutCod(): Order
    {
        Sanctum::actingAs($this->customer);

        $response = $this->withSession(['alabiq_cart' => $this->cartSession()])
            ->postJson('/api/orders/checkout', [
                'payment_method' => 'cash_on_delivery',
                'customer_address_id' => $this->customerAddress()->id,
            ]);

        $response->assertCreated();

        return Order::findOrFail($response->json('data.id'));
    }

    public function test_checkout_requires_authentication(): void
    {
        $this->postJson('/api/orders/checkout', [
            'payment_method' => 'cash_on_delivery',
        ])->assertUnauthorized();
    }

    public function test_checkout_validates_payment_method(): void
    {
        Sanctum::actingAs($this->customer);

        $this->withSession(['alabiq_cart' => $this->cartSession()])
            ->postJson('/api/orders/checkout', [
                'payment_method' => 'barter',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_cod_checkout_places_order_immediately(): void
    {
        $order = $this->checkoutCod();

        $this->assertEquals(Order::STATUS_PLACED, $order->status);
        $this->assertEquals('pending', $order->payment_status);
        $this->assertEquals(50.00, (float) $order->subtotal);
        $this->assertEquals(2, $order->items->sum('quantity'));
        $this->assertCount(1, $order->statusHistories);
    }

    public function test_online_payment_checkout_waits_in_pending_payment(): void
    {
        config(['alabiq.online_payments_enabled' => true]);
        Sanctum::actingAs($this->customer);

        $response = $this->withSession(['alabiq_cart' => $this->cartSession()])
            ->postJson('/api/orders/checkout', [
                'customer_address_id' => $this->customerAddress()->id,
                'payment_method' => 'tap_payments',
            ]);

        $response->assertCreated();
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $response->json('data.status'));
    }

    public function test_checkout_rejects_address_belonging_to_another_customer(): void
    {
        Sanctum::actingAs($this->customer);
        $strangerAddress = CustomerAddress::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->withSession(['alabiq_cart' => $this->cartSession()])
            ->postJson('/api/orders/checkout', [
                'payment_method' => 'cash_on_delivery',
                'customer_address_id' => $strangerAddress->id,
            ])->assertBadRequest();
    }

    public function test_checkout_requires_a_delivery_address(): void
    {
        Sanctum::actingAs($this->customer);

        $this->withSession(['alabiq_cart' => $this->cartSession()])
            ->postJson('/api/orders/checkout', ['payment_method' => 'cash_on_delivery'])
            ->assertJsonValidationErrors(['customer_address_id']);
    }

    public function test_checkout_rejects_address_outside_branch_delivery_radius(): void
    {
        Sanctum::actingAs($this->customer);
        $this->branch->update(['latitude' => 24.7136, 'longitude' => 46.6753, 'delivery_radius_km' => 5]);
        $farAddress = $this->customerAddress(['latitude' => 24.8136, 'longitude' => 46.6753]);

        $this->withSession(['alabiq_cart' => $this->cartSession()])
            ->postJson('/api/orders/checkout', [
                'payment_method' => 'cash_on_delivery',
                'customer_address_id' => $farAddress->id,
            ])
            ->assertBadRequest()
            ->assertJsonPath('message', "Your delivery address is outside this branch's delivery area");

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_accepts_address_inside_branch_delivery_radius(): void
    {
        Sanctum::actingAs($this->customer);
        $this->branch->update(['latitude' => 24.7136, 'longitude' => 46.6753, 'delivery_radius_km' => 5]);
        $nearAddress = $this->customerAddress(['latitude' => 24.7236, 'longitude' => 46.6753]);

        $this->withSession(['alabiq_cart' => $this->cartSession()])
            ->postJson('/api/orders/checkout', [
                'payment_method' => 'cash_on_delivery',
                'customer_address_id' => $nearAddress->id,
            ])
            ->assertCreated();
    }

    public function test_checkout_rejects_unpinned_address_when_branch_has_radius(): void
    {
        Sanctum::actingAs($this->customer);
        $this->branch->update(['latitude' => 24.7136, 'longitude' => 46.6753, 'delivery_radius_km' => 5]);

        $this->withSession(['alabiq_cart' => $this->cartSession()])
            ->postJson('/api/orders/checkout', [
                'payment_method' => 'cash_on_delivery',
                'customer_address_id' => $this->customerAddress()->id,
            ])
            ->assertBadRequest();
    }

    public function test_customer_can_view_own_order_but_not_anothers(): void
    {
        $order = $this->checkoutCod();

        Sanctum::actingAs($this->customer);
        $this->getJson("/api/orders/{$order->id}")->assertOk();

        Sanctum::actingAs(User::factory()->role(User::ROLE_CUSTOMER)->create());
        $this->getJson("/api/orders/{$order->id}")->assertForbidden();
    }

    public function test_restaurant_owner_can_view_order_but_not_other_restaurants(): void
    {
        $order = $this->checkoutCod();

        Sanctum::actingAs($this->restaurantOwner);
        $this->getJson("/api/orders/{$order->id}")->assertOk();

        $otherOwner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        Restaurant::factory()->create(['owner_id' => $otherOwner->id]);

        Sanctum::actingAs($otherOwner);
        $this->getJson("/api/orders/{$order->id}")->assertForbidden();
    }

    public function test_restaurant_can_accept_and_prepare_own_order(): void
    {
        $order = $this->checkoutCod();

        Sanctum::actingAs($this->restaurantOwner);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'preparing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'preparing');
    }

    public function test_restaurant_cannot_touch_other_restaurants_order(): void
    {
        $order = $this->checkoutCod();
        $otherOwner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        Restaurant::factory()->create(['owner_id' => $otherOwner->id]);

        Sanctum::actingAs($otherOwner);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'accepted'])
            ->assertForbidden();
    }

    public function test_invalid_transition_returns_409(): void
    {
        $order = $this->checkoutCod();

        Sanctum::actingAs($this->restaurantOwner);

        // placed -> delivered is not a valid transition.
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertConflict();

        // Unknown status value fails validation, not the state machine.
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'teleported'])
            ->assertUnprocessable();
    }

    public function test_customer_can_cancel_own_order_but_cannot_accept(): void
    {
        $order = $this->checkoutCod();

        Sanctum::actingAs($this->customer);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'accepted'])
            ->assertConflict();

        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'cancelled',
            'note' => 'Changed my mind',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_terminal_status_cannot_transition_anywhere(): void
    {
        $order = $this->checkoutCod();

        Sanctum::actingAs($this->customer);
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        // Cancelled is terminal — even the restaurant cannot resurrect it.
        Sanctum::actingAs($this->restaurantOwner);
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'accepted'])
            ->assertConflict();
    }

    public function test_full_cod_order_lifecycle_end_to_end(): void
    {
        $order = $this->checkoutCod();

        // Restaurant: accept -> prepare -> ready -> assign rider
        Sanctum::actingAs($this->restaurantOwner);
        foreach (['accepted', 'preparing', 'ready', 'rider_assigned'] as $status) {
            $this->patchJson("/api/orders/{$order->id}/status", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }

        // Rider: pickup -> on the way -> delivered
        $rider = User::factory()->role(User::ROLE_RIDER)->create();
        Sanctum::actingAs($rider);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'picked_up'])
            ->assertOk();
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'on_the_way'])
            ->assertOk();

        $response = $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'delivered']);
        $response->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.payment_status', 'paid');

        // Audit trail recorded every transition.
        $this->assertEquals(
            ['placed', 'accepted', 'preparing', 'ready', 'rider_assigned', 'picked_up', 'on_the_way', 'delivered'],
            $order->statusHistories()->pluck('to_status')->all()
        );
    }

    public function test_rider_cannot_pick_up_before_assignment(): void
    {
        $order = $this->checkoutCod();

        // Restaurant marks ready but does not assign a rider yet.
        Sanctum::actingAs($this->restaurantOwner);
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'accepted'])->assertOk();
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'preparing'])->assertOk();
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'ready'])->assertOk();

        $rider = User::factory()->role(User::ROLE_RIDER)->create();
        Sanctum::actingAs($rider);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'picked_up'])
            ->assertConflict();
    }

    public function test_customer_order_list_is_scoped_and_filterable(): void
    {
        $this->checkoutCod();

        Sanctum::actingAs($this->customer);
        $response = $this->getJson('/api/orders?status=placed');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
        $this->assertEquals('placed', $response->json('data.0.status'));

        $empty = $this->getJson('/api/orders?status=delivered');
        $this->assertCount(0, $empty->json('data'));
    }

    public function test_order_status_requires_authentication(): void
    {
        $order = Order::factory()->create();

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'accepted'])
            ->assertUnauthorized();
    }
}
