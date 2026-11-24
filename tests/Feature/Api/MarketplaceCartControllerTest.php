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

    public function test_cart_item_key_is_returned_when_adding_and_listing_items(): void
    {
        $response = $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ])->assertCreated();

        $itemKey = $response->json('data.items.0.item_key');

        $this->assertIsString($itemKey);
        $this->getJson('/api/marketplace/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.item_key', $itemKey)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.item_count', 2);
    }

    public function test_quantity_update_recalculates_cart_totals(): void
    {
        $response = $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ])->assertCreated();
        $itemKey = $response->json('data.items.0.item_key');

        $this->putJson("/api/marketplace/cart/{$itemKey}", ['quantity' => 5])
            ->assertOk()
            ->assertJsonPath('data.items.0.item_key', $itemKey)
            ->assertJsonPath('data.items.0.quantity', 5)
            ->assertJsonPath('data.item_count', 5)
            ->assertJsonPath('data.subtotal', 125)
            ->assertSessionHas("alabiq_cart.items.{$itemKey}.quantity", 5);
    }

    public function test_quantity_update_rejects_more_than_99_and_preserves_cart(): void
    {
        $response = $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ])->assertCreated();
        $itemKey = $response->json('data.items.0.item_key');

        $this->putJson("/api/marketplace/cart/{$itemKey}", ['quantity' => 100])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity'])
            ->assertJsonPath('errors.quantity.0', 'The quantity field must not be greater than 99.')
            ->assertSessionHas("alabiq_cart.items.{$itemKey}.quantity", 2);
    }

    public function test_removing_item_returns_updated_cart_and_resets_empty_cart(): void
    {
        $response = $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 3,
        ])->assertCreated();
        $itemKey = $response->json('data.items.0.item_key');

        $this->deleteJson("/api/marketplace/cart/{$itemKey}")
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.item_count', 0)
            ->assertJsonPath('data.subtotal', 0)
            ->assertSessionHas('alabiq_cart.items', []);

        $this->assertNull(session('alabiq_cart.restaurant_id'));
        $this->assertNull(session('alabiq_cart.branch_id'));
    }

    public function test_removing_one_line_preserves_other_items(): void
    {
        $otherItem = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $this->menuItem->menu_category_id,
            'price' => 30.00,
            'is_available' => true,
        ]);
        $firstResponse = $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $firstItemKey = $firstResponse->json('data.items.0.item_key');
        $this->postJson('/api/marketplace/cart', [
            'menu_item_id' => $otherItem->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->deleteJson("/api/marketplace/cart/{$firstItemKey}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.menu_item_id', $otherItem->id)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.subtotal', 60)
            ->assertSessionMissing("alabiq_cart.items.{$firstItemKey}");
    }

    public function test_updating_missing_cart_item_returns_400(): void
    {
        $this->putJson('/api/marketplace/cart/missing-item', ['quantity' => 2])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Item not found in cart');
    }
}
