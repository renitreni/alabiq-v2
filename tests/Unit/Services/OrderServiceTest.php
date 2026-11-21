<?php

namespace Tests\Unit\Services;

use App\Models\CustomerAddress;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemOption;
use App\Models\MenuItemOptionValue;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;

    private CartService $cartService;

    private User $customer;

    private User $restaurantOwner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private MenuCategory $category;

    private MenuItem $menuItem;

    private MenuItemOption $option;

    private MenuItemOptionValue $optionValue;

    protected function setUp(): void
    {
        parent::setUp();
        // Freeze time to a known value for consistent isOpen() checks
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 12, 0, 0));

        $this->orderService = new OrderService(new CartService);
        $this->cartService = new CartService;

        // Create customer
        $this->customer = User::factory()->create();

        // Create restaurant owner
        $this->restaurantOwner = User::factory()->create();

        // Create restaurant
        $this->restaurant = Restaurant::factory()->create([
            'owner_id' => $this->restaurantOwner->id,
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
        ]);

        // Create branch
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'accepts_orders' => true,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'delivery_fee' => 10.00,
            'minimum_order' => 20.00,
            'estimated_delivery_time' => 30,
        ]);

        // Create menu category
        $this->category = MenuCategory::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        // Create menu item
        $this->menuItem = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $this->category->id,
            'price' => 25.00,
            'is_available' => true,
        ]);

        // Create option and value
        $this->option = MenuItemOption::factory()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Size',
            'is_required' => true,
        ]);

        $this->optionValue = MenuItemOptionValue::factory()->create([
            'menu_item_option_id' => $this->option->id,
            'name' => 'Large',
            'price_adjustment' => 5.00,
            'is_active' => true,
        ]);

        // Add item to cart (simulating session)
        session(['alabiq_cart' => [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'items' => [
                $this->menuItem->id.'_'.md5('') => [
                    'menu_item_id' => $this->menuItem->id,
                    'name' => $this->menuItem->name,
                    'price' => 25.00,
                    'options_price' => 0,
                    'final_price' => 25.00,
                    'quantity' => 2,
                    'options' => [],
                    'subtotal' => 50.00,
                ],
            ],
            'subtotal' => 50.00,
        ]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_place_order_creates_order_with_items_and_history()
    {
        $customerAddress = CustomerAddress::factory()->create([
            'user_id' => $this->customer->id,
            'is_default' => true,
        ]);

        $order = $this->orderService->placeOrder([
            'customer_address_id' => $customerAddress->id,
            'payment_method' => 'cash_on_delivery',
            'delivery_instructions' => 'Ring the bell',
        ], $this->customer);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($this->customer->id, $order->user_id);
        $this->assertEquals($this->restaurant->id, $order->restaurant_id);
        $this->assertEquals($this->branch->id, $order->branch_id);
        $this->assertEquals($customerAddress->id, $order->customer_address_id);
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertEquals('cash_on_delivery', $order->payment_method);
        $this->assertEquals(50.00, $order->subtotal);
        $this->assertEquals(10.00, $order->delivery_fee);
        $this->assertEquals(7.50, $order->tax_amount); // 15% VAT
        $this->assertEquals(67.50, $order->total_amount); // 50 + 10 + 7.5

        // Check items
        $this->assertCount(1, $order->items);
        $orderItem = $order->items->first();
        $this->assertEquals($this->menuItem->id, $orderItem->menu_item_id);
        $this->assertEquals($this->menuItem->name, $orderItem->name);
        $this->assertEquals(25.00, $orderItem->price);
        $this->assertEquals(0, $orderItem->options_price);
        $this->assertEquals(25.00, $orderItem->final_price);
        $this->assertEquals(2, $orderItem->quantity);
        $this->assertEquals(50.00, $orderItem->subtotal);

        // Check status history
        $this->assertCount(1, $order->statusHistories);
        $history = $order->statusHistories->first();
        $this->assertNull($history->from_status);
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $history->to_status);
        $this->assertEquals($this->customer->id, $history->changed_by);
        $this->assertEquals('customer', $history->changed_by_type);
        $this->assertEquals('Order placed', $history->note);

        // Check snapshot data
        $this->assertArrayHasKey('restaurant', $order->snapshot_data);
        $this->assertArrayHasKey('branch', $order->snapshot_data);
    }

    public function test_place_order_with_options_snapshots_pricing()
    {
        // Add item with options to cart
        session(['alabiq_cart' => [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'items' => [
                $this->menuItem->id.'_'.md5($this->option->id.':'.$this->optionValue->id) => [
                    'menu_item_id' => $this->menuItem->id,
                    'name' => $this->menuItem->name,
                    'price' => 25.00,
                    'options_price' => 5.00,
                    'final_price' => 30.00,
                    'quantity' => 1,
                    'options' => [
                        [
                            'option_id' => $this->option->id,
                            'value_ids' => [$this->optionValue->id],
                        ],
                    ],
                    'subtotal' => 30.00,
                ],
            ],
            'subtotal' => 30.00,
        ]]);

        $order = $this->orderService->placeOrder([
            'payment_method' => 'cash_on_delivery',
        ], $this->customer);

        $orderItem = $order->items->first();
        $this->assertEquals(25.00, $orderItem->price);
        $this->assertEquals(5.00, $orderItem->options_price);
        $this->assertEquals(30.00, $orderItem->final_price);
        $this->assertNotNull($orderItem->options_snapshot);
        $this->assertCount(1, $orderItem->options_snapshot);
        $this->assertEquals('Size', $orderItem->options_snapshot[0]['option_name']);
        $this->assertEquals('Large', $orderItem->options_snapshot[0]['value_name']);
        $this->assertEquals(5.00, $orderItem->options_snapshot[0]['price_adjustment']);
    }

    public function test_place_order_clears_cart()
    {
        $this->orderService->placeOrder([
            'payment_method' => 'cash_on_delivery',
        ], $this->customer);

        $cart = $this->cartService->getCart();
        $this->assertEmpty($cart['items']);
        $this->assertNull($cart['restaurant_id']);
        $this->assertNull($cart['branch_id']);
        $this->assertEquals(0, $cart['subtotal']);
    }

    public function test_place_order_throws_exception_for_empty_cart()
    {
        session(['alabiq_cart' => [
            'restaurant_id' => null,
            'branch_id' => null,
            'items' => [],
            'subtotal' => 0,
        ]]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cart is empty');

        $this->orderService->placeOrder([
            'payment_method' => 'cash_on_delivery',
        ], $this->customer);
    }

    public function test_place_order_throws_exception_for_closed_branch()
    {
        // Use hours that are definitely closed (early morning)
        $this->branch->update([
            'opens_at' => '02:00',
            'closes_at' => '03:00',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('currently closed');

        $this->orderService->placeOrder([
            'payment_method' => 'cash_on_delivery',
        ], $this->customer);
    }

    public function test_place_order_throws_exception_for_below_minimum_order()
    {
        $this->branch->update([
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'minimum_order' => 100.00,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Minimum order amount');

        $this->orderService->placeOrder([
            'payment_method' => 'cash_on_delivery',
        ], $this->customer);
    }

    public function test_place_order_throws_exception_for_unavailable_item()
    {
        $this->branch->update([
            'opens_at' => '00:00',
            'closes_at' => '23:59',
        ]);

        $this->menuItem->update(['is_available' => false]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no longer available');

        $this->orderService->placeOrder([
            'payment_method' => 'cash_on_delivery',
        ], $this->customer);
    }

    public function test_transition_status_valid_transition_succeeds()
    {
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->transitionStatus(
            $order,
            Order::STATUS_ACCEPTED,
            $this->restaurantOwner->id,
            'restaurant',
            'Restaurant accepted'
        );

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_ACCEPTED, $order->status);
        $this->assertCount(2, $order->statusHistories); // initial + transition
    }

    public function test_transition_status_invalid_transition_fails()
    {
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        // Try to jump from placed to ready (skipping accepted/preparing)
        $result = $this->orderService->transitionStatus(
            $order,
            Order::STATUS_READY,
            $this->restaurantOwner->id,
            'restaurant'
        );

        $this->assertFalse($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_PLACED, $order->status);
    }

    public function test_transition_status_terminal_state_cannot_transition()
    {
        $order = Order::factory()->delivered()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->orderService->transitionStatus(
            $order,
            Order::STATUS_CANCELLED,
            $this->customer->id,
            'customer'
        );

        $this->assertFalse($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
    }

    public function test_restaurant_accept_only_own_restaurant()
    {
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $otherOwner = User::factory()->create();
        $otherRestaurant = Restaurant::factory()->create(['owner_id' => $otherOwner->id]);

        // Try to accept with wrong restaurant ID
        $result = $this->orderService->restaurantAccept($order, $otherRestaurant->id);

        $this->assertFalse($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_PLACED, $order->status);

        // Accept with correct restaurant ID
        $result = $this->orderService->restaurantAccept($order, $this->restaurant->id);
        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_ACCEPTED, $order->status);
    }

    public function test_restaurant_reject_only_own_restaurant()
    {
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $otherOwner = User::factory()->create();
        $otherRestaurant = Restaurant::factory()->create(['owner_id' => $otherOwner->id]);

        $result = $this->orderService->restaurantReject($order, $otherRestaurant->id, 'Not our order');
        $this->assertFalse($result);

        $result = $this->orderService->restaurantReject($order, $this->restaurant->id, 'Too busy');
        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertEquals('Too busy', $order->cancellation_reason);
    }

    public function test_customer_cancel_own_order_only()
    {
        $order = Order::factory()->placed()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $otherCustomer = User::factory()->create();

        $result = $this->orderService->customerCancel($order, $otherCustomer->id, 'Wrong order');
        $this->assertFalse($result);

        $result = $this->orderService->customerCancel($order, $this->customer->id, 'Changed mind');
        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
    }
}
