<?php

namespace Tests\Unit\Services;

use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PlatformCommission;
use App\Models\Restaurant;
use App\Models\RestaurantSettlement;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    private SettlementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SettlementService;
    }

    public function test_generate_creates_pending_settlement_from_delivered_orders(): void
    {
        $restaurant = Restaurant::factory()->create();
        Order::factory()->delivered()->create([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 200,
        ]);

        $settlement = $this->service->generate($restaurant, now()->subWeek(), now());

        $this->assertSame(RestaurantSettlement::STATUS_PENDING, $settlement->status);
        $this->assertSame(1, $settlement->orders_count);
        $this->assertSame(200.0, (float) $settlement->gross_amount);
        $this->assertSame(30.0, (float) $settlement->commission_amount); // 15%
        $this->assertSame(170.0, (float) $settlement->net_amount);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settlement.generated']);
    }

    public function test_generate_is_idempotent_per_restaurant_and_period(): void
    {
        $restaurant = Restaurant::factory()->create();
        Order::factory()->delivered()->create(['restaurant_id' => $restaurant->id, 'subtotal' => 200]);

        $first = $this->service->generate($restaurant, now()->subWeek(), now());
        $second = $this->service->generate($restaurant, now()->subWeek(), now());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, RestaurantSettlement::count());
    }

    public function test_generate_excludes_non_delivered_and_out_of_window_orders(): void
    {
        $restaurant = Restaurant::factory()->create();
        Order::factory()->placed()->create(['restaurant_id' => $restaurant->id, 'subtotal' => 500]);
        Order::factory()->delivered()->create([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 100,
            'delivered_at' => now()->subDays(30),
        ]);

        $this->expectException(\DomainException::class);
        $this->service->generate($restaurant, now()->subWeek(), now());
    }

    public function test_generate_rejects_reversed_period(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->expectException(\DomainException::class);
        $this->service->generate($restaurant, now(), now()->subWeek());
    }

    public function test_override_rate_applies_to_settlement(): void
    {
        $restaurant = Restaurant::factory()->create();
        PlatformCommission::factory()->create(['restaurant_id' => $restaurant->id, 'rate' => 10.00]);
        Order::factory()->delivered()->create(['restaurant_id' => $restaurant->id, 'subtotal' => 200]);

        $settlement = $this->service->generate($restaurant, now()->subWeek(), now());

        $this->assertSame(20.0, (float) $settlement->commission_amount);
        $this->assertSame(180.0, (float) $settlement->net_amount);
    }

    public function test_mark_paid_posts_balanced_ledger_journals(): void
    {
        $restaurant = Restaurant::factory()->create();
        Order::factory()->delivered()->create(['restaurant_id' => $restaurant->id, 'subtotal' => 200]);
        $order = Order::first();
        $settlement = $this->service->generate($restaurant, now()->subWeek(), now());

        $paid = $this->service->markPaid($settlement);

        $this->assertSame(RestaurantSettlement::STATUS_PAID, $paid->status);
        $this->assertNotNull($paid->paid_at);

        // One balanced journal per order: debit clearing == credit payout.
        $entries = LedgerEntry::where('reference', "settlement_{$settlement->id}_order_{$order->id}")->get();
        $this->assertCount(2, $entries);
        $this->assertSame(170.0, (float) $entries->where('type', LedgerEntry::TYPE_DEBIT)->sum('amount'));
        $this->assertSame(170.0, (float) $entries->where('type', LedgerEntry::TYPE_CREDIT)->sum('amount'));
        $this->assertDatabaseHas('ledger_entries', ['account' => LedgerEntry::ACCOUNT_SETTLEMENT_PAYOUT]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settlement.paid']);
    }

    public function test_cannot_pay_twice(): void
    {
        $restaurant = Restaurant::factory()->create();
        Order::factory()->delivered()->create(['restaurant_id' => $restaurant->id]);
        $settlement = $this->service->generate($restaurant, now()->subWeek(), now());
        $this->service->markPaid($settlement);

        $this->expectException(\DomainException::class);
        $this->service->markPaid($settlement->fresh());
    }

    public function test_paid_period_is_not_regenerated(): void
    {
        $restaurant = Restaurant::factory()->create();
        Order::factory()->delivered()->create(['restaurant_id' => $restaurant->id, 'subtotal' => 100]);
        $settlement = $this->service->generate($restaurant, now()->subWeek(), now());
        $this->service->markPaid($settlement);

        // Same period, new generation attempt: existing (paid) row is returned.
        $regenerated = $this->service->generate($restaurant, now()->subWeek(), now());
        $this->assertSame($settlement->id, $regenerated->id);
    }

    public function test_generate_writes_audit_log_with_actor(): void
    {
        $restaurant = Restaurant::factory()->create();
        Order::factory()->delivered()->create(['restaurant_id' => $restaurant->id]);
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->create();

        $this->service->generate($restaurant, now()->subWeek(), now(), $admin, '127.0.0.1');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'settlement.generated',
        ]);
        $this->assertSame(1, AuditLog::count());
    }
}
