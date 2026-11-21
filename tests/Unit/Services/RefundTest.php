<?php

namespace Tests\Unit\Services;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\LedgerService;
use App\Services\PaymentService;
use App\Services\TapPaymentsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    private Order $order;

    private PaymentTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();
        config(['alabiq.platform_commission_rate' => 0.15]);

        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $this->order = Order::factory()->create([
            'user_id' => $user->id,
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'payment_method' => PaymentTransaction::GATEWAY_CASH,
            'subtotal' => 100.00,
            'delivery_fee' => 10.00,
            'total_amount' => 110.00,
            'payment_status' => 'paid',
        ]);

        $this->transaction = PaymentTransaction::factory()->succeeded()->create([
            'order_id' => $this->order->id,
            'gateway' => PaymentTransaction::GATEWAY_CASH,
            'amount' => 110.00,
        ]);

        $gateway = $this->createMock(TapPaymentsGateway::class);
        $ledger = new LedgerService(new CommissionService);
        $this->service = new PaymentService($gateway, $ledger);
    }

    public function test_full_refund_of_cash_transaction(): void
    {
        $result = $this->service->refund($this->transaction, 110.00);

        $this->assertEquals(110.00, $result['refund_amount']);
        $this->assertEquals(PaymentTransaction::STATUS_REFUNDED, $result['transaction']->status);
        $this->assertEquals(110.00, $result['transaction']->refunded_amount);
        $this->assertEquals('refunded', $this->order->fresh()->payment_status);
    }

    public function test_partial_refund_of_cash_transaction(): void
    {
        $result = $this->service->refund($this->transaction, 50.00);

        $this->assertEquals(50.00, $result['refund_amount']);
        $this->assertEquals(50.00, $result['transaction']->refunded_amount);
        $this->assertEquals(60.00, $result['transaction']->getRemainingRefundableAmount());
    }

    public function test_refund_posts_ledger_entry(): void
    {
        $this->service->refund($this->transaction, 110.00);

        $entry = LedgerEntry::where('order_id', $this->order->id)
            ->where('account', LedgerEntry::ACCOUNT_REFUND)
            ->first();

        $this->assertNotNull($entry);
        $this->assertEquals(LedgerEntry::TYPE_DEBIT, $entry->type);
        $this->assertEquals(110.00, $entry->amount);
    }

    public function test_refund_rejects_non_refundable_transaction(): void
    {
        $this->transaction->update(['status' => PaymentTransaction::STATUS_FAILED]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not refundable');

        $this->service->refund($this->transaction, 50.00);
    }

    public function test_refund_rejects_amount_exceeding_remaining(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid refund amount');

        $this->service->refund($this->transaction, 200.00);
    }

    public function test_refund_rejects_zero_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid refund amount');

        $this->service->refund($this->transaction, 0);
    }

    public function test_tap_refund_calls_gateway(): void
    {
        $this->transaction->update([
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_transaction_id' => 'chg_refund_test',
        ]);

        $gateway = $this->createMock(TapPaymentsGateway::class);
        $gateway->expects($this->once())
            ->method('refund')
            ->with($this->transaction, 50.00)
            ->willReturn([
                'refund_id' => 'ref_12345',
                'status' => 'succeeded',
                'amount' => 50.00,
                'raw_response' => ['id' => 'ref_12345'],
            ]);
        $service = new PaymentService($gateway, new LedgerService(new CommissionService));

        $result = $service->refund($this->transaction, 50.00);

        $this->assertEquals('ref_12345', $result['gateway_response']['refund_id']);
        $this->assertEquals(50.00, $result['transaction']->refunded_amount);
    }

    public function test_refund_marks_transaction_refunded_after_partial_refund(): void
    {
        $this->service->refund($this->transaction, 50.00);
        $this->transaction->refresh();

        $this->assertEquals(PaymentTransaction::STATUS_REFUNDED, $this->transaction->status);
        $this->assertFalse($this->transaction->isRefundable());
    }
}
