<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantSettlement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Restaurant settlements: aggregate delivered orders into a payable record
 * and mark it paid with a balanced ledger journal per order.
 *
 * Rules:
 *  - Idempotent: generating for the same restaurant and period returns the
 *    existing settlement instead of duplicating it.
 *  - Only delivered orders inside the window and not already settled count.
 *  - Payment posts one balanced journal per settled order via the
 *    LedgerService (double-entry invariant preserved).
 */
class SettlementService
{
    public function __construct(
        private ?CommissionService $commissionService = null,
        private ?LedgerService $ledgerService = null,
        private ?AuditLogService $auditLogService = null,
    ) {
        $this->commissionService ??= new CommissionService;
        $this->ledgerService ??= new LedgerService($this->commissionService);
        $this->auditLogService ??= new AuditLogService;
    }

    /**
     * Effective commission rate for a restaurant (active override or config).
     */
    public function effectiveRatePercent(Restaurant $restaurant): float
    {
        $override = $restaurant->commissionOverride()->value('rate');

        if ($override !== null) {
            return (float) $override;
        }

        return round((float) config('alabiq.platform_commission_rate', 0.15) * 100, 2);
    }

    /**
     * Build (or return the existing) settlement for a period.
     *
     * @throws \DomainException On empty order sets or reversed dates.
     */
    public function generate(Restaurant $restaurant, Carbon $periodStart, Carbon $periodEnd, ?User $actor = null, ?string $ip = null): RestaurantSettlement
    {
        if ($periodEnd->lt($periodStart)) {
            throw new \DomainException('Period end must not be before period start.');
        }

        $existing = RestaurantSettlement::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->first();

        if ($existing) {
            return $existing; // Idempotent re-generation.
        }

        $rate = $this->effectiveRatePercent($restaurant);

        return DB::transaction(function () use ($restaurant, $periodStart, $periodEnd, $rate, $actor, $ip) {
            $orders = Order::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('status', Order::STATUS_DELIVERED)
                ->whereNotNull('delivered_at')
                ->whereDate('delivered_at', '>=', $periodStart->toDateString())
                ->whereDate('delivered_at', '<=', $periodEnd->toDateString())
                ->lockForUpdate()
                ->get();

            // Exclude orders already covered by any settlement for this restaurant.
            $orders = $orders->filter(fn (Order $order) => ! $this->isOrderSettled($restaurant, $order));

            if ($orders->isEmpty()) {
                throw new \DomainException('No unsettled delivered orders in this period.');
            }

            $gross = round($orders->sum(fn (Order $o) => (float) $o->subtotal), 2);
            $commission = round($gross * ($rate / 100), 2);
            $net = round($gross - $commission, 2);

            $settlement = RestaurantSettlement::create([
                'restaurant_id' => $restaurant->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'orders_count' => $orders->count(),
                'gross_amount' => $gross,
                'commission_amount' => $commission,
                'net_amount' => $net,
                'status' => RestaurantSettlement::STATUS_PENDING,
                'reference' => $this->nextReference(),
            ]);

            $this->auditLogService->record($actor, 'settlement.generated', $settlement, null, [
                'restaurant_id' => $restaurant->id,
                'period' => [$periodStart->toDateString(), $periodEnd->toDateString()],
                'orders_count' => $orders->count(),
                'net_amount' => $net,
            ], $ip);

            return $settlement;
        });
    }

    /**
     * Mark a pending settlement as paid and post balanced payout journals
     * for every covered order.
     *
     * @throws \DomainException When the settlement is not pending.
     */
    public function markPaid(RestaurantSettlement $settlement, ?User $actor = null, ?string $ip = null): RestaurantSettlement
    {
        if ($settlement->status !== RestaurantSettlement::STATUS_PENDING) {
            throw new \DomainException("Settlement is already {$settlement->status}.");
        }

        return DB::transaction(function () use ($settlement, $actor, $ip) {
            $orders = Order::query()
                ->where('restaurant_id', $settlement->restaurant_id)
                ->where('status', Order::STATUS_DELIVERED)
                ->whereNotNull('delivered_at')
                ->whereDate('delivered_at', '>=', $settlement->period_start->toDateString())
                ->whereDate('delivered_at', '<=', $settlement->period_end->toDateString())
                ->get()
                ->filter(fn (Order $order) => ! $this->isOrderSettled($settlement->restaurant, $order, $settlement->id));

            $rate = $this->effectiveRatePercent($settlement->restaurant);

            foreach ($orders as $order) {
                $breakdown = $this->commissionService->calculate($order, $rate / 100);
                $this->ledgerService->postSettlementPayout($order, $settlement, $breakdown['restaurant_share']);
            }

            $settlement->update([
                'status' => RestaurantSettlement::STATUS_PAID,
                'paid_at' => now(),
            ]);

            $this->auditLogService->record($actor, 'settlement.paid', $settlement, [
                'status' => RestaurantSettlement::STATUS_PENDING,
            ], [
                'status' => RestaurantSettlement::STATUS_PAID,
                'net_amount' => (float) $settlement->net_amount,
            ], $ip);

            return $settlement->refresh();
        });
    }

    /**
     * Orders within the settlement window, used by generate/markPaid.
     *
     * @return Collection<int, Order>
     */
    public function coveredOrders(RestaurantSettlement $settlement): Collection
    {
        return Order::query()
            ->where('restaurant_id', $settlement->restaurant_id)
            ->where('status', Order::STATUS_DELIVERED)
            ->whereNotNull('delivered_at')
            ->whereDate('delivered_at', '>=', $settlement->period_start->toDateString())
            ->whereDate('delivered_at', '<=', $settlement->period_end->toDateString())
            ->get();
    }

    /**
     * An order counts as settled when a PAID settlement for the same
     * restaurant covers its delivery date window.
     */
    private function isOrderSettled(Restaurant $restaurant, Order $order, ?int $ignoreSettlementId = null): bool
    {
        return RestaurantSettlement::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', RestaurantSettlement::STATUS_PAID)
            ->when($ignoreSettlementId, fn ($q) => $q->where('id', '!=', $ignoreSettlementId))
            ->whereDate('period_start', '<=', $order->delivered_at?->toDateString())
            ->whereDate('period_end', '>=', $order->delivered_at?->toDateString())
            ->exists();
    }

    private function nextReference(): string
    {
        return 'STL-'.now()->format('Ymd').'-'.str_pad((string) (RestaurantSettlement::count() + 1), 4, '0', STR_PAD_LEFT);
    }
}
