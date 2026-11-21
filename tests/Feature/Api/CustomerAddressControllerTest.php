<?php

namespace Tests\Feature\Api;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAddressControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/customer/addresses')->assertUnauthorized();
    }

    public function test_index_lists_only_own_active_addresses(): void
    {
        $mine = CustomerAddress::factory()->count(2)->create(['user_id' => $this->user->id]);
        CustomerAddress::factory()->create(['user_id' => User::factory()->create()->id]);
        $inactive = CustomerAddress::factory()->create(['user_id' => $this->user->id, 'is_active' => false]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/customer/addresses');

        $response->assertOk()->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mine[0]->id));
        $this->assertFalse($ids->contains($inactive->id));
    }

    public function test_first_address_becomes_default(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/customer/addresses', [
            'street' => 'King Fahd Road',
            'city' => 'Riyadh',
        ]);

        $response->assertCreated()->assertJsonPath('data.is_default', true);
    }

    public function test_later_address_does_not_steal_default_implicitly(): void
    {
        Sanctum::actingAs($this->user);
        $first = CustomerAddress::factory()->create(['user_id' => $this->user->id, 'is_default' => true]);

        $response = $this->postJson('/api/customer/addresses', [
            'street' => 'Olaya Street',
            'city' => 'Riyadh',
        ]);

        $response->assertCreated()->assertJsonPath('data.is_default', false);
        $this->assertTrue($first->refresh()->is_default);
    }

    public function test_setting_is_default_demotes_previous_default(): void
    {
        Sanctum::actingAs($this->user);
        $first = CustomerAddress::factory()->create(['user_id' => $this->user->id, 'is_default' => true]);

        $response = $this->postJson('/api/customer/addresses', [
            'street' => 'Tahlia Street',
            'city' => 'Riyadh',
            'is_default' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.is_default', true);
        $this->assertFalse($first->refresh()->is_default);
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/customer/addresses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['street', 'city']);
    }

    public function test_store_validates_coordinate_ranges(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/customer/addresses', [
            'street' => 'King Fahd Road',
            'city' => 'Riyadh',
            'latitude' => 91,
            'longitude' => 200,
        ])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_store_accepts_manual_pin_coordinates(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/customer/addresses', [
            'street' => 'King Fahd Road',
            'city' => 'Riyadh',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'label' => 'Home',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.latitude', '24.71360000')
            ->assertJsonPath('data.label', 'Home');
    }

    public function test_user_cannot_view_anothers_address(): void
    {
        $address = CustomerAddress::factory()->create(['user_id' => User::factory()->create()->id]);

        Sanctum::actingAs($this->user);

        $this->getJson("/api/customer/addresses/{$address->id}")->assertForbidden();
        $this->putJson("/api/customer/addresses/{$address->id}", [
            'street' => 'Hacked Street',
            'city' => 'Hacked City',
        ])->assertForbidden();
        $this->deleteJson("/api/customer/addresses/{$address->id}")->assertForbidden();
    }

    public function test_user_can_update_own_address(): void
    {
        $address = CustomerAddress::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);

        $this->putJson("/api/customer/addresses/{$address->id}", [
            'street' => 'New Street',
            'city' => 'Jeddah',
        ])->assertOk()->assertJsonPath('data.street', 'New Street');
    }

    public function test_destroy_marks_address_inactive_and_hides_it(): void
    {
        $address = CustomerAddress::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);

        $this->deleteJson("/api/customer/addresses/{$address->id}")->assertOk();

        $this->assertFalse($address->refresh()->is_active);
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'is_active' => false,
        ]);
    }

    public function test_set_default_switches_default(): void
    {
        Sanctum::actingAs($this->user);
        $first = CustomerAddress::factory()->create(['user_id' => $this->user->id, 'is_default' => true]);
        $second = CustomerAddress::factory()->create(['user_id' => $this->user->id, 'is_default' => false]);

        $response = $this->patchJson("/api/customer/addresses/{$second->id}/default");

        $response->assertOk()->assertJsonPath('data.is_default', true);
        $this->assertFalse($first->refresh()->is_default);
        $this->assertTrue($second->refresh()->is_default);
    }

    public function test_set_default_on_anothers_address_is_forbidden(): void
    {
        $address = CustomerAddress::factory()->create(['user_id' => User::factory()->create()->id]);

        Sanctum::actingAs($this->user);

        $this->patchJson("/api/customer/addresses/{$address->id}/default")->assertForbidden();
    }
}
