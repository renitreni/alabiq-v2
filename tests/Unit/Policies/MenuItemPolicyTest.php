<?php

namespace Tests\Unit\Policies;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use App\Policies\MenuItemPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemPolicyTest extends TestCase
{
    use RefreshDatabase;

    private MenuItemPolicy $policy;

    private User $owner;

    private Restaurant $restaurant;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new MenuItemPolicy;

        $this->owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        $this->restaurant = Restaurant::factory()->create(['owner_id' => $this->owner->id]);
        $category = MenuCategory::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->menuItem = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'menu_category_id' => $category->id,
            'is_available' => true,
        ]);
    }

    public function test_menu_browsing_is_public(): void
    {
        $this->assertTrue($this->policy->viewAny(null));
        $this->assertTrue($this->policy->viewAny(User::factory()->make()));
    }

    public function test_available_item_is_publicly_viewable(): void
    {
        $this->assertTrue($this->policy->view(null, $this->menuItem));
    }

    public function test_unavailable_item_visible_only_to_manager(): void
    {
        $this->menuItem->update(['is_available' => false]);

        $this->assertFalse($this->policy->view(null, $this->menuItem));
        $this->assertFalse($this->policy->view(User::factory()->create(), $this->menuItem));
        $this->assertTrue($this->policy->view($this->owner, $this->menuItem));
        $this->assertTrue($this->policy->view(
            User::factory()->role(User::ROLE_SUPER_ADMIN)->create(),
            $this->menuItem
        ));
    }

    public function test_owner_can_create_update_delete_items(): void
    {
        $newItem = MenuItem::factory()->make([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->assertTrue($this->policy->create($this->owner, $newItem));
        $this->assertTrue($this->policy->update($this->owner, $this->menuItem));
        $this->assertTrue($this->policy->delete($this->owner, $this->menuItem));
    }

    public function test_other_restaurant_owner_cannot_manage_items(): void
    {
        $otherOwner = User::factory()->role(User::ROLE_RESTAURANT)->create();

        $this->assertFalse($this->policy->update($otherOwner, $this->menuItem));
        $this->assertFalse($this->policy->delete($otherOwner, $this->menuItem));
        $this->assertFalse($this->policy->create(
            $otherOwner,
            MenuItem::factory()->make(['restaurant_id' => $this->restaurant->id])
        ));
    }

    public function test_customer_cannot_manage_items(): void
    {
        $customer = User::factory()->role(User::ROLE_CUSTOMER)->create();

        $this->assertFalse($this->policy->update($customer, $this->menuItem));
        $this->assertFalse($this->policy->delete($customer, $this->menuItem));
    }

    public function test_super_admin_can_manage_any_item(): void
    {
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->create();

        $this->assertTrue($this->policy->update($admin, $this->menuItem));
        $this->assertTrue($this->policy->delete($admin, $this->menuItem));
    }

    public function test_policy_is_auto_discovered_for_authorization(): void
    {
        $this->assertTrue($this->owner->can('update', $this->menuItem));
        $this->assertFalse(User::factory()->create()->can('update', $this->menuItem));
    }
}
