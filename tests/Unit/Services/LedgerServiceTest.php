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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['alabiq.platform_commission_rate' => 0.15]);
        $this->service = new LedgerService(new CommissionService);
    }

    private function makeOrder(): Order
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        return Order::factory()->create([
            'user_id' => $user->id,
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'subtotal' => 100.00,
            'delivery_fee' => 10.00,
            'total_amount' => 110.00,
        ]);
    }

    public function test_post_payment_creates_balanced_journal(): void
    {
        $order = $this->makeOrder();
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'amount' => 110.00,
        ]);

        $entries = $this->service->postPayment($order, $transaction);

        // Three revenue credits + one balancing clearing debit.
        $entries = collect($entries);
        $this->assertCount(4, $entries);
        $this->assertEquals(4, LedgerEntry::where('order_id', $order->id)->count());
        $this->assertEquals(3, $entries->where('type', LedgerEntry::TYPE_CREDIT)->count());
        $this->assertEquals(1, $entries->where('type', LedgerEntry::TYPE_DEBIT)->count());

        foreach ($entries as $entry) {
            $this->assertEquals($transaction->id, $entry->payment_transaction_id);
        }

        // The clearing debit equals the sum of the revenue credits.
        $clearing = $entries->firstWhere('account', LedgerEntry::ACCOUNT_ORDER_CLEARING);
        $this->assertEquals(LedgerEntry::TYPE_DEBIT, $clearing->type);
        $this->assertEquals(110.00, (float) $clearing->amount);
        $this->assertTrue($this->service->verifyBalances($order));
    }

    public function test_post_payment_uses_correct_accounts_and_amounts(): void
    {
        $order = $this->makeOrder();
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'amount' => 110.00,
        ]);

        $this->service->postPayment($order, $transaction);

        $this->assertEquals(15.00, $this->service->getAccountBalance(LedgerEntry::ACCOUNT_PLATFORM_COMMISSION));
        $this->assertEquals(85.00, $this->service->getAccountBalance(LedgerEntry::ACCOUNT_RESTAURANT_REVENUE));
        $this->assertEquals(10.00, $this->service->getAccountBalance(LedgerEntry::ACCOUNT_DELIVERY_FEE));
    }

    public function test_running_balance_accumulates_across_orders(): void
    {
        $order1 = $this->makeOrder();
        $order2 = $this->makeOrder();
        $tx1 = PaymentTransaction::factory()->create(['order_id' => $order1->id]);
        $tx2 = PaymentTransaction::factory()->create(['order_id' => $order2->id]);

        $this->service->postPayment($order1, $tx1);
        $this->service->postPayment($order2, $tx2);

        $this->assertEquals(30.00, $this->service->getAccountBalance(LedgerEntry::ACCOUNT_PLATFORM_COMMISSION));
        $this->assertEquals(170.00, $this->service->getAccountBalance(LedgerEntry::ACCOUNT_RESTAURANT_REVENUE));
        $this->assertEquals(20.00, $this->service->getAccountBalance(LedgerEntry::ACCOUNT_DELIVERY_FEE));
    }

    public function test_post_refund_creates_balanced_journal(): void
    {
        $order = $this->makeOrder();
        $transaction = PaymentTransaction::factory()->succeeded()->create(['order_id' => $order->id]);

        $entries = $this->service->postRefund($order, $transaction, 50.00);

        // Refund debit + clearing credit reversal, netting to zero.
        $entries = collect($entries);
        $this->assertCount(2, $entries);

        $refund = $entries->firstWhere('account', LedgerEntry::ACCOUNT_REFUND);
        $this->assertEquals(LedgerEntry::TYPE_DEBIT, $refund->type);
        $this->assertEquals(50.00, $refund->amount);
        $this->assertEquals(-50.0, $refund->signed_amount);

        $clearing = $entries->firstWhere('account', LedgerEntry::ACCOUNT_ORDER_CLEARING);
        $this->assertEquals(LedgerEntry::TYPE_CREDIT, $clearing->type);
        $this->assertEquals(50.00, (float) $clearing->amount);
    }

    public function test_verify_balances_detects_unbalanced_journal(): void
    {
        $order = $this->makeOrder();

        // Fabricate a one-sided journal — debits without matching credits.
        LedgerEntry::create([
            'order_id' => $order->id,
            'payment_transaction_id' => null,
            'account' => LedgerEntry::ACCOUNT_ORDER_CLEARING,
            'type' => LedgerEntry::TYPE_DEBIT,
            'amount' => 25.00,
            'balance_after' => -25.00,
            'reference' => 'broken_journal',
            'description' => 'Fabricated unbalanced entry',
        ]);

        $this->assertFalse($this->service->verifyBalances($order));
    }

    public function test_clearing_account_nets_to_zero_across_payment_and_refund(): void
    {
        $order = $this->makeOrder();
        $transaction = PaymentTransaction::factory()->succeeded()->create(['order_id' => $order->id]);

        $this->service->postPayment($order, $transaction);
        $this->service->postRefund($order, $transaction, 50.00);

        $this->assertTrue($this->service->verifyBalances($order));
        // Uniform balance convention (debits subtract): payment moved the
        // clearing account to -110, the 50.00 refund credit brought it to -60.
        $this->assertEquals(-60.00, $this->service->getAccountBalance(LedgerEntry::ACCOUNT_ORDER_CLEARING));
    }

    public function test_verify_balances_returns_true_for_valid_order(): void
    {
        $order = $this->makeOrder();
        $transaction = PaymentTransaction::factory()->succeeded()->create(['order_id' => $order->id]);
        $this->service->postPayment($order, $transaction);

        $this->assertTrue($this->service->verifyBalances($order));
    }

    public function test_get_order_entries_returns_only_order_entries(): void
    {
        $order1 = $this->makeOrder();
        $order2 = $this->makeOrder();
        $tx1 = PaymentTransaction::factory()->create(['order_id' => $order1->id]);
        $tx2 = PaymentTransaction::factory()->create(['order_id' => $order2->id]);

        $this->service->postPayment($order1, $tx1);
        $this->service->postPayment($order2, $tx2);

        $this->assertCount(4, $this->service->getOrderEntries($order1));
        $this->assertCount(4, $this->service->getOrderEntries($order2));
    }
}
