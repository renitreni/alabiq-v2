<?php

namespace Tests\Feature\Api;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceCartControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->role(User::ROLE_CUSTOMER)->create();

        $this->restaurant = Restaurant::factory()->create([
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
        ]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'accepts_orders' => true,
        ]);

        $category = MenuCategory::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->menuItem = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $category->id,
            'price' => 25.00,
            'is_available' => true,
        ]);
    }

    public function test_guest_can_add_item_to_cart(): void
    {
        $response = $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);

        $response->assertCreated()
            ->assertJson([
                'message' => 'Item added to cart',
                'data' => [
                    'restaurant_id' => $this->restaurant->id,
                    'item_count' => 1,
                    'subtotal' => 25.00,
                ],
            ]);

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals($this->menuItem->name, $response->json('data.items.0.name'));
        $this->assertEquals(25.00, (float) $response->json('data.items.0.final_price'));
        $this->assertEquals(1, $response->json('data.items.0.quantity'));
    }

    public function test_adding_same_item_increments_quantity(): void
    {
        $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJson(['data' => ['item_count' => 3, 'subtotal' => 75.00]]);
    }

    public function test_cannot_add_items_from_different_restaurants(): void
    {
        $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();

        $otherRestaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $otherRestaurant->id]);
        $otherItem = MenuItem::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'menu_category_id' => $category->id,
            'price' => 30.00,
        ]);

        $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => $otherItem->id,
            'quantity' => 1,
        ])->assertStatus(400)
            ->assertJson(['message' => 'Cannot add items from different restaurants. Please clear your cart first.']);
    }

    public function test_add_rejects_unknown_menu_item(): void
    {
        $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => 999999,
            'quantity' => 1,
        ])->assertUnprocessable();
    }

    public function test_add_rejects_quantity_below_one(): void
    {
        $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 0,
        ])->assertUnprocessable();
    }
}
