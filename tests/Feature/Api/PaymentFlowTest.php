<?php

namespace Tests\Feature\Api;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhook;
use App\Models\Refund;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('alabiq.tap_payments.webhook_secret', 'test-webhook-secret');
        Config::set('alabiq.tap_payments.secret_key', 'sk_test_xxx');

        $this->customer = User::factory()->role(User::ROLE_CUSTOMER)->create();
        $restaurant = Restaurant::factory()->create();

        $this->order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $restaurant->id,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_method' => PaymentTransaction::GATEWAY_TAP,
            'payment_status' => 'pending',
            'subtotal' => 100.00,
            'delivery_fee' => 10.00,
            'tax_amount' => 15.00,
            'total_amount' => 125.00,
        ]);
    }

    /**
     * Build a Tap-shaped webhook payload with a valid HMAC signature.
     */
    private function signedTapWebhook(string $chargeId, string $status, float $amount = 125.00): array
    {
        $payload = [
            'id' => $chargeId,
            'amount' => $amount,
            'currency' => 'SAR',
            'reference' => ['gateway' => 'ref_gateway', 'payment' => 'ref_payment'],
            'status' => $status,
            'transaction' => ['created' => '1700000000'],
        ];

        $hashString = 'x_id'.$payload['id']
            .'x_amount'.$payload['amount']
            .'x_currency'.$payload['currency']
            .'x_gateway_reference'.$payload['reference']['gateway']
            .'x_payment_reference'.$payload['reference']['payment']
            .'x_status'.$payload['status']
            .'x_created'.$payload['transaction']['created'];

        return [
            'payload' => $payload,
            'signature' => hash_hmac('sha256', $hashString, 'test-webhook-secret'),
        ];
    }

    public function test_initiate_tap_payment_returns_payment_url(): void
    {
        Http::fake([
            'sandbox.api.tap.company/*' => Http::response([
                'id' => 'chg_initiate_1',
                'status' => 'INITIATED',
                'transaction' => ['url' => 'https://checkout.tap.company/xyz'],
            ], 200),
        ]);

        Sanctum::actingAs($this->customer);

        $response = $this->postJson("/api/payments/orders/{$this->order->id}/initiate");

        $response->assertOk()
            ->assertJsonPath('data.payment_url', 'https://checkout.tap.company/xyz');

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $this->order->id,
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_transaction_id' => 'chg_initiate_1',
            'status' => PaymentTransaction::STATUS_PROCESSING,
        ]);
    }

    public function test_initiate_requires_order_ownership(): void
    {
        Sanctum::actingAs(User::factory()->role(User::ROLE_CUSTOMER)->create());

        $this->postJson("/api/payments/orders/{$this->order->id}/initiate")
            ->assertForbidden();
    }

    public function test_initiate_already_paid_order_returns_409(): void
    {
        $this->order->update(['payment_status' => 'paid']);

        Sanctum::actingAs($this->customer);

        $this->postJson("/api/payments/orders/{$this->order->id}/initiate")
            ->assertConflict();
    }

    public function test_cod_initiate_creates_local_transaction_without_gateway(): void
    {
        Http::fake(['sandbox.api.tap.company/*' => Http::response([], 500)]);

        $this->order->update(['payment_method' => PaymentTransaction::GATEWAY_CASH]);

        Sanctum::actingAs($this->customer);

        $response = $this->postJson("/api/payments/orders/{$this->order->id}/initiate");

        $response->assertOk()
            ->assertJsonPath('data.payment_url', null);

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $this->order->id,
            'gateway' => PaymentTransaction::GATEWAY_CASH,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        // The Tap gateway must not be called for COD.
        Http::assertNothingSent();
    }

    public function test_successful_webhook_marks_order_paid_places_it_and_posts_balanced_ledger(): void
    {
        PaymentTransaction::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_transaction_id' => 'chg_success_1',
            'status' => PaymentTransaction::STATUS_PROCESSING,
            'amount' => 125.00,
        ]);

        $webhook = $this->signedTapWebhook('chg_success_1', 'CAPTURED');

        $response = $this->postJson('/api/payments/tap/webhook', $webhook['payload'], [
            'x-tap-signature' => $webhook['signature'],
        ]);

        $response->assertOk()->assertJsonPath('message', 'Webhook processed');

        $this->order->refresh();
        $this->assertEquals('paid', $this->order->payment_status);
        $this->assertEquals(Order::STATUS_PLACED, $this->order->status);

        // Ledger: 3 revenue credits + 1 balancing clearing debit.
        $entries = LedgerEntry::where('order_id', $this->order->id)->get();
        $this->assertCount(4, $entries);
        $this->assertEquals(
            (float) $entries->where('type', 'debit')->sum('amount'),
            (float) $entries->where('type', 'credit')->sum('amount')
        );

        // Webhook audited as processed.
        $this->assertDatabaseHas('payment_webhooks', [
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_event_id' => 'chg_success_1',
            'signature_valid' => true,
            'processed' => true,
        ]);
    }

    public function test_invalid_signature_returns_401_and_is_audited(): void
    {
        PaymentTransaction::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_transaction_id' => 'chg_bad_sig',
            'status' => PaymentTransaction::STATUS_PROCESSING,
        ]);

        $response = $this->postJson('/api/payments/tap/webhook', [
            'id' => 'chg_bad_sig',
            'amount' => 125.00,
            'currency' => 'SAR',
            'status' => 'CAPTURED',
        ], ['x-tap-signature' => 'forged-signature']);

        $response->assertUnauthorized();

        $this->assertDatabaseHas('payment_webhooks', [
            'gateway_event_id' => 'chg_bad_sig',
            'signature_valid' => false,
            'processed' => false,
        ]);

        // The order must remain untouched.
        $this->assertEquals('pending', $this->order->fresh()->payment_status);
    }

    public function test_duplicate_webhook_delivery_is_idempotent(): void
    {
        PaymentTransaction::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_transaction_id' => 'chg_dup_1',
            'status' => PaymentTransaction::STATUS_PROCESSING,
            'amount' => 125.00,
        ]);

        $webhook = $this->signedTapWebhook('chg_dup_1', 'CAPTURED');

        $headers = ['x-tap-signature' => $webhook['signature']];

        $this->postJson('/api/payments/tap/webhook', $webhook['payload'], $headers)->assertOk();
        $this->postJson('/api/payments/tap/webhook', $webhook['payload'], $headers)->assertOk();

        // One set of ledger entries, not two.
        $this->assertCount(4, LedgerEntry::where('order_id', $this->order->id)->get());
        $this->assertEquals(2, PaymentWebhook::count());
    }

    public function test_failed_payment_webhook_marks_order_failed(): void
    {
        PaymentTransaction::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_transaction_id' => 'chg_fail_1',
            'status' => PaymentTransaction::STATUS_PROCESSING,
        ]);

        $webhook = $this->signedTapWebhook('chg_fail_1', 'DECLINED');

        $this->postJson('/api/payments/tap/webhook', $webhook['payload'], [
            'x-tap-signature' => $webhook['signature'],
        ])->assertOk();

        $this->assertEquals('failed', $this->order->fresh()->payment_status);
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $this->order->fresh()->status);
    }

    public function test_admin_refund_endpoint_processes_refund_with_records(): void
    {
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->create();
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => PaymentTransaction::GATEWAY_CASH,
            'status' => PaymentTransaction::STATUS_SUCCEEDED,
            'amount' => 125.00,
            'refunded_amount' => 0,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/payments/transactions/{$transaction->id}/refund", [
            'amount' => 50.00,
            'reason' => 'Partial goodwill refund',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('refunds', [
            'payment_transaction_id' => $transaction->id,
            'order_id' => $this->order->id,
            'amount' => 50.00,
            'reason' => 'Partial goodwill refund',
            'processed_by' => $admin->id,
        ]);

        $this->assertEquals('refunded', $this->order->fresh()->payment_status);
        $this->assertEquals(50.00, (float) $transaction->refresh()->refunded_amount);
    }

    public function test_refund_endpoint_rejects_non_admin(): void
    {
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $this->order->id,
            'status' => PaymentTransaction::STATUS_SUCCEEDED,
        ]);

        Sanctum::actingAs($this->customer);

        $this->postJson("/api/payments/transactions/{$transaction->id}/refund", [
            'amount' => 10.00,
        ])->assertForbidden();
    }

    public function test_refund_endpoint_validates_amount(): void
    {
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->create();
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $this->order->id,
            'status' => PaymentTransaction::STATUS_SUCCEEDED,
            'amount' => 125.00,
        ]);

        Sanctum::actingAs($admin);

        // Exceeds the refundable amount.
        $this->postJson("/api/payments/transactions/{$transaction->id}/refund", [
            'amount' => 500.00,
        ])->assertUnprocessable();

        $this->assertCount(0, Refund::all());
    }

    public function test_status_endpoint_shows_transactions_for_owner(): void
    {
        PaymentTransaction::factory()->create([
            'order_id' => $this->order->id,
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'status' => PaymentTransaction::STATUS_PROCESSING,
        ]);

        Sanctum::actingAs($this->customer);

        $response = $this->getJson("/api/payments/orders/{$this->order->id}/status");

        $response->assertOk()
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonCount(1, 'data.transactions');
    }
}
