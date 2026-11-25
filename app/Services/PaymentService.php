<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private TapPaymentsGateway $gateway,
        private LedgerService $ledgerService
    ) {}

    /**
     * Initiate a payment for an order. Cash on delivery is handled locally.
     */
    public function initiate(Order $order): array
    {
        if ($order->payment_method === PaymentTransaction::GATEWAY_CASH) {
            return $this->initiateCashOnDelivery($order);
        }

        if ($order->payment_method !== PaymentTransaction::GATEWAY_TAP) {
            throw new \InvalidArgumentException('Unsupported payment method: '.$order->payment_method);
        }

        $transaction = $this->findOrCreateTransaction($order);

        if ($transaction->isSuccessful()) {
            return [
                'transaction' => $transaction,
                'payment_url' => null,
                'message' => 'Payment already completed',
            ];
        }

        $result = $this->gateway->initiate($order, $transaction);

        $transaction->update([
            'gateway_transaction_id' => $result['gateway_transaction_id'],
            'status' => PaymentTransaction::STATUS_PROCESSING,
            'response' => $result['raw_response'],
        ]);

        return [
            'transaction' => $transaction,
            'payment_url' => $result['payment_url'],
            'gateway_transaction_id' => $result['gateway_transaction_id'],
        ];
    }

    /**
     * Find an existing transaction (idempotency) or create a new one.
     */
    private function findOrCreateTransaction(Order $order): PaymentTransaction
    {
        $existing = PaymentTransaction::where('order_id', $order->id)
            ->where('gateway', $order->payment_method)
            ->whereIn('status', [PaymentTransaction::STATUS_PENDING, PaymentTransaction::STATUS_PROCESSING])
            ->first();

        if ($existing) {
            return $existing;
        }

        return PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway' => $order->payment_method,
            'idempotency_key' => $this->generateIdempotencyKey($order),
            'amount' => $order->total_amount,
            'currency' => config('alabiq.currency', 'SAR'),
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);
    }

    private function generateIdempotencyKey(Order $order): string
    {
        return 'alabiq_order_'.$order->id.'_'.uniqid('', true);
    }

    /**
     * Cash on delivery: create a local pending transaction (paid on delivery).
     */
    private function initiateCashOnDelivery(Order $order): array
    {
        $transaction = PaymentTransaction::firstOrCreate(
            [
                'order_id' => $order->id,
                'gateway' => PaymentTransaction::GATEWAY_CASH,
            ],
            [
                'idempotency_key' => 'cod_'.$order->id.'_'.uniqid('', true),
                'amount' => $order->total_amount,
                'currency' => config('alabiq.currency', 'SAR'),
                'status' => PaymentTransaction::STATUS_PENDING,
            ]
        );

        return [
            'transaction' => $transaction,
            'payment_url' => null,
            'message' => 'Cash on delivery - pay when order arrives',
        ];
    }

    /**
     * Cash collected by the rider on delivery: settle the COD transaction and
     * post the ledger entries (idempotent).
     */
    public function recordCashCollected(Order $order): ?PaymentTransaction
    {
        $transaction = $this->initiateCashOnDelivery($order)['transaction'];

        if ($transaction->status !== PaymentTransaction::STATUS_PENDING) {
            return $transaction;
        }

        return DB::transaction(function () use ($order, $transaction) {
            $transaction->update(['status' => PaymentTransaction::STATUS_SUCCEEDED]);
            $this->ledgerService->postPayment($order, $transaction);

            return $transaction->fresh();
        });
    }

    /**
     * A cancelled cash order never collected money, so close its transaction.
     */
    public function voidCashOrder(Order $order, ?string $reason = null): void
    {
        PaymentTransaction::where('order_id', $order->id)
            ->where('gateway', PaymentTransaction::GATEWAY_CASH)
            ->where('status', PaymentTransaction::STATUS_PENDING)
            ->update([
                'status' => PaymentTransaction::STATUS_FAILED,
                'failure_reason' => $reason ?? 'Order cancelled',
            ]);
    }

    /**
     * Handle a webhook event (idempotent).
     */
    public function handleWebhook(array $parsedPayload): ?PaymentTransaction
    {
        $gatewayTxId = $parsedPayload['gateway_transaction_id'] ?? null;
        $tapStatus = $parsedPayload['status'] ?? null;

        if (! $gatewayTxId || ! $tapStatus) {
            return null;
        }

        $transaction = PaymentTransaction::where('gateway_transaction_id', $gatewayTxId)->first();

        if (! $transaction) {
            return null;
        }

        $newStatus = $this->gateway->mapStatus($tapStatus);

        // Idempotency: if already in a terminal state, do nothing
        if ($transaction->status === $newStatus) {
            return $transaction;
        }

        if (! in_array($transaction->status, [
            PaymentTransaction::STATUS_PENDING,
            PaymentTransaction::STATUS_PROCESSING,
        ])) {
            return $transaction;
        }

        return DB::transaction(function () use ($transaction, $newStatus, $parsedPayload) {
            $transaction->update([
                'status' => $newStatus,
                'response' => $parsedPayload['raw_response'] ?? null,
                'failure_reason' => $newStatus === PaymentTransaction::STATUS_FAILED
                    ? ($parsedPayload['raw_response']['response']['message'] ?? 'Payment failed')
                    : null,
            ]);

            $order = $transaction->order;

            if ($newStatus === PaymentTransaction::STATUS_SUCCEEDED) {
                $order->update([
                    'payment_status' => 'paid',
                    'status' => Order::STATUS_PLACED,
                ]);

                $this->ledgerService->postPayment($order, $transaction);
            } elseif ($newStatus === PaymentTransaction::STATUS_FAILED) {
                $order->update(['payment_status' => 'failed']);
            }

            return $transaction->fresh();
        });
    }

    /**
     * Process a refund for a transaction.
     */
    public function refund(PaymentTransaction $transaction, float $amount, ?int $processedBy = null, ?string $reason = null): array
    {
        if (! $transaction->isRefundable()) {
            throw new \InvalidArgumentException('Transaction is not refundable');
        }

        if ($amount <= 0 || $amount > $transaction->getRemainingRefundableAmount()) {
            throw new \InvalidArgumentException('Invalid refund amount');
        }

        // Cash on delivery refunds don't call the gateway
        if ($transaction->gateway === PaymentTransaction::GATEWAY_CASH) {
            return $this->completeRefund($transaction, $amount, null, $processedBy, $reason);
        }

        $result = $this->gateway->refund($transaction, $amount);

        return $this->completeRefund($transaction, $amount, $result, $processedBy, $reason);
    }

    private function completeRefund(PaymentTransaction $transaction, float $amount, ?array $gatewayResult, ?int $processedBy = null, ?string $reason = null): array
    {
        return DB::transaction(function () use ($transaction, $amount, $gatewayResult, $processedBy, $reason) {
            $transaction->update([
                'refunded_amount' => round((float) $transaction->refunded_amount + $amount, 2),
                'status' => PaymentTransaction::STATUS_REFUNDED,
            ]);

            $order = $transaction->order;
            $order->update(['payment_status' => 'refunded']);

            $this->ledgerService->postRefund($order, $transaction, $amount);

            $refund = Refund::create([
                'payment_transaction_id' => $transaction->id,
                'order_id' => $order->id,
                'amount' => $amount,
                'currency' => $transaction->currency,
                'reason' => $reason,
                'processed_by' => $processedBy,
                'status' => 'processed',
                'gateway_response' => $gatewayResult,
            ]);

            return [
                'transaction' => $transaction->fresh(),
                'refund' => $refund,
                'refund_amount' => $amount,
                'gateway_response' => $gatewayResult,
            ];
        });
    }
}
