<?php

namespace App\Services;

use App\Models\Order;

class CommissionService
{
    /**
     * Commission breakdown for an order. An explicit rate (e.g. a
     * restaurant-specific override) wins over the config default.
     */
    public function calculate(Order $order, ?float $rate = null): array
    {
        $subtotal = (float) $order->subtotal;
        $deliveryFee = (float) $order->delivery_fee;
        $commissionRate = $rate ?? (float) config('alabiq.platform_commission_rate', 0.15);

        $platformCommission = round($subtotal * $commissionRate, 2);
        $restaurantShare = round($subtotal - $platformCommission, 2);

        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'platform_commission' => $platformCommission,
            'restaurant_share' => $restaurantShare,
            'total' => round($restaurantShare + $deliveryFee, 2),
        ];
    }
}
