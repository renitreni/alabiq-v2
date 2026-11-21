<?php

namespace Tests\Unit\Policies;

use App\Models\Restaurant;
use App\Models\User;
use App\Policies\RestaurantPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantPolicyTest extends TestCase
{
    use RefreshDatabase;

    private RestaurantPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RestaurantPolicy;
    }

    public function test_anyone_can_view_restaurant_listing(): void
    {
        $this->assertTrue($this->policy->viewAny(null));
        $this->assertTrue($this->policy->viewAny(User::factory()->make()));
    }

    public function test_active_restaurant_is_publicly_viewable(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);

        $this->assertTrue($this->policy->view(null, $restaurant));
        $this->assertTrue($this->policy->view(User::factory()->make(), $restaurant));
    }

    public function test_inactive_restaurant_only_owner_or_admin_can_view(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['is_active' => false, 'owner_id' => $owner->id]);

        $this->assertFalse($this->policy->view(null, $restaurant));
        $this->assertFalse($this->policy->view(User::factory()->create(), $restaurant));
        $this->assertTrue($this->policy->view($owner, $restaurant));
        $this->assertTrue($this->policy->view(
            User::factory()->role(User::ROLE_SUPER_ADMIN)->create(),
            $restaurant
        ));
    }

    public function test_only_restaurant_or_admin_roles_can_create(): void
    {
        $customer = User::factory()->role(User::ROLE_CUSTOMER)->make();
        $restaurant = User::factory()->role(User::ROLE_RESTAURANT)->make();
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->make();

        $this->assertFalse($this->policy->create($customer));
        $this->assertTrue($this->policy->create($restaurant));
        $this->assertTrue($this->policy->create($admin));
    }

    public function test_only_owner_or_admin_can_update(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_id' => $owner->id]);
        $stranger = User::factory()->create();

        $this->assertTrue($this->policy->update($owner, $restaurant));
        $this->assertTrue($this->policy->update(
            User::factory()->role(User::ROLE_SUPER_ADMIN)->create(),
            $restaurant
        ));
        $this->assertFalse($this->policy->update($stranger, $restaurant));
    }

    public function test_only_owner_or_admin_can_delete(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($this->policy->delete($owner, $restaurant));
        $this->assertFalse($this->policy->delete(User::factory()->create(), $restaurant));
    }

    public function test_policy_is_auto_discovered_for_authorization(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($owner->can('update', $restaurant));
        $this->assertFalse(User::factory()->create()->can('delete', $restaurant));
    }
}
