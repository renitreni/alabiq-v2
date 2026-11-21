<?php

namespace App\Services;

/**
 * Centralized VAT calculation for order pricing.
 *
 * Rates come from config('alabiq.vat_rate'); amounts are rounded
 * to 2 decimal places to match money storage columns.
 */
class VatService
{
    /**
     * The configured VAT rate (e.g. 0.15 for 15%).
     */
    public function rate(): float
    {
        $rate = config('alabiq.vat_rate');

        return is_numeric($rate) ? (float) $rate : 0.15;
    }

    /**
     * VAT amount added on top of a net (VAT-exclusive) subtotal.
     */
    public function amount(float $net): float
    {
        return round($net * $this->rate(), 2);
    }

    /**
     * Gross total for a net subtotal (net + VAT).
     */
    public function addTo(float $net): float
    {
        return round($net + $this->amount($net), 2);
    }

    /**
     * VAT portion of a VAT-inclusive gross amount.
     */
    public function extractFrom(float $gross): float
    {
        return round($gross - $gross / (1 + $this->rate()), 2);
    }

    /**
     * Net (VAT-exclusive) portion of a VAT-inclusive gross amount.
     */
    public function netFrom(float $gross): float
    {
        return round($gross / (1 + $this->rate()), 2);
    }
}
