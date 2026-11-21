<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new UserPolicy;
    }

    public function test_super_admin_can_manage_other_users(): void
    {
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->make(['id' => 1]);
        $target = User::factory()->role(User::ROLE_CUSTOMER)->make(['id' => 2]);

        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->updateRole($admin, $target));
        $this->assertTrue($this->policy->deactivate($admin, $target));
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->make(['id' => 1]);

        $this->assertFalse($this->policy->updateRole($admin, $admin));
        $this->assertFalse($this->policy->deactivate($admin, $admin));
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $customer = User::factory()->role(User::ROLE_CUSTOMER)->make(['id' => 3]);
        $restaurant = User::factory()->role(User::ROLE_RESTAURANT)->make(['id' => 4]);
        $target = User::factory()->role(User::ROLE_CUSTOMER)->make(['id' => 5]);

        $this->assertFalse($this->policy->viewAny($customer));
        $this->assertFalse($this->policy->updateRole($customer, $target));
        $this->assertFalse($this->policy->deactivate($customer, $target));
        $this->assertFalse($this->policy->viewAny($restaurant));
        $this->assertFalse($this->policy->updateRole($restaurant, $target));
    }
}
