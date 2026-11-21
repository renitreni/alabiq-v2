<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\PlatformCommission;
use App\Models\Restaurant;
use App\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private ReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportingService;
    }

    public function test_sales_report_sums_delivered_orders_only(): void
    {
        $restaurant = Restaurant::factory()->create();
        Order::factory()->delivered()->create([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 100,
        ]);
        Order::factory()->delivered()->create([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 300,
        ]);
        Order::factory()->placed()->create([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 500,
        ]);

        $report = $this->service->restaurantSales($restaurant);

        $this->assertSame(2, $report['orders_count']);
        $this->assertSame(400.0, $report['gross_amount']);
        // Default 15% commission.
        $this->assertSame(60.0, $report['commission_amount']);
        $this->assertSame(340.0, $report['net_amount']);
        $this->assertSame(200.0, $report['average_order_value']);
        $this->assertArrayHasKey('placed', $report['by_status']);
        $this->assertSame(1, $report['by_status']['placed']);
    }

    public function test_sales_report_respects_date_window(): void
    {
        $restaurant = Restaurant::factory()->create();
        Order::factory()->delivered()->create([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 100,
            'delivered_at' => now()->subDays(10),
        ]);
        Order::factory()->delivered()->create([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 300,
            'delivered_at' => now(),
        ]);

        $report = $this->service->restaurantSales(
            $restaurant,
            now()->subWeek(),
            now()
        );

        $this->assertSame(1, $report['orders_count']);
        $this->assertSame(300.0, $report['gross_amount']);
    }

    public function test_restaurant_commission_override_is_applied(): void
    {
        $restaurant = Restaurant::factory()->create();
        PlatformCommission::factory()->create([
            'restaurant_id' => $restaurant->id,
            'rate' => 20.00,
        ]);
        Order::factory()->delivered()->create([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 200,
        ]);

        $report = $this->service->restaurantSales($restaurant);

        $this->assertSame(40.0, $report['commission_amount']);
        $this->assertSame(160.0, $report['net_amount']);
    }

    public function test_inactive_override_falls_back_to_config_rate(): void
    {
        $restaurant = Restaurant::factory()->create();
        PlatformCommission::factory()->create([
            'restaurant_id' => $restaurant->id,
            'rate' => 20.00,
            'is_active' => false,
        ]);
        Order::factory()->delivered()->create([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 200,
        ]);

        $report = $this->service->restaurantSales($restaurant);

        $this->assertSame(30.0, $report['commission_amount']); // 15% default
    }

    public function test_empty_restaurant_has_zero_report(): void
    {
        $restaurant = Restaurant::factory()->create();

        $report = $this->service->restaurantSales($restaurant);

        $this->assertSame(0, $report['orders_count']);
        $this->assertSame(0.0, $report['gross_amount']);
        $this->assertSame(0.0, $report['average_order_value']);
    }
}
