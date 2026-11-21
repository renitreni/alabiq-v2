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
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function test_search_restaurants_returns_paginated_results()
    {
        $result = $this->service->searchRestaurants([], 10);
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(1, $result->total());
        $this->assertEquals('Test Restaurant', $result->first()->name);
    }

    public function test_search_restaurants_filters_by_keyword()
    {
        Restaurant::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Pizza Place',
            'slug' => 'pizza-place',
            'city' => 'Jeddah',
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
        ]);

        $result = $this->service->searchRestaurants(['keyword' => 'Test'], 10);
        $this->assertEquals(1, $result->total());
        $this->assertEquals('Test Restaurant', $result->first()->name);
    }

    public function test_search_restaurants_filters_by_cuisine()
    {
        Restaurant::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Sushi Bar',
            'slug' => 'sushi-bar',
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
        ]);

        $result = $this->service->searchRestaurants(['cuisine_id' => $this->cuisine->id], 10);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('Test Restaurant', $result->first()->name);
    }

    public function test_search_restaurants_filters_by_city()
    {
        Restaurant::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Jeddah Eats',
            'slug' => 'jeddah-eats',
            'city' => 'Jeddah',
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
        ]);

        $result = $this->service->searchRestaurants(['city' => 'Riyadh'], 10);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('Riyadh', $result->first()->city);
    }

    public function test_search_restaurants_filters_by_max_delivery_fee()
    {
        // Branch delivery_fee is 10.00 (set in setUp).
        $included = $this->service->searchRestaurants(['max_delivery_fee' => 15], 10);
        $this->assertEquals(1, $included->total());

        $excluded = $this->service->searchRestaurants(['max_delivery_fee' => 5], 10);
        $this->assertEquals(0, $excluded->total());
    }

    public function test_search_restaurants_sort_by_is_accepted(): void
    {
        foreach (['rating', 'delivery_time', 'delivery_fee', 'newest', 'relevance'] as $sortBy) {
            $result = $this->service->searchRestaurants(['sort_by' => $sortBy], 10);
            $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        }
    }

    public function test_search_excludes_inactive_unapproved_or_closed_restaurants(): void
    {
        Restaurant::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Inactive Place',
            'slug' => 'inactive-place',
            'is_active' => false,
        ]);
        Restaurant::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Unapproved Place',
            'slug' => 'unapproved-place',
            'is_approved' => false,
        ]);
        Restaurant::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Closed Place',
            'slug' => 'closed-place',
            'accepts_orders' => false,
        ]);

        $result = $this->service->searchRestaurants([], 10);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('Test Restaurant', $result->first()->name);
    }

    public function test_get_featured_restaurants_returns_active_only_and_respects_limit(): void
    {
        Restaurant::factory()->count(3)->create([
            'owner_id' => $this->owner->id,
            'is_active' => false,
        ]);

        $featured = $this->service->getFeaturedRestaurants(5);

        $this->assertLessThanOrEqual(5, $featured->count());
        $this->assertTrue($featured->every(fn ($r) => $r->is_active && $r->is_approved && $r->accepts_orders));
    }

    public function test_get_popular_items_returns_available_popular_items(): void
    {
        $this->menuItem->update(['is_popular' => true]);

        MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $this->category->id,
            'name' => 'Unpopular Salad',
            'slug' => 'unpopular-salad',
            'is_popular' => false,
            'is_available' => true,
        ]);
        MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $this->category->id,
            'name' => 'Sold Out Wings',
            'slug' => 'sold-out-wings',
            'is_popular' => true,
            'is_available' => false,
        ]);

        $popular = $this->service->getPopularItems(10);

        $this->assertGreaterThanOrEqual(1, $popular->count());
        $this->assertTrue($popular->every(fn ($i) => $i->is_popular && $i->is_available));
        $this->assertEquals('Cheeseburger', $popular->firstWhere('name', 'Cheeseburger')?->name);
    }

    public function test_get_all_cuisines_returns_active_cuisines(): void
    {
        Cuisine::factory()->create([
            'name' => 'Inactive Cuisine',
            'slug' => 'inactive-cuisine',
            'is_active' => false,
        ]);

        $cuisines = $this->service->getAllCuisines();

        $this->assertEquals(1, $cuisines->count());
        $this->assertEquals('Burgers', $cuisines->first()->name);
    }

    public function test_get_restaurant_with_menu_returns_storefront_data(): void
    {
        $data = $this->service->getRestaurantWithMenu('test-restaurant');

        $this->assertNotNull($data);
        $this->assertEquals('Test Restaurant', $data['restaurant']['name']);
        $this->assertEquals($this->branch->id, $data['branch']['id']);
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

    public function test_get_nearby_restaurants_within_radius_only(): void
    {
        $this->branch->update(['latitude' => 24.7100, 'longitude' => 46.6100]);

        $farRestaurant = Restaurant::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Far Away',
            'slug' => 'far-away',
        ]);
        RestaurantBranch::factory()->create([
            'restaurant_id' => $farRestaurant->id,
            'name' => 'Far Branch',
            'slug' => 'far-branch',
            'latitude' => 24.0000,
            'longitude' => 46.0000,
            'is_active' => true,
            'accepts_orders' => true,
        ]);

        $nearby = $this->service->getNearbyRestaurants(24.7100, 46.6100, 5, 10);

        $this->assertEquals(1, $nearby->count());
        $this->assertEquals('Test Restaurant', $nearby->first()->name);
    }
}
