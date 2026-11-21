<?php

namespace Tests\Unit\Policies;

use App\Models\Rider;
use App\Models\User;
use App\Policies\RiderPolicy;
use Tests\TestCase;

class RiderPolicyTest extends TestCase
{
    private RiderPolicy $policy;

    private Rider $rider;

    private User $riderUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RiderPolicy;
        $this->riderUser = User::factory()->role(User::ROLE_RIDER)->make(['id' => 1001]);
        $this->rider = Rider::factory()->make(['user_id' => $this->riderUser->id]);
    }

    public function test_owner_can_view(): void
    {
        $this->assertTrue($this->policy->view($this->riderUser, $this->rider));
        $this->assertTrue($this->policy->updateAvailability($this->riderUser, $this->rider));
        $this->assertTrue($this->policy->updateLocation($this->riderUser, $this->rider));
    }

    public function test_other_rider_cannot_view_or_update(): void
    {
        $other = User::factory()->role(User::ROLE_RIDER)->make(['id' => 1002]);

        $this->assertFalse($this->policy->view($other, $this->rider));
        $this->assertFalse($this->policy->updateAvailability($other, $this->rider));
        $this->assertFalse($this->policy->updateLocation($other, $this->rider));
    }

    public function test_customer_cannot_manage_rider(): void
    {
        $customer = User::factory()->role(User::ROLE_CUSTOMER)->make(['id' => 1003]);

        $this->assertFalse($this->policy->view($customer, $this->rider));
        $this->assertFalse($this->policy->updateAvailability($customer, $this->rider));
        $this->assertFalse($this->policy->updateLocation($customer, $this->rider));
    }

    public function test_super_admin_can_view_but_not_act_as_rider(): void
    {
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->make(['id' => 1004]);

        $this->assertTrue($this->policy->view($admin, $this->rider));
        $this->assertFalse($this->policy->updateAvailability($admin, $this->rider));
        $this->assertFalse($this->policy->updateLocation($admin, $this->rider));
    }
}
