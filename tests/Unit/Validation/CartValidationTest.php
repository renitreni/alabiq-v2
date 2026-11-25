<?php

namespace Tests\Unit\Validation;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\CartService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartValidationTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cartService;

    private User $customer;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private MenuCategory $category;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();
        // Freeze time to a known value for consistent isOpen() checks
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 12, 0, 0));

        $this->customer = User::factory()->create();

        $this->restaurant = Restaurant::factory()->create([
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
        ]);

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

        $this->category = MenuCategory::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        $this->menuItem = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $this->category->id,
            'price' => 25.00,
            'is_available' => true,
        ]);
    }

    protected function getCartService(): CartService
    {
        // Create a fresh CartService instance for each test to avoid session pollution
        // Note: Session is cleared in setUp() and before each test
        return new CartService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $store = app('session.store');
        $store->forget('alabiq_cart');
        $store->save();
        parent::tearDown();
    }

    protected function setUpCartSession(array $cartData): void
    {
        // Use the session store directly to ensure it's persisted
        $store = app('session.store');
        $store->forget('alabiq_cart');
        $store->put('alabiq_cart', $cartData);
        $store->save();
    }

    public function test_validate_for_checkout_a_empty_cart_fails()
    {
        $this->setUpCartSession([
            'restaurant_id' => null,
            'branch_id' => null,
            'items' => [],
            'subtotal' => 0,
        ]);

        $result = $this->getCartService()->validateForCheckout();

        $this->assertFalse($result['valid']);
        $this->assertEquals('Cart is empty', $result['message']);
    }

    public function test_validate_for_checkout_b_no_branch_fails()
    {
        $this->setUpCartSession([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'items' => [
                'item1' => [
                    'menu_item_id' => $this->menuItem->id,
                    'name' => 'Test',
                    'price' => 25.00,
                    'options_price' => 0,
                    'final_price' => 25.00,
                    'quantity' => 1,
                    'options' => [],
                    'subtotal' => 25.00,
                ],
            ],
            'subtotal' => 25.00,
        ]);

        $result = $this->getCartService()->validateForCheckout();

        $this->assertFalse($result['valid']);
        $this->assertEquals('No delivery branch selected', $result['message']);
    }

    public function test_validate_for_checkout_c_branch_not_accepting_orders_fails()
    {
        $closedBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'accepts_orders' => false,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'delivery_fee' => 10.00,
            'minimum_order' => 20.00,
            'estimated_delivery_time' => 30,
        ]);

        $this->setUpCartSession([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $closedBranch->id,
            'items' => [
                'item1' => [
                    'menu_item_id' => $this->menuItem->id,
                    'name' => 'Test',
                    'price' => 25.00,
                    'options_price' => 0,
                    'final_price' => 25.00,
                    'quantity' => 1,
                    'options' => [],
                    'subtotal' => 25.00,
                ],
            ],
            'subtotal' => 25.00,
        ]);

        $result = $this->getCartService()->validateForCheckout();

        $this->assertFalse($result['valid']);
        $this->assertEquals('Selected branch is not accepting orders', $result['message']);
    }

    public function test_validate_for_checkout_z_branch_closed_fails()
    {
        // Closed at the frozen 12:00 local time
        $closedBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'accepts_orders' => true,
            'opens_at' => '10:00',
            'closes_at' => '11:00',
            'delivery_fee' => 10.00,
            'minimum_order' => 20.00,
            'estimated_delivery_time' => 30,
        ]);

        session(['alabiq_cart' => [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $closedBranch->id,
            'items' => [
                'item1' => [
                    'menu_item_id' => $this->menuItem->id,
                    'name' => 'Test',
                    'price' => 25.00,
                    'options_price' => 0,
                    'final_price' => 25.00,
                    'quantity' => 1,
                    'options' => [],
                    'subtotal' => 25.00,
                ],
            ],
            'subtotal' => 25.00,
        ]]);

        $result = $this->getCartService()->validateForCheckout();

        $this->assertFalse($result['valid']);
        $this->assertEquals('Selected branch is currently closed', $result['message']);
    }

    public function test_validate_for_checkout_d_below_minimum_order_fails()
    {
        // Explicitly clear session to ensure test isolation
        $store = app('session.store');
        $store->forget('alabiq_cart');
        $store->save();

        // Create a branch with high minimum order
        $highMinBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'accepts_orders' => true,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'delivery_fee' => 10.00,
            'minimum_order' => 50.00,
            'estimated_delivery_time' => 30,
        ]);

        $this->setUpCartSession([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $highMinBranch->id,
            'items' => [
                'item1' => [
                    'menu_item_id' => $this->menuItem->id,
                    'name' => 'Test',
                    'price' => 25.00,
                    'options_price' => 0,
                    'final_price' => 25.00,
                    'quantity' => 1,
                    'options' => [],
                    'subtotal' => 25.00,
                ],
            ],
            'subtotal' => 25.00,
        ]);

        $result = $this->getCartService()->validateForCheckout();

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Minimum order amount is 50.00', $result['message']);
        $this->assertEquals(50.00, $result['minimum_order']);
    }

    public function test_validate_for_checkout_e_item_unavailable_fails()
    {
        // Explicitly clear session to ensure test isolation
        $store = app('session.store');
        $store->forget('alabiq_cart');
        $store->save();

        // Use the default branch (open hours)
        $this->menuItem->update(['is_available' => false]);

        $this->setUpCartSession([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'items' => [
                'item1' => [
                    'menu_item_id' => $this->menuItem->id,
                    'name' => 'Test',
                    'price' => 25.00,
                    'options_price' => 0,
                    'final_price' => 25.00,
                    'quantity' => 1,
                    'options' => [],
                    'subtotal' => 25.00,
                ],
            ],
            'subtotal' => 25.00,
        ]);

        $result = $this->getCartService()->validateForCheckout();

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('no longer available', $result['message']);
    }

    public function test_validate_for_checkout_f_success()
    {
        // Explicitly clear session to ensure test isolation
        $store = app('session.store');
        $store->forget('alabiq_cart');
        $store->save();

        // Reset branch to open hours (open at 12:00 frozen time)
        $this->branch->update([
            'opens_at' => '00:00',
            'closes_at' => '23:59',
        ]);

        $this->menuItem->update(['is_available' => true]);

        $this->setUpCartSession([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'items' => [
                'item1' => [
                    'menu_item_id' => $this->menuItem->id,
                    'name' => 'Test',
                    'price' => 25.00,
                    'options_price' => 0,
                    'final_price' => 25.00,
                    'quantity' => 1,
                    'options' => [],
                    'subtotal' => 25.00,
                ],
            ],
            'subtotal' => 25.00,
        ]);

        $result = $this->getCartService()->validateForCheckout();

        $this->assertTrue($result['valid']);
    }

    public function test_get_cart_for_checkout_returns_correct_data()
    {
        $this->setUpCartSession([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'items' => [
                'item1' => [
                    'menu_item_id' => $this->menuItem->id,
                    'name' => 'Test',
                    'price' => 25.00,
                    'options_price' => 0,
                    'final_price' => 25.00,
                    'quantity' => 2,
                    'options' => [],
                    'subtotal' => 50.00,
                ],
            ],
            'subtotal' => 50.00,
        ]);

        $cartData = $this->getCartService()->getCartForCheckout();

        $this->assertEquals($this->restaurant->id, $cartData['restaurant_id']);
        $this->assertEquals($this->branch->id, $cartData['branch_id']);
        $this->assertNotNull($cartData['branch']);
        $this->assertEquals($this->branch->id, $cartData['branch']['id']);
        $this->assertEquals(10.00, $cartData['branch']['delivery_fee']);
        $this->assertEquals(20.00, $cartData['branch']['minimum_order']);
        $this->assertEquals(30, $cartData['branch']['estimated_delivery_time']);
        $this->assertCount(1, $cartData['items']);
        $this->assertEquals(50.00, $cartData['subtotal']);
        $this->assertEquals(10.00, $cartData['delivery_fee']);
        $this->assertEquals(60.00, $cartData['total']);
    }

    public function test_add_item_from_different_restaurant_throws_exception()
    {
        $otherRestaurant = Restaurant::factory()->create();
        $otherBranch = RestaurantBranch::factory()->create(['restaurant_id' => $otherRestaurant->id]);
        $otherCategory = MenuCategory::factory()->create(['restaurant_id' => $otherRestaurant->id]);
        $otherItem = MenuItem::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'menu_category_id' => $otherCategory->id,
            'price' => 30.00,
        ]);

        // Add item from first restaurant
        session(['alabiq_cart' => [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'items' => [],
            'subtotal' => 0,
        ]]);

        $this->getCartService()->addItem($this->menuItem->id, 1);

        // Try to add item from different restaurant
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('different restaurants');

        $this->getCartService()->addItem($otherItem->id, 1);
    }

    public function test_clear_cart_removes_all_data()
    {
        session(['alabiq_cart' => [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'items' => ['item1' => ['menu_item_id' => 1, 'quantity' => 1, 'final_price' => 25, 'subtotal' => 25]],
            'subtotal' => 25.00,
        ]]);

        $this->getCartService()->clear();

        $cart = $this->getCartService()->getCart();
        $this->assertNull($cart['restaurant_id']);
        $this->assertNull($cart['branch_id']);
        $this->assertEmpty($cart['items']);
        $this->assertEquals(0, $cart['subtotal']);
    }

    public function test_set_branch_updates_cart()
    {
        session(['alabiq_cart' => [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'items' => [],
            'subtotal' => 0,
        ]]);

        $cart = $this->getCartService()->setBranch($this->branch->id);

        $this->assertEquals($this->branch->id, $cart['branch_id']);
    }

    public function test_remove_item_reduces_quantity()
    {
        session(['alabiq_cart' => [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'items' => [
                'item1' => [
                    'menu_item_id' => $this->menuItem->id,
                    'name' => 'Test',
                    'price' => 25.00,
                    'options_price' => 0,
                    'final_price' => 25.00,
                    'quantity' => 3,
                    'options' => [],
                    'subtotal' => 75.00,
                ],
            ],
            'subtotal' => 75.00,
        ]]);

        $cart = $this->getCartService()->removeItem('item1');

        $this->assertEmpty($cart['items']);
        $this->assertEquals(0, $cart['subtotal']);
    }

    public function test_update_quantity_to_zero_removes_item()
    {
        session(['alabiq_cart' => [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'items' => [
                'item1' => [
                    'menu_item_id' => $this->menuItem->id,
                    'name' => 'Test',
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

        $cart = $this->getCartService()->updateQuantity('item1', 0);

        $this->assertEmpty($cart['items']);
        $this->assertEquals(0, $cart['subtotal']);
    }
}
