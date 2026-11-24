<?php

namespace Tests\Feature\Api;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RestaurantSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        $this->restaurant = Restaurant::factory()->create([
            'owner_id' => $this->owner->id,
        ]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_restaurant_can_load_own_settings_and_payment_availability(): void
    {
        $settings = $this->app->make(SettingsService::class);
        $settings->set('cod_enabled', true);
        $settings->set('online_payments_enabled', true);

        Sanctum::actingAs($this->owner);

        $this->getJson('/api/restaurant/settings')
            ->assertOk()
            ->assertJsonPath('data.restaurant.id', $this->restaurant->id)
            ->assertJsonPath('data.restaurant.name', $this->restaurant->name)
            ->assertJsonCount(1, 'data.branches')
            ->assertJsonPath('data.branches.0.id', $this->branch->id)
            ->assertJsonPath('data.branches.0.opens_at', '00:00')
            ->assertJsonPath('data.branches.0.closes_at', '23:59')
            ->assertJsonPath('data.payments.cod_available', true)
            ->assertJsonPath('data.payments.online_payments_available', true);
    }

    public function test_restaurant_can_update_branch_operations(): void
    {
        Sanctum::actingAs($this->owner);

        $this->putJson("/api/restaurant/settings/branches/{$this->branch->id}", [
            'is_active' => false,
            'accepts_orders' => false,
            'opens_at' => '09:30',
            'closes_at' => '23:45',
            'delivery_fee' => 15.50,
            'minimum_order' => 75,
            'estimated_delivery_time' => 45,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $this->branch->id)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.accepts_orders', false)
            ->assertJsonPath('data.opens_at', '09:30')
            ->assertJsonPath('data.closes_at', '23:45')
            ->assertJsonPath('data.delivery_fee', 15.5)
            ->assertJsonPath('data.minimum_order', 75)
            ->assertJsonPath('data.estimated_delivery_time', 45);

        $this->assertDatabaseHas('restaurant_branches', [
            'id' => $this->branch->id,
            'is_active' => false,
            'accepts_orders' => false,
            'opens_at' => '09:30',
            'closes_at' => '23:45',
            'delivery_fee' => 15.50,
            'minimum_order' => 75,
            'estimated_delivery_time' => 45,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'action' => 'restaurant.branch_settings_updated',
            'auditable_type' => $this->branch->getMorphClass(),
            'auditable_id' => $this->branch->id,
        ]);
    }

    public function test_branch_settings_are_validated(): void
    {
        Sanctum::actingAs($this->owner);

        $this->putJson("/api/restaurant/settings/branches/{$this->branch->id}", [
            'is_active' => false,
            'accepts_orders' => true,
            'opens_at' => '25:00',
            'closes_at' => 'not-a-time',
            'delivery_fee' => -1,
            'minimum_order' => -1,
            'estimated_delivery_time' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'opens_at',
                'closes_at',
                'delivery_fee',
                'minimum_order',
                'estimated_delivery_time',
            ]);
    }

    public function test_restaurant_can_create_branch(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/restaurant/settings/branches', [
            'name' => 'Downtown Branch',
            'slug' => 'downtown-branch',
            'address' => '123 Main St',
            'phone' => '+1234567890',
            'opens_at' => '09:00',
            'closes_at' => '22:00',
            'delivery_fee' => 10.00,
            'minimum_order' => 50,
            'estimated_delivery_time' => 40,
            'is_active' => true,
            'accepts_orders' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Downtown Branch')
            ->assertJsonPath('data.slug', 'downtown-branch')
            ->assertJsonPath('data.address', '123 Main St')
            ->assertJsonPath('data.phone', '+1234567890')
            ->assertJsonPath('data.opens_at', '09:00')
            ->assertJsonPath('data.closes_at', '22:00')
            ->assertJsonPath('data.delivery_fee', 10)
            ->assertJsonPath('data.minimum_order', 50)
            ->assertJsonPath('data.estimated_delivery_time', 40)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.accepts_orders', true);

        $this->assertDatabaseHas('restaurant_branches', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Downtown Branch',
            'slug' => 'downtown-branch',
            'is_active' => true,
            'accepts_orders' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'action' => 'restaurant.branch_created',
            'auditable_type' => $this->branch->getMorphClass(),
        ]);
    }

    public function test_branch_creation_is_validated(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/restaurant/settings/branches', [
            'name' => '',
            'address' => '',
            'opens_at' => '25:00',
            'closes_at' => 'not-a-time',
            'delivery_fee' => -1,
            'minimum_order' => -1,
            'estimated_delivery_time' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'address',
                'opens_at',
                'closes_at',
                'delivery_fee',
                'minimum_order',
                'estimated_delivery_time',
            ]);
    }

    public function test_restaurant_cannot_update_another_restaurants_branch(): void
    {
        $otherBranch = RestaurantBranch::factory()->create();

        Sanctum::actingAs($this->owner);

        $this->putJson("/api/restaurant/settings/branches/{$otherBranch->id}", [
            'is_active' => false,
            'accepts_orders' => false,
            'opens_at' => '10:00',
            'closes_at' => '22:00',
            'delivery_fee' => 0,
            'minimum_order' => 0,
            'estimated_delivery_time' => 30,
        ])->assertNotFound();

        $this->assertDatabaseHas('restaurant_branches', [
            'id' => $otherBranch->id,
            'is_active' => $otherBranch->is_active,
        ]);
    }

    public function test_customer_cannot_access_restaurant_settings_api(): void
    {
        Sanctum::actingAs(User::factory()->role(User::ROLE_CUSTOMER)->create());

        $this->getJson('/api/restaurant/settings')->assertForbidden();
        $this->putJson("/api/restaurant/settings/branches/{$this->branch->id}", [])->assertForbidden();
    }

    public function test_restaurant_user_without_a_restaurant_gets_not_found(): void
    {
        Sanctum::actingAs(User::factory()->role(User::ROLE_RESTAURANT)->create());

        $this->getJson('/api/restaurant/settings')->assertNotFound();
    }
}
