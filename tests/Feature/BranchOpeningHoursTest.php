<?php

namespace Tests\Feature;

use App\Models\RestaurantBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BranchOpeningHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['alabiq.timezone' => 'Asia/Riyadh']);
    }

    private function branch(string $opensAt, string $closesAt): RestaurantBranch
    {
        return RestaurantBranch::factory()->create(['opens_at' => $opensAt, 'closes_at' => $closesAt]);
    }

    public function test_hours_are_evaluated_in_the_business_timezone(): void
    {
        // 07:30 UTC is 10:30 in Riyadh.
        Carbon::setTestNow(Carbon::create(2026, 1, 1, 7, 30, 0, 'UTC'));

        $this->assertTrue($this->branch('10:00:00', '22:00:00')->isOpen());
        $this->assertFalse($this->branch('11:00:00', '22:00:00')->isOpen());
    }

    public function test_hours_running_past_midnight_are_supported(): void
    {
        $branch = $this->branch('18:00:00', '02:00:00');

        Carbon::setTestNow(Carbon::create(2026, 1, 1, 22, 0, 0, 'UTC')); // 01:00 Riyadh
        $this->assertTrue($branch->isOpen());

        Carbon::setTestNow(Carbon::create(2026, 1, 1, 10, 0, 0, 'UTC')); // 13:00 Riyadh
        $this->assertFalse($branch->isOpen());
    }
}
