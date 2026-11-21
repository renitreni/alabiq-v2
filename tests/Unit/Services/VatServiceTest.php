<?php

namespace Tests\Unit\Services;

use App\Services\VatService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class VatServiceTest extends TestCase
{
    private VatService $vatService;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('alabiq.vat_rate', 0.15);
        $this->vatService = new VatService;
    }

    public function test_rate_comes_from_config(): void
    {
        $this->assertSame(0.15, $this->vatService->rate());

        Config::set('alabiq.vat_rate', 0.05);
        $this->assertSame(0.05, (new VatService)->rate());
    }

    public function test_rate_falls_back_to_default_when_config_missing(): void
    {
        Config::offsetUnset('alabiq.vat_rate');

        $this->assertSame(0.15, (new VatService)->rate());
    }

    public function test_amount_calculates_vat_on_net_subtotal(): void
    {
        $this->assertSame(1.5, $this->vatService->amount(10.0));
        $this->assertSame(18.75, $this->vatService->amount(125.0));
        $this->assertSame(0.0, $this->vatService->amount(0.0));
    }

    public function test_amount_rounds_to_two_decimals(): void
    {
        // 0.15 * 33.33 = 4.9995 -> 5.00
        $this->assertSame(5.0, $this->vatService->amount(33.33));
    }

    public function test_add_to_returns_gross_total(): void
    {
        $this->assertSame(11.5, $this->vatService->addTo(10.0));
        $this->assertSame(0.0, $this->vatService->addTo(0.0));
    }

    public function test_extract_from_returns_vat_portion_of_gross(): void
    {
        // VAT portion of a 115.00 gross at 15% is 15.00.
        $this->assertSame(15.0, $this->vatService->extractFrom(115.0));
    }

    public function test_net_from_returns_net_portion_of_gross(): void
    {
        $this->assertSame(100.0, $this->vatService->netFrom(115.0));
    }

    public function test_extract_and_net_recompose_gross(): void
    {
        $gross = 230.50;

        $this->assertSame(
            $gross,
            round($this->vatService->netFrom($gross) + $this->vatService->extractFrom($gross), 2)
        );
    }
}
