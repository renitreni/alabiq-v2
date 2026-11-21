<?php

namespace Tests\Unit\Services;

use App\Models\Cuisine;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
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
}
