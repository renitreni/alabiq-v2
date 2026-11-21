<?php

namespace App\Services;

/**
 * Money helpers for order and payment amounts.
 *
 * All amounts are stored as decimal(10,2)-style floats; this service
 * centralizes rounding, display formatting and weighted allocation so
 * every money touchpoint behaves identically.
 */
class FoodMoneyService
{
    /**
     * Round an amount to the currency's minor-unit precision (2 dp).
     */
    public function round(float $amount): float
    {
        return round($amount, 2);
    }

    /**
     * Format an amount for display, e.g. "1,234.50 SAR".
     */
    public function format(float $amount, ?string $currency = null): string
    {
        $currency ??= config('alabiq.currency', 'SAR');

        return number_format($this->round($amount), 2, '.', ',').' '.$currency;
    }

    /**
     * Parse a user-supplied money string ("1,234.56") into a float amount.
     */
    public function parse(string $amount): float
    {
        $normalized = str_replace(',', '', trim($amount));

        if (! is_numeric($normalized)) {
            throw new \InvalidArgumentException("Invalid money amount: {$amount}");
        }

        return $this->round((float) $normalized);
    }

    /**
     * Allocate a total across weighted parts using largest-remainder rounding.
     *
     * @param  array<string|int, float|int>  $weights
     * @return array<string|int, float> Parts rounded to 2 dp; sums to $total.
     */
    public function allocate(float $total, array $weights): array
    {
        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            throw new \InvalidArgumentException('Allocation weights must sum to a positive value');
        }

        $raw = [];
        $floors = [];
        $remainder = $total;

        foreach ($weights as $key => $weight) {
            $exact = $total * ($weight / $totalWeight);
            $floors[$key] = floor($exact * 100) / 100;
            $raw[$key] = $exact;
            $remainder -= $floors[$key];
        }

        // Distribute the leftover cents to the largest fractional parts.
        arsort($raw);
        // Floating-point dust can leave remainder a hair below zero.
        $centsLeft = max(0, (int) round($remainder * 100));

        foreach (array_keys($raw) as $key) {
            if ($centsLeft-- <= 0) {
                break;
            }
            $floors[$key] = $this->round($floors[$key] + 0.01);
        }

        return $floors;
    }
}
