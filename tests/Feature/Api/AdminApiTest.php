<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantSettlement;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->create();
    }

    public function test_admin_endpoints_require_authentication(): void
    {
        $this->getJson('/api/admin/stats')->assertUnauthorized();
        $this->getJson('/api/admin/users')->assertUnauthorized();
        $this->getJson('/api/admin/settlements')->assertUnauthorized();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $customer = User::factory()->role(User::ROLE_CUSTOMER)->create();
        Sanctum::actingAs($customer);

        $this->getJson('/api/admin/stats')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->patchJson("/api/admin/users/{$this->admin->id}/role", ['role' => 'customer'])->assertForbidden();
    }

    public function test_admin_gets_platform_stats(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/stats')->assertOk();

        $response->assertJsonStructure([
            'data' => ['users', 'restaurants', 'riders', 'orders', 'gmv', 'ledger', 'settlements'],
        ]);
    }

    public function test_admin_lists_and_updates_user_role_with_audit(): void
    {
        Sanctum::actingAs($this->admin);
        $target = User::factory()->role(User::ROLE_CUSTOMER)->create();

        $this->getJson('/api/admin/users')->assertOk()->assertJsonFragment(['id' => $target->id]);

        $this->patchJson("/api/admin/users/{$target->id}/role", ['role' => User::ROLE_RESTAURANT])
            ->assertOk()
            ->assertJsonPath('data.role', User::ROLE_RESTAURANT);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => User::ROLE_RESTAURANT]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_updated', 'user_id' => $this->admin->id]);
    }

    public function test_invalid_role_is_rejected(): void
    {
        Sanctum::actingAs($this->admin);
        $target = User::factory()->create();

        $this->patchJson("/api/admin/users/{$target->id}/role", ['role' => 'wizard'])->assertUnprocessable();
    }

    public function test_rider_activation_toggle(): void
    {
        Sanctum::actingAs($this->admin);
        $rider = Rider::factory()->create(['is_active' => true]);

        $this->patchJson("/api/admin/riders/{$rider->id}/approval", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'rider.activation_updated']);
    }

    public function test_settings_update_flow(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/admin/settings', [
            'settings' => ['vat_rate' => 0.05, 'cod_enabled' => false],
        ])->assertOk()
            ->assertJsonPath('data.vat_rate', 0.05)
            ->assertJsonPath('data.cod_enabled', false);

        $this->assertDatabaseHas('system_settings', ['key' => 'vat_rate', 'type' => 'numeric']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_unknown_setting_key_returns_422(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/admin/settings', [
            'settings' => ['not_a_real_key' => 'x'],
        ])->assertUnprocessable();
    }

    public function test_full_settlement_flow_via_api(): void
    {
        Sanctum::actingAs($this->admin);
        $restaurant = Restaurant::factory()->create();
        Order::factory()->delivered()->create([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 200,
        ]);

        $generate = $this->postJson('/api/admin/settlements/generate', [
            'restaurant_id' => $restaurant->id,
            'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $settlementId = $generate->json('data.id');

        $pay = $this->postJson("/api/admin/settlements/{$settlementId}/pay")->assertOk();
        $this->assertSame('paid', $pay->json('data.status'));

        // Second pay is a conflict.
        $this->postJson("/api/admin/settlements/{$settlementId}/pay")->assertConflict();

        $this->getJson('/api/admin/settlements?status=paid')
            ->assertOk()
            ->assertJsonFragment(['id' => $settlementId]);
    }

    public function test_restaurant_reads_own_settlements_only(): void
    {
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        $restaurant = Restaurant::factory()->create(['owner_id' => $owner->id]);
        RestaurantSettlement::factory()->create(['restaurant_id' => $restaurant->id]);
        RestaurantSettlement::factory()->create(); // another restaurant

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/restaurant/settlements')->assertOk();

        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_restaurant_sales_report_is_scoped_to_own_restaurant(): void
    {
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        $restaurant = Restaurant::factory()->create(['owner_id' => $owner->id]);
        Order::factory()->delivered()->create(['restaurant_id' => $restaurant->id, 'subtotal' => 300]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/restaurant/reports/sales')
            ->assertOk()
            ->assertJsonPath('data.restaurant_id', $restaurant->id)
            ->assertJsonPath('data.gross_amount', 300)
            ->assertJsonPath('data.orders_count', 1);
    }

    public function test_rider_earnings_report_is_scoped_to_own_profile(): void
    {
        $riderUser = User::factory()->role(User::ROLE_RIDER)->create();
        $rider = Rider::factory()->create(['user_id' => $riderUser->id]);
        DeliveryAssignment::factory()->completed()->create(['rider_id' => $rider->id, 'delivery_fee' => 20]);
        DeliveryAssignment::factory()->completed()->create(['delivery_fee' => 99]);

        Sanctum::actingAs($riderUser);

        $this->getJson('/api/rider/reports/earnings')
            ->assertOk()
            ->assertJsonPath('data.completed_deliveries', 1)
            ->assertJsonPath('data.earnings', 20);

        $this->getJson('/api/rider/reports/deliveries')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_audit_log_listing(): void
    {
        Sanctum::actingAs($this->admin);
        AuditLog::factory()->count(3)->create();

        $this->getJson('/api/admin/audit-logs')->assertOk()->assertJsonCount(3, 'data');
    }
}
