<?php

namespace Tests\Unit\Policies;

use App\Models\CustomerAddress;
use App\Models\User;
use App\Policies\CustomerAddressPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAddressPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CustomerAddressPolicy $policy;

    private User $user;

    private CustomerAddress $address;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CustomerAddressPolicy;

        $this->user = User::factory()->create();
        $this->address = CustomerAddress::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_any_authenticated_user_can_list_addresses(): void
    {
        $this->assertTrue($this->policy->viewAny(User::factory()->make()));
    }

    public function test_user_can_view_own_address(): void
    {
        $this->assertTrue($this->policy->view($this->user, $this->address));
    }

    public function test_other_user_cannot_view_address(): void
    {
        $this->assertFalse($this->policy->view(User::factory()->create(), $this->address));
    }

    public function test_any_authenticated_user_can_create_addresses(): void
    {
        $this->assertTrue($this->policy->create(User::factory()->make()));
    }

    public function test_user_can_update_and_delete_own_address(): void
    {
        $this->assertTrue($this->policy->update($this->user, $this->address));
        $this->assertTrue($this->policy->delete($this->user, $this->address));
    }

    public function test_other_user_cannot_update_or_delete(): void
    {
        $stranger = User::factory()->create();

        $this->assertFalse($this->policy->update($stranger, $this->address));
        $this->assertFalse($this->policy->delete($stranger, $this->address));
    }

    public function test_super_admin_has_full_access(): void
    {
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->create();

        $this->assertTrue($this->policy->view($admin, $this->address));
        $this->assertTrue($this->policy->update($admin, $this->address));
        $this->assertTrue($this->policy->delete($admin, $this->address));
    }

    public function test_policy_is_auto_discovered_for_authorization(): void
    {
        $this->assertTrue($this->user->can('update', $this->address));
        $this->assertFalse(User::factory()->create()->can('delete', $this->address));
    }
}
