<?php

namespace Tests\Unit\Services;

use App\Services\FoodMoneyService;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;

class FoodMoneyServiceTest extends TestCase
{
    private FoodMoneyService $money;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('alabiq.currency', 'SAR');
        $this->money = new FoodMoneyService;
    }

    public function test_round_snaps_to_minor_units(): void
    {
        $this->assertSame(10.24, $this->money->round(10.239));
        $this->assertSame(10.24, $this->money->round(10.235));
        $this->assertSame(0.0, $this->money->round(0.0));
    }

    public function test_format_appends_currency(): void
    {
        $this->assertSame('1,234.50 SAR', $this->money->format(1234.5));
        $this->assertSame('10.00 USD', $this->money->format(10.0, 'USD'));
    }

    public function test_format_falls_back_to_config_currency(): void
    {
        Config::set('alabiq.currency', 'AED');

        $this->assertSame('5.00 AED', $this->money->format(5.0));
    }

    public function test_parse_strips_thousand_separators(): void
    {
        $this->assertSame(1234.56, $this->money->parse('1,234.56'));
        $this->assertSame(10.0, $this->money->parse(' 10 '));
    }

    public function test_parse_rejects_non_numeric_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->money->parse('abc');
    }

    public function test_allocate_distributes_by_weight_and_sums_to_total(): void
    {
        $parts = $this->money->allocate(100.0, ['platform' => 15, 'restaurant' => 85]);

        $this->assertSame(['platform' => 15.0, 'restaurant' => 85.0], $parts);
        $this->assertSame(100.0, round(array_sum($parts), 2));
    }

    public function test_allocate_handles_indivisible_cents(): void
    {
        // 0.01 cannot be split 50/50 into exact cents without a remainder.
        $parts = $this->money->allocate(0.01, [1, 1]);

        $this->assertSame(0.01, round(array_sum($parts), 2));
        $this->assertSame(0.01, max($parts));
        $this->assertSame(0.0, min($parts));
    }

    public function test_allocate_rejects_zero_total_weight(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->money->allocate(100.0, [0, 0]);
    }
}
