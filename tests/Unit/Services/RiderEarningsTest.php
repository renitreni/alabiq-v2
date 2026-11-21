<?php

namespace Tests\Unit\Services;

use App\Models\DeliveryAssignment;
use App\Models\Rider;
use App\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderEarningsTest extends TestCase
{
    use RefreshDatabase;

    private ReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportingService;
    }

    public function test_earnings_sum_completed_assignment_fees(): void
    {
        $rider = Rider::factory()->create();
        DeliveryAssignment::factory()->completed()->create(['rider_id' => $rider->id, 'delivery_fee' => 15]);
        DeliveryAssignment::factory()->completed()->create(['rider_id' => $rider->id, 'delivery_fee' => 25]);
        DeliveryAssignment::factory()->rejected()->create(['rider_id' => $rider->id, 'delivery_fee' => 30]);

        $report = $this->service->riderEarnings($rider);

        $this->assertSame(2, $report['completed_deliveries']);
        $this->assertSame(40.0, $report['earnings']);
    }

    public function test_earnings_respect_date_window(): void
    {
        $rider = Rider::factory()->create();
        $old = DeliveryAssignment::factory()->completed()->create(['rider_id' => $rider->id, 'delivery_fee' => 10]);
        // updated_at is guarded on the model; bypass via query builder.
        DeliveryAssignment::whereKey($old->id)->update(['updated_at' => now()->subDays(30)]);
        DeliveryAssignment::factory()->completed()->create(['rider_id' => $rider->id, 'delivery_fee' => 20]);

        $report = $this->service->riderEarnings($rider, now()->subWeek(), now());

        $this->assertSame(1, $report['completed_deliveries']);
        $this->assertSame(20.0, $report['earnings']);
    }

    public function test_delivery_history_is_scoped_to_the_rider(): void
    {
        $rider = Rider::factory()->create();
        DeliveryAssignment::factory()->count(3)->create(['rider_id' => $rider->id]);
        DeliveryAssignment::factory()->count(2)->create(); // other riders

        $history = $this->service->riderDeliveryHistory($rider);

        $this->assertCount(3, $history['data']);
        $this->assertSame(3, $history['meta']['total']);
    }

    public function test_report_includes_lifetime_stats(): void
    {
        $rider = Rider::factory()->create(['total_deliveries' => 42, 'rating' => 4.75]);

        $report = $this->service->riderEarnings($rider);

        $this->assertSame(42, $report['lifetime_deliveries']);
        $this->assertSame(4.75, $report['rating']);
    }
}
