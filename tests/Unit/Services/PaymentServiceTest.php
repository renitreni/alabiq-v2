<?php

namespace Tests\Unit\Services;

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

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        config(['alabiq.platform_commission_rate' => 0.15]);

        $gateway = $this->createMock(TapPaymentsGateway::class);
        $ledger = new LedgerService(new CommissionService);
        $this->service = new PaymentService($gateway, $ledger);

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
        ]);
    }

    public function test_initiate_cash_on_delivery_creates_local_transaction(): void
    {
        $result = $this->service->initiate($this->order);

        $this->assertNull($result['payment_url']);
        $this->assertInstanceOf(PaymentTransaction::class, $result['transaction']);
        $this->assertEquals(PaymentTransaction::GATEWAY_CASH, $result['transaction']->gateway);
        $this->assertEquals(PaymentTransaction::STATUS_PENDING, $result['transaction']->status);
        $this->assertEquals(110.00, $result['transaction']->amount);
    }

    public function test_initiate_cash_on_delivery_is_idempotent(): void
    {
        $first = $this->service->initiate($this->order);
        $second = $this->service->initiate($this->order);

        $this->assertEquals($first['transaction']->id, $second['transaction']->id);
        $this->assertEquals(1, PaymentTransaction::where('order_id', $this->order->id)->count());
    }

    public function test_initiate_tap_payments_creates_processing_transaction(): void
    {
        $this->order->update(['payment_method' => PaymentTransaction::GATEWAY_TAP]);

        $gateway = $this->createMock(TapPaymentsGateway::class);
        $gateway->expects($this->once())
            ->method('initiate')
            ->willReturn([
                'gateway_transaction_id' => 'chg_12345',
                'status' => 'pending',
                'payment_url' => 'https://sandbox.tap.company/checkout/chg_12345',
                'raw_response' => ['id' => 'chg_12345'],
            ]);
        $service = new PaymentService($gateway, new LedgerService(new CommissionService));

        $result = $service->initiate($this->order);

        $this->assertEquals('https://sandbox.tap.company/checkout/chg_12345', $result['payment_url']);
        $this->assertEquals(PaymentTransaction::STATUS_PROCESSING, $result['transaction']->status);
        $this->assertEquals('chg_12345', $result['transaction']->gateway_transaction_id);
    }

    public function test_initiate_tap_payments_is_idempotent(): void
    {
        $this->order->update(['payment_method' => PaymentTransaction::GATEWAY_TAP]);

        $gateway = $this->createMock(TapPaymentsGateway::class);
        $gateway->expects($this->exactly(2))
            ->method('initiate')
            ->willReturn([
                'gateway_transaction_id' => 'chg_12345',
                'status' => 'pending',
                'payment_url' => 'https://sandbox.tap.company/checkout/chg_12345',
                'raw_response' => ['id' => 'chg_12345'],
            ]);
        $service = new PaymentService($gateway, new LedgerService(new CommissionService));

        $first = $service->initiate($this->order);
        $second = $service->initiate($this->order);

        $this->assertEquals($first['transaction']->id, $second['transaction']->id);
        $this->assertEquals(1, PaymentTransaction::where('order_id', $this->order->id)->count());
    }

    public function test_initiate_rejects_unsupported_payment_method(): void
    {
        $this->order->update(['payment_method' => 'crypto']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported payment method');

        $this->service->initiate($this->order);
    }

    public function test_handle_webhook_marks_order_paid_and_posts_ledger(): void
    {
        $this->order->update(['payment_method' => PaymentTransaction::GATEWAY_TAP]);

        PaymentTransaction::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_transaction_id' => 'chg_webhook_test',
            'status' => PaymentTransaction::STATUS_PROCESSING,
        ]);

        $gateway = $this->createMock(TapPaymentsGateway::class);
        $gateway->method('mapStatus')->with('captured')->willReturn(PaymentTransaction::STATUS_SUCCEEDED);
        $service = new PaymentService($gateway, new LedgerService(new CommissionService));

        $result = $service->handleWebhook([
            'gateway_transaction_id' => 'chg_webhook_test',
            'status' => 'captured',
            'raw_response' => ['id' => 'chg_webhook_test', 'status' => 'captured'],
        ]);

        $this->assertNotNull($result);
        $this->assertEquals(PaymentTransaction::STATUS_SUCCEEDED, $result->status);
        $this->assertEquals('paid', $this->order->fresh()->payment_status);
        $this->assertEquals(Order::STATUS_PLACED, $this->order->fresh()->status);
        // 3 revenue credits + 1 balancing clearing debit.
        $this->assertEquals(4, $this->order->fresh()->ledgerEntries()->count());
    }

    public function test_handle_webhook_is_idempotent(): void
    {
        $this->order->update(['payment_method' => PaymentTransaction::GATEWAY_TAP]);

        PaymentTransaction::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_transaction_id' => 'chg_idempotent',
            'status' => PaymentTransaction::STATUS_SUCCEEDED,
        ]);

        $gateway = $this->createMock(TapPaymentsGateway::class);
        $gateway->method('mapStatus')->willReturn(PaymentTransaction::STATUS_SUCCEEDED);
        $service = new PaymentService($gateway, new LedgerService(new CommissionService));

        $result = $service->handleWebhook([
            'gateway_transaction_id' => 'chg_idempotent',
            'status' => 'captured',
            'raw_response' => [],
        ]);

        $this->assertEquals(PaymentTransaction::STATUS_SUCCEEDED, $result->status);
        $this->assertEquals(0, $this->order->fresh()->ledgerEntries()->count());
    }

    public function test_handle_webhook_ignores_unknown_transaction(): void
    {
        $gateway = $this->createMock(TapPaymentsGateway::class);
        $service = new PaymentService($gateway, new LedgerService(new CommissionService));

        $result = $service->handleWebhook([
            'gateway_transaction_id' => 'chg_unknown',
            'status' => 'captured',
        ]);

        $this->assertNull($result);
    }

    public function test_handle_webhook_marks_order_failed_on_declined_payment(): void
    {
        $this->order->update(['payment_method' => PaymentTransaction::GATEWAY_TAP]);

        PaymentTransaction::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_transaction_id' => 'chg_declined',
            'status' => PaymentTransaction::STATUS_PROCESSING,
        ]);

        $gateway = $this->createMock(TapPaymentsGateway::class);
        $gateway->method('mapStatus')->willReturn(PaymentTransaction::STATUS_FAILED);
        $service = new PaymentService($gateway, new LedgerService(new CommissionService));

        $result = $service->handleWebhook([
            'gateway_transaction_id' => 'chg_declined',
            'status' => 'declined',
            'raw_response' => ['response' => ['message' => 'Card declined']],
        ]);

        $this->assertEquals(PaymentTransaction::STATUS_FAILED, $result->status);
        $this->assertEquals('failed', $this->order->fresh()->payment_status);
        $this->assertEquals(0, $this->order->fresh()->ledgerEntries()->count());
    }
}
