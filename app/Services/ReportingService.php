<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Rider;
use Carbon\Carbon;

/**
 * Sales and earnings reporting, strictly scoped to the actor's own data
 * for restaurants and riders (isolation gate).
 */
class ReportingService
{
    public function __construct(private ?CommissionService $commissionService = null)
    {
        $this->commissionService ??= new CommissionService;
    }

    /**
     * Sales report for one restaurant within a date window.
     *
     * @return array<string, mixed>
     */
    public function restaurantSales(Restaurant $restaurant, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $base = Order::query()->where('restaurant_id', $restaurant->id);

        $statusCounts = (clone $base)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $delivered = (clone $base)
            ->where('status', Order::STATUS_DELIVERED)
            ->when($from, fn ($q) => $q->whereDate('delivered_at', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('delivered_at', '<=', $to->toDateString()));

        $gross = (float) (clone $delivered)->sum('subtotal');
        $deliveryFees = (float) (clone $delivered)->sum('delivery_fee');
        $ordersCount = (clone $delivered)->count();

        $commission = round($gross * $this->commissionRate($restaurant), 2);

        return [
            'restaurant_id' => $restaurant->id,
            'period' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'orders_count' => $ordersCount,
            'gross_amount' => round($gross, 2),
            'delivery_fees' => round($deliveryFees, 2),
            'commission_amount' => $commission,
            'net_amount' => round($gross - $commission, 2),
            'average_order_value' => $ordersCount > 0 ? round($gross / $ordersCount, 2) : 0.0,
            'by_status' => $statusCounts,
        ];
    }

    /**
     * Earnings report for one rider within a date window (completed
     * delivery assignment fees).
     *
     * @return array<string, mixed>
     */
    public function riderEarnings(Rider $rider, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $completed = DeliveryAssignment::query()
            ->where('rider_id', $rider->id)
            ->where('status', DeliveryAssignment::STATUS_COMPLETED)
            ->when($from, fn ($q) => $q->whereDate('updated_at', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('updated_at', '<=', $to->toDateString()));

        return [
            'rider_id' => $rider->id,
            'period' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'completed_deliveries' => (clone $completed)->count(),
            'earnings' => (float) (clone $completed)->sum('delivery_fee'),
            'lifetime_deliveries' => $rider->total_deliveries,
            'rating' => $rider->rating !== null ? (float) $rider->rating : null,
        ];
    }

    /**
     * Paginated delivery history for a rider, newest first.
     */
    public function riderDeliveryHistory(Rider $rider, int $page = 1, int $perPage = 15): array
    {
        $paginator = DeliveryAssignment::query()
            ->where('rider_id', $rider->id)
            ->with(['order:id,status,total_amount,restaurant_id', 'order.restaurant:id,name'])
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Effective commission rate for a restaurant: active override row first,
     * config default otherwise.
     */
    private function commissionRate(Restaurant $restaurant): float
    {
        $override = $restaurant->commissionOverride()->value('rate');

        if ($override !== null) {
            return (float) $override / 100;
        }

        return (float) config('alabiq.platform_commission_rate', 0.15);
    }
}
