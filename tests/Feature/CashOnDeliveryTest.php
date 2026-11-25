<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashOnDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function codOrder(string $status): Order
    {
        $order = Order::factory()->create([
            'status' => $status,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'pending',
        ]);
        app(PaymentService::class)->initiate($order);

        return $order;
    }

    public function test_delivery_settles_the_cash_transaction_and_posts_the_ledger(): void
    {
        $order = $this->codOrder(Order::STATUS_ON_THE_WAY);

        $this->assertTrue(app(OrderService::class)->transitionStatus($order, Order::STATUS_DELIVERED, $order->user_id, 'rider'));

        $transaction = PaymentTransaction::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(PaymentTransaction::STATUS_SUCCEEDED, $transaction->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertTrue(LedgerEntry::where('order_id', $order->id)->exists());
    }

    public function test_cancelling_a_cash_order_voids_the_transaction_instead_of_refunding(): void
    {
        $order = $this->codOrder(Order::STATUS_PLACED);

        app(OrderService::class)->transitionStatus($order, Order::STATUS_CANCELLED, $order->user_id, 'customer', 'Changed my mind');

        $this->assertSame(PaymentTransaction::STATUS_FAILED, PaymentTransaction::where('order_id', $order->id)->firstOrFail()->status);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertFalse(LedgerEntry::where('order_id', $order->id)->exists());
    }

    public function test_cash_flow_works_without_tap_credentials(): void
    {
        config(['alabiq.tap_payments.secret_key' => null, 'alabiq.tap_payments.webhook_secret' => null]);

        $order = $this->codOrder(Order::STATUS_PLACED);

        $this->assertTrue(PaymentTransaction::where('order_id', $order->id)->exists());
    }

    public function test_cash_collection_is_idempotent(): void
    {
        $order = $this->codOrder(Order::STATUS_ON_THE_WAY);
        $payments = app(PaymentService::class);

        $payments->recordCashCollected($order);
        $payments->recordCashCollected($order);

        $this->assertSame(1, PaymentTransaction::where('order_id', $order->id)->count());
        $this->assertSame(1, LedgerEntry::where('order_id', $order->id)->where('account', LedgerEntry::ACCOUNT_PLATFORM_COMMISSION)->count());
    }
}
