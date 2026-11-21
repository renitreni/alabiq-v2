<?php

namespace App\Services;

use App\Models\Order;

class CommissionService
{
    public function calculate(Order $order): array
    {
        $subtotal = (float) $order->subtotal;
        $deliveryFee = (float) $order->delivery_fee;
        $commissionRate = (float) config('alabiq.platform_commission_rate', 0.15);

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
