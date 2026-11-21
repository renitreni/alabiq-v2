<?php

namespace Tests\Unit\Policies;

use App\Models\Restaurant;
use App\Models\RestaurantSettlement;
use App\Models\User;
use App\Policies\RestaurantSettlementPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementPolicyTest extends TestCase
{
    use RefreshDatabase;

    private RestaurantSettlementPolicy $policy;

    private Restaurant $restaurant;

    private RestaurantSettlement $settlement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RestaurantSettlementPolicy;
        $this->restaurant = Restaurant::factory()->make(['id' => 10, 'owner_id' => 100]);
        $this->settlement = RestaurantSettlement::factory()->make([
            'id' => 1,
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_restaurant_owner_can_view_own_settlement(): void
    {
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->make(['id' => 100]);
        $owner->setRelation('restaurant', $this->restaurant);

        $this->assertTrue($this->policy->view($owner, $this->settlement));
    }

    public function test_other_restaurant_cannot_view_settlement(): void
    {
        $other = User::factory()->role(User::ROLE_RESTAURANT)->make(['id' => 200]);
        $other->setRelation('restaurant', Restaurant::factory()->make(['id' => 11, 'owner_id' => 200]));

        $this->assertFalse($this->policy->view($other, $this->settlement));
    }

    public function test_customer_cannot_view_or_pay(): void
    {
        $customer = User::factory()->role(User::ROLE_CUSTOMER)->make(['id' => 300]);

        $this->assertFalse($this->policy->view($customer, $this->settlement));
        $this->assertFalse($this->policy->pay($customer, $this->settlement));
        $this->assertFalse($this->policy->create($customer));
        $this->assertFalse($this->policy->viewAny($customer));
    }

    public function test_super_admin_has_full_access(): void
    {
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->make(['id' => 400]);

        $this->assertTrue($this->policy->view($admin, $this->settlement));
        $this->assertTrue($this->policy->pay($admin, $this->settlement));
        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->viewAny($admin));
    }
}
