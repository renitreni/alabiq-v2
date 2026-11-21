<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    public function __construct(
        private CommissionService $commissionService
    ) {}

    /**
     * Post double-entry ledger records for a successful payment.
     */
    public function postPayment(Order $order, PaymentTransaction $transaction): array
    {
        return DB::transaction(function () use ($order, $transaction) {
            $breakdown = $this->commissionService->calculate($order);
            $reference = 'order_'.$order->id.'_'.$transaction->id;

            $entries = [];

            // Platform commission: credit platform (revenue)
            $entries[] = $this->post(
                $order,
                $transaction,
                LedgerEntry::ACCOUNT_PLATFORM_COMMISSION,
                LedgerEntry::TYPE_CREDIT,
                $breakdown['platform_commission'],
                $reference,
                'Platform commission for order #'.$order->id
            );

            // Restaurant revenue: credit restaurant (revenue)
            $entries[] = $this->post(
                $order,
                $transaction,
                LedgerEntry::ACCOUNT_RESTAURANT_REVENUE,
                LedgerEntry::TYPE_CREDIT,
                $breakdown['restaurant_share'],
                $reference,
                'Restaurant share for order #'.$order->id
            );

            // Delivery fee: credit delivery (revenue)
            $entries[] = $this->post(
                $order,
                $transaction,
                LedgerEntry::ACCOUNT_DELIVERY_FEE,
                LedgerEntry::TYPE_CREDIT,
                $breakdown['delivery_fee'],
                $reference,
                'Delivery fee for order #'.$order->id
            );

            return $entries;
        });
    }

    /**
     * Post refund entries to reverse a completed payment.
     */
    public function postRefund(Order $order, PaymentTransaction $transaction, float $amount): array
    {
        return DB::transaction(function () use ($order, $transaction, $amount) {
            $reference = 'refund_'.$order->id.'_'.$transaction->id;

            $entries = [];

            // Refund account: debit (money out)
            $entries[] = $this->post(
                $order,
                $transaction,
                LedgerEntry::ACCOUNT_REFUND,
                LedgerEntry::TYPE_DEBIT,
                $amount,
                $reference,
                'Refund for order #'.$order->id
            );

            return $entries;
        });
    }

    /**
     * Post a single ledger entry with running balance.
     */
    private function post(
        Order $order,
        PaymentTransaction $transaction,
        string $account,
        string $type,
        float $amount,
        string $reference,
        string $description
    ): LedgerEntry {
        $lastBalance = (float) (LedgerEntry::where('account', $account)
            ->orderByDesc('id')
            ->value('balance_after') ?? 0);

        $balanceAfter = $type === LedgerEntry::TYPE_DEBIT
            ? $lastBalance - $amount
            : $lastBalance + $amount;

        return LedgerEntry::create([
            'order_id' => $order->id,
            'payment_transaction_id' => $transaction->id,
            'account' => $account,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => round($balanceAfter, 2),
            'reference' => $reference,
            'description' => $description,
        ]);
    }

    /**
     * Get total balance for an account across all entries.
     */
    public function getAccountBalance(string $account): float
    {
        return (float) LedgerEntry::where('account', $account)
            ->orderByDesc('id')
            ->value('balance_after') ?? 0;
    }

    /**
     * Get all ledger entries for an order.
     */
    public function getOrderEntries(Order $order): Collection
    {
        return LedgerEntry::where('order_id', $order->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * Verify that ledger balances are consistent (credits - debits >= 0 per account).
     */
    public function verifyBalances(Order $order): bool
    {
        $entries = $this->getOrderEntries($order);

        foreach ($entries->groupBy('account') as $account => $accountEntries) {
            $net = $accountEntries->sum(fn (LedgerEntry $entry) => $entry->signed_amount);
            if ($net < 0) {
                return false;
            }
        }

        return true;
    }
}
