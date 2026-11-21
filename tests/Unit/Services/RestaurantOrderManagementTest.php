<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;

    private User $customer;

    private User $restaurantOwner;

    private User $otherRestaurantOwner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = new OrderService(new CartService);
        $this->customer = User::factory()->create();
        $this->restaurantOwner = User::factory()->create();
        $this->otherRestaurantOwner = User::factory()->create();

        $this->restaurant = Restaurant::factory()->create([
            'owner_id' => $this->restaurantOwner->id,
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
        ]);

        $this->otherRestaurant = Restaurant::factory()->create([
            'owner_id' => $this->otherRestaurantOwner->id,
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
        ]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_restaurant_can_accept_own_order()
    {
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->restaurantAccept($order, $this->restaurant->id);

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_ACCEPTED, $order->status);
        $this->assertCount(2, $order->statusHistories);
        $lastHistory = $order->statusHistories->last();
        $this->assertEquals(Order::STATUS_ACCEPTED, $lastHistory->to_status);
        $this->assertEquals($this->restaurantOwner->id, $lastHistory->changed_by);
        $this->assertEquals('restaurant', $lastHistory->changed_by_type);
        $this->assertEquals('Restaurant accepted the order', $lastHistory->note);
    }

    public function test_restaurant_cannot_accept_other_restaurant_order()
    {
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->restaurantAccept($order, $this->otherRestaurant->id);

        $this->assertFalse($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_PLACED, $order->status);
    }

    public function test_restaurant_can_reject_own_order()
    {
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->restaurantReject($order, $this->restaurant->id, 'Too busy today');

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertEquals('Too busy today', $order->cancellation_reason);
        $this->assertEquals('refunded', $order->payment_status);
    }

    public function test_restaurant_cannot_reject_other_restaurant_order()
    {
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->restaurantReject($order, $this->otherRestaurant->id, 'Wrong restaurant');

        $this->assertFalse($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_PLACED, $order->status);
    }

    public function test_restaurant_can_update_prep_status_accepted_to_preparing()
    {
        $order = Order::factory()->accepted()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->restaurantUpdatePrepStatus($order, Order::STATUS_PREPARING, $this->restaurant->id);

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_PREPARING, $order->status);
        $lastHistory = $order->statusHistories->last();
        $this->assertEquals('Restaurant started preparing the order', $lastHistory->note);
    }

    public function test_restaurant_can_update_prep_status_preparing_to_ready()
    {
        $order = Order::factory()->preparing()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->restaurantUpdatePrepStatus($order, Order::STATUS_READY, $this->restaurant->id);

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_READY, $order->status);
        $lastHistory = $order->statusHistories->last();
        $this->assertEquals('Order is ready for pickup', $lastHistory->note);
    }

    public function test_restaurant_cannot_update_prep_status_skip_steps()
    {
        $order = Order::factory()->accepted()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        // Try to jump from accepted to ready (skipping preparing)
        $result = $this->orderService->restaurantUpdatePrepStatus($order, Order::STATUS_READY, $this->restaurant->id);

        $this->assertFalse($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_ACCEPTED, $order->status);
    }

    public function test_restaurant_cannot_update_prep_status_from_wrong_state()
    {
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        // Can't go from placed to preparing (must accept first)
        $result = $this->orderService->restaurantUpdatePrepStatus($order, Order::STATUS_PREPARING, $this->restaurant->id);

        $this->assertFalse($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_PLACED, $order->status);
    }

    public function test_restaurant_cannot_update_other_restaurant_order_prep_status()
    {
        $order = Order::factory()->accepted()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->restaurantUpdatePrepStatus($order, Order::STATUS_PREPARING, $this->otherRestaurant->id);

        $this->assertFalse($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_ACCEPTED, $order->status);
    }

    public function test_get_restaurant_orders_returns_only_own_orders()
    {
        Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        Order::factory()->accepted()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->otherRestaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $orders = $this->orderService->getRestaurantOrders($this->restaurant->id);

        $this->assertCount(1, $orders);
        $this->assertEquals($this->restaurant->id, $orders->first()->restaurant_id);
    }

    public function test_get_restaurant_orders_filters_by_status()
    {
        Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        Order::factory()->accepted()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $placedOrders = $this->orderService->getRestaurantOrders($this->restaurant->id, Order::STATUS_PLACED);
        $this->assertCount(1, $placedOrders);
        $this->assertEquals(Order::STATUS_PLACED, $placedOrders->first()->status);

        $acceptedOrders = $this->orderService->getRestaurantOrders($this->restaurant->id, Order::STATUS_ACCEPTED);
        $this->assertCount(1, $acceptedOrders);
        $this->assertEquals(Order::STATUS_ACCEPTED, $acceptedOrders->first()->status);
    }

    public function test_customer_can_cancel_own_order()
    {
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->customerCancel($order, $this->customer->id, 'Changed my mind');

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertEquals('Changed my mind', $order->cancellation_reason);
    }

    public function test_customer_cannot_cancel_other_customer_order()
    {
        $otherCustomer = User::factory()->create();
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->customerCancel($order, $otherCustomer->id, 'Wrong order');

        $this->assertFalse($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_PLACED, $order->status);
    }

    public function test_admin_can_cancel_any_order()
    {
        $admin = User::factory()->create();
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->adminCancel($order, $admin->id, 'Platform policy violation');

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertEquals('Platform policy violation', $order->cancellation_reason);
        $this->assertEquals('refunded', $order->payment_status);
    }

    public function test_admin_can_process_refund_on_delivered_order()
    {
        $admin = User::factory()->create();
        $order = Order::factory()->delivered()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->processRefund($order, $admin->id, 'Customer complaint');

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_REFUNDED, $order->status);
        $this->assertEquals('refunded', $order->payment_status);
    }

    public function test_admin_cannot_refund_non_delivered_order()
    {
        $admin = User::factory()->create();
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->processRefund($order, $admin->id, 'Customer complaint');

        $this->assertFalse($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_PLACED, $order->status);
    }

    public function test_rider_can_pickup_order()
    {
        $rider = User::factory()->create();
        $order = Order::factory()->riderAssigned()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->riderPickup($order, $rider->id);

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_PICKED_UP, $order->status);
    }

    public function test_rider_can_mark_on_the_way()
    {
        $rider = User::factory()->create();
        $order = Order::factory()->pickedUp()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->riderOnTheWay($order, $rider->id);

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_ON_THE_WAY, $order->status);
    }

    public function test_rider_can_deliver_order()
    {
        $rider = User::factory()->create();
        $order = Order::factory()->onTheWay()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->riderDeliver($order, $rider->id);

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertEquals('paid', $order->payment_status);
    }
}
