<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TapPaymentReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_verifies_charge_with_tap_and_redirects_to_the_order(): void
    {
        $order = Order::factory()->create(['payment_method' => 'tap_payments', 'payment_status' => 'pending']);
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_transaction_id' => 'chg_test_1',
            'status' => PaymentTransaction::STATUS_PROCESSING,
        ]);

        Http::fake(['*/charges/chg_test_1' => Http::response(['id' => 'chg_test_1', 'status' => 'CAPTURED'])]);

        $this->get('/api/payments/tap/return?order='.$order->id.'&tap_id=chg_test_1')
            ->assertRedirect(route('customer.orders.show', ['order' => $order->id]));

        $this->assertSame(PaymentTransaction::STATUS_SUCCEEDED, $transaction->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_return_ignores_an_unverified_success_claim(): void
    {
        $order = Order::factory()->create(['payment_method' => 'tap_payments', 'payment_status' => 'pending']);

        Http::fake(['*' => Http::response([], 500)]);

        $this->get('/api/payments/tap/return?order='.$order->id.'&tap_id=chg_bad&status=CAPTURED')
            ->assertRedirect();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }
}
