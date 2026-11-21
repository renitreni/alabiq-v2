<?php

namespace Tests\Unit\Services;

use App\Models\DeliveryAssignment;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantSettlement;
use App\Models\Rider;
use App\Models\User;
use App\Services\AdminStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformStatsTest extends TestCase
{
    use RefreshDatabase;

    private AdminStatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminStatsService;
    }

    public function test_counts_users_by_role(): void
    {
        User::factory()->count(3)->role(User::ROLE_CUSTOMER)->create();
        User::factory()->role(User::ROLE_RESTAURANT)->create();
        User::factory()->role(User::ROLE_SUPER_ADMIN)->create();

        $stats = $this->service->platformStatistics();

        $this->assertSame(5, $stats['users']['total']);
        $this->assertSame(3, $stats['users']['by_role']['customer']);
        $this->assertSame(1, $stats['users']['by_role']['restaurant']);
        $this->assertSame(1, $stats['users']['by_role']['super_admin']);
    }

    public function test_restaurant_approval_counts(): void
    {
        Restaurant::factory()->count(3)->create(['is_approved' => true]);
        Restaurant::factory()->create(['is_approved' => false]);

        $stats = $this->service->platformStatistics();

        $this->assertSame(4, $stats['restaurants']['total']);
        $this->assertSame(3, $stats['restaurants']['approved']);
        $this->assertSame(1, $stats['restaurants']['pending_approval']);
    }

    public function test_rider_online_and_delivering_counts(): void
    {
        Rider::factory()->online()->create();
        Rider::factory()->online()->create();
        Rider::factory()->offline()->create();
        $busy = Rider::factory()->online()->create();
        DeliveryAssignment::factory()->accepted()->create(['rider_id' => $busy->id]);

        $stats = $this->service->platformStatistics();

        $this->assertSame(4, $stats['riders']['total']);
        $this->assertSame(3, $stats['riders']['online']);
        $this->assertSame(1, $stats['riders']['delivering_now']);
    }

    public function test_gmv_sums_only_delivered_orders(): void
    {
        Order::factory()->delivered()->create(['total_amount' => 100]);
        Order::factory()->delivered()->create(['total_amount' => 250]);
        Order::factory()->placed()->create(['total_amount' => 999]);

        $stats = $this->service->platformStatistics();

        $this->assertSame(350.0, $stats['gmv']['delivered_total']);
    }

    public function test_pending_settlements_rollup(): void
    {
        RestaurantSettlement::factory()->create(['net_amount' => 100]);
        RestaurantSettlement::factory()->create(['net_amount' => 250]);
        RestaurantSettlement::factory()->paid()->create(['net_amount' => 400]);

        $stats = $this->service->platformStatistics();

        $this->assertSame(2, $stats['settlements']['pending_count']);
        $this->assertSame(350.0, $stats['settlements']['pending_amount']);
    }

    public function test_ledger_balances_are_exposed(): void
    {
        Order::factory()->delivered()->create(['subtotal' => 100]);
        LedgerEntry::create([
            'order_id' => Order::first()->id,
            'account' => LedgerEntry::ACCOUNT_PLATFORM_COMMISSION,
            'type' => LedgerEntry::TYPE_CREDIT,
            'amount' => 15,
            'balance_after' => 15,
            'reference' => 'test_1',
        ]);

        $stats = $this->service->platformStatistics();

        $this->assertSame(15.0, $stats['ledger']['platform_commission']);
        $this->assertSame(0.0, $stats['ledger']['settlement_payout']);
    }
}
