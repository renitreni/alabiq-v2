<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantSettlement;
use App\Models\Rider;
use App\Models\User;

/**
 * Headline platform statistics for the super admin dashboard.
 *
 * Revenue figures come from the double-entry ledger, never from order
 * snapshots, so stats stay consistent with the accounting layer.
 */
class AdminStatsService
{
    public function __construct(private ?LedgerService $ledgerService = null)
    {
        $this->ledgerService ??= new LedgerService(new CommissionService);
    }

    /**
     * @return array<string, mixed>
     */
    public function platformStatistics(): array
    {
        return [
            'users' => [
                'total' => User::count(),
                'by_role' => User::query()
                    ->selectRaw('role, COUNT(*) as count')
                    ->groupBy('role')
                    ->pluck('count', 'role')
                    ->all(),
            ],
            'restaurants' => [
                'total' => Restaurant::count(),
                'active' => Restaurant::where('is_active', true)->count(),
                'approved' => Restaurant::where('is_approved', true)->count(),
                'pending_approval' => Restaurant::where('is_approved', false)->count(),
            ],
            'riders' => [
                'total' => Rider::count(),
                'online' => Rider::where('is_online', true)->where('is_active', true)->count(),
                'delivering_now' => DeliveryAssignment::whereIn('status', DeliveryAssignment::ACTIVE_STATUSES)->count(),
            ],
            'orders' => [
                'total' => Order::withTrashed()->count(),
                'today' => Order::whereDate('created_at', today())->count(),
                'active' => Order::active()->count(),
                'delivered' => Order::where('status', Order::STATUS_DELIVERED)->count(),
            ],
            'gmv' => [
                // Delivered order value (what customers paid).
                'delivered_total' => (float) Order::where('status', Order::STATUS_DELIVERED)->sum('total_amount'),
                'refunded_total' => (float) Order::where('status', Order::STATUS_REFUNDED)->sum('total_amount'),
            ],
            'ledger' => [
                'platform_commission' => $this->ledgerService->getAccountBalance(LedgerEntry::ACCOUNT_PLATFORM_COMMISSION),
                'order_clearing' => $this->ledgerService->getAccountBalance(LedgerEntry::ACCOUNT_ORDER_CLEARING),
                'settlement_payout' => $this->ledgerService->getAccountBalance(LedgerEntry::ACCOUNT_SETTLEMENT_PAYOUT),
            ],
            'settlements' => [
                'pending_count' => RestaurantSettlement::where('status', RestaurantSettlement::STATUS_PENDING)->count(),
                'pending_amount' => (float) RestaurantSettlement::where('status', RestaurantSettlement::STATUS_PENDING)->sum('net_amount'),
            ],
        ];
    }
}
