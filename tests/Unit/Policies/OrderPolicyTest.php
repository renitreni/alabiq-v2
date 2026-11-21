<?php

namespace Tests\Unit\Policies;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    private OrderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new OrderPolicy;
    }

    public function test_any_authenticated_user_can_list_orders(): void
    {
        $this->assertTrue($this->policy->viewAny(User::factory()->make()));
    }

    public function test_customer_can_view_own_order(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);

        $this->assertTrue($this->policy->view($customer, $order));
    }

    public function test_other_customer_cannot_view_order(): void
    {
        $order = Order::factory()->create();

        $this->assertFalse($this->policy->view(User::factory()->create(), $order));
    }

    public function test_restaurant_owner_can_view_and_update_order(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_id' => $owner->id]);
        $order = Order::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->assertTrue($this->policy->view($owner, $order));
        $this->assertTrue($this->policy->update($owner, $order));
        $this->assertTrue($this->policy->cancel($owner, $order));
    }

    public function test_customer_cannot_update_order(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);

        $this->assertFalse($this->policy->update($customer, $order));
    }

    public function test_customer_can_cancel_own_order(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);

        $this->assertTrue($this->policy->cancel($customer, $order));
        $this->assertFalse($this->policy->cancel(User::factory()->create(), $order));
    }

    public function test_super_admin_has_full_access(): void
    {
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->create();
        $order = Order::factory()->create();

        $this->assertTrue($this->policy->view($admin, $order));
        $this->assertTrue($this->policy->update($admin, $order));
        $this->assertTrue($this->policy->cancel($admin, $order));
        $this->assertTrue($this->policy->create($admin));
    }

    public function test_only_customer_or_admin_can_create_orders(): void
    {
        $customer = User::factory()->role(User::ROLE_CUSTOMER)->make();
        $rider = User::factory()->role(User::ROLE_RIDER)->make();
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->make();

        $this->assertTrue($this->policy->create($customer));
        $this->assertFalse($this->policy->create($rider));
        $this->assertTrue($this->policy->create($admin));
    }

    public function test_policy_is_auto_discovered_for_authorization(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);

        $this->assertTrue($customer->can('view', $order));
        $this->assertFalse(User::factory()->create()->can('cancel', $order));
    }
}
