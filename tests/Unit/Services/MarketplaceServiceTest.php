<?php

namespace Tests\Unit\Services;

use App\Models\Cuisine;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantDeliveryZone;
use App\Models\User;
use App\Services\MarketplaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceServiceTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceService $service;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private Cuisine $cuisine;

    private MenuCategory $category;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MarketplaceService;

        $this->owner = User::factory()->create();

        $this->restaurant = Restaurant::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'city' => 'Riyadh',
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
        ]);

        $this->cuisine = Cuisine::factory()->create([
            'name' => 'Burgers',
            'slug' => 'burgers',
            'is_active' => true,
        ]);
        $this->restaurant->cuisines()->attach($this->cuisine->id);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'is_active' => true,
            'accepts_orders' => true,
            'delivery_fee' => 10.00,
            'minimum_order' => 20.00,
        ]);

        $this->category = MenuCategory::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Burgers',
            'slug' => 'burgers',
            'is_active' => true,
        ]);

        $this->menuItem = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $this->category->id,
            'name' => 'Cheeseburger',
            'slug' => 'cheeseburger',
            'price' => 25.00,
            'is_available' => true,
        ]);
    }

    public function test_get_restaurant_with_menu_returns_storefront_data(): void
    {
        $data = $this->service->getRestaurantWithMenu('test-restaurant');

        $this->assertNotNull($data);
        $this->assertEquals('Test Restaurant', $data['restaurant']['name']);
        $this->assertEquals($this->branch->id, $data['branch']['id']);
        $this->assertSame(10.0, $data['branch']['delivery_fee']);
        $this->assertSame(20.0, $data['branch']['minimum_order']);
        $this->assertCount(1, $data['categories']);
        $this->assertEquals('Cheeseburger', $data['categories'][0]['items'][0]->name);
    }

    public function test_get_restaurant_with_menu_returns_null_for_unknown_slug(): void
    {
        $this->assertNull($this->service->getRestaurantWithMenu('does-not-exist'));
    }

    public function test_get_restaurant_with_menu_filters_items_unavailable_at_branch(): void
    {
        $unavailable = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $this->category->id,
            'name' => 'Branch Only Item',
            'slug' => 'branch-only-item',
            'is_available' => true,
        ]);
        $unavailable->availabilities()->create([
            'restaurant_branch_id' => $this->branch->id,
            'is_available' => false,
        ]);

        $data = $this->service->getRestaurantWithMenu('test-restaurant', $this->branch->id);

        $itemNames = $data['categories'][0]['items']->pluck('name');
        $this->assertTrue($itemNames->contains('Cheeseburger'));
        $this->assertFalse($itemNames->contains('Branch Only Item'));
    }

    public function test_get_restaurant_with_menu_applies_branch_price_override(): void
    {
        $this->menuItem->availabilities()->create([
            'restaurant_branch_id' => $this->branch->id,
            'is_available' => true,
            'price_override' => 19.99,
        ]);

        $data = $this->service->getRestaurantWithMenu('test-restaurant', $this->branch->id);

        $this->assertEquals(19.99, (float) $data['categories'][0]['items'][0]->branch_price);
    }

    public function test_check_delivery_coverage_returns_zone_terms(): void
    {
        RestaurantDeliveryZone::factory()->create([
            'restaurant_branch_id' => $this->branch->id,
            'zone_type' => 'circle',
            'coordinates' => [
                'center' => ['lat' => 24.7100, 'lng' => 46.6100],
                'radius' => 3000,
            ],
            'delivery_fee' => 5.00,
            'priority' => 1,
            'is_active' => true,
        ]);

        $inside = $this->service->checkDeliveryCoverage($this->branch->id, 24.7100, 46.6100);
        $this->assertTrue($inside['covered']);
        $this->assertSame(5.0, $inside['delivery_fee']);

        $outside = $this->service->checkDeliveryCoverage($this->branch->id, 24.9000, 46.9000);
        $this->assertTrue($outside['covered']);
        $this->assertSame(10.0, $outside['delivery_fee']);
    }
}
