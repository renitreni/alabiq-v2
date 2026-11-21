<?php

namespace Tests\Unit\Validation;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $restaurantOwner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create();
        $this->restaurantOwner = User::factory()->create();
        $this->restaurant = Restaurant::factory()->create([
            'owner_id' => $this->restaurantOwner->id,
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
        ]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_all_valid_transitions_succeed()
    {
        $validTransitions = [
            Order::STATUS_PENDING_PAYMENT => Order::STATUS_PLACED,
            Order::STATUS_PLACED => Order::STATUS_ACCEPTED,
            Order::STATUS_ACCEPTED => Order::STATUS_PREPARING,
            Order::STATUS_PREPARING => Order::STATUS_READY,
            Order::STATUS_READY => Order::STATUS_RIDER_ASSIGNED,
            Order::STATUS_RIDER_ASSIGNED => Order::STATUS_PICKED_UP,
            Order::STATUS_PICKED_UP => Order::STATUS_ON_THE_WAY,
            Order::STATUS_ON_THE_WAY => Order::STATUS_DELIVERED,
            Order::STATUS_DELIVERED => Order::STATUS_REFUNDED,
            // Cancellation paths
            Order::STATUS_PENDING_PAYMENT => Order::STATUS_CANCELLED,
            Order::STATUS_PLACED => Order::STATUS_CANCELLED,
            Order::STATUS_ACCEPTED => Order::STATUS_CANCELLED,
            Order::STATUS_PREPARING => Order::STATUS_CANCELLED,
            Order::STATUS_READY => Order::STATUS_CANCELLED,
            Order::STATUS_RIDER_ASSIGNED => Order::STATUS_CANCELLED,
            Order::STATUS_PICKED_UP => Order::STATUS_CANCELLED,
            Order::STATUS_ON_THE_WAY => Order::STATUS_CANCELLED,
        ];

        foreach ($validTransitions as $fromStatus => $toStatus) {
            $order = Order::factory()->create([
                'user_id' => $this->customer->id,
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'status' => $fromStatus,
            ]);

            $this->assertTrue($order->canTransitionTo($toStatus), "Failed: {$fromStatus} -> {$toStatus}");
        }
    }

    public function test_invalid_transitions_are_rejected()
    {
        $invalidTransitions = [
            Order::STATUS_PENDING_PAYMENT => Order::STATUS_ACCEPTED, // Skip placed
            Order::STATUS_PLACED => Order::STATUS_PREPARING, // Skip accepted
            Order::STATUS_ACCEPTED => Order::STATUS_READY, // Skip preparing
            Order::STATUS_PREPARING => Order::STATUS_RIDER_ASSIGNED, // Skip ready
            Order::STATUS_READY => Order::STATUS_PICKED_UP, // Skip rider_assigned
            Order::STATUS_RIDER_ASSIGNED => Order::STATUS_ON_THE_WAY, // Skip picked_up
            Order::STATUS_PICKED_UP => Order::STATUS_DELIVERED, // Skip on_the_way
            Order::STATUS_PLACED => Order::STATUS_RIDER_ASSIGNED, // Big jump
            Order::STATUS_DELIVERED => Order::STATUS_CANCELLED, // Terminal
            Order::STATUS_CANCELLED => Order::STATUS_PLACED, // Terminal
            Order::STATUS_REFUNDED => Order::STATUS_PLACED, // Terminal
        ];

        foreach ($invalidTransitions as $fromStatus => $toStatus) {
            $order = Order::factory()->create([
                'user_id' => $this->customer->id,
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'status' => $fromStatus,
            ]);

            $this->assertFalse($order->canTransitionTo($toStatus), "Should fail: {$fromStatus} -> {$toStatus}");
        }
    }

    public function test_cancelled_is_terminal_from_any_active_state()
    {
        $activeStatuses = [
            Order::STATUS_PENDING_PAYMENT,
            Order::STATUS_PLACED,
            Order::STATUS_ACCEPTED,
            Order::STATUS_PREPARING,
            Order::STATUS_READY,
            Order::STATUS_RIDER_ASSIGNED,
            Order::STATUS_PICKED_UP,
            Order::STATUS_ON_THE_WAY,
        ];

        foreach ($activeStatuses as $status) {
            $order = Order::factory()->create([
                'user_id' => $this->customer->id,
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'status' => $status,
            ]);

            $this->assertTrue($order->canTransitionTo(Order::STATUS_CANCELLED), "Can cancel from {$status}");
        }
    }

    public function test_terminal_statuses_cannot_transition_anywhere()
    {
        // cancelled and refunded are truly terminal (no transitions)
        $fullyTerminalStatuses = [
            Order::STATUS_CANCELLED,
            Order::STATUS_REFUNDED,
        ];

        foreach ($fullyTerminalStatuses as $status) {
            $order = Order::factory()->create([
                'user_id' => $this->customer->id,
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'status' => $status,
            ]);

            $this->assertTrue($order->isTerminal(), "{$status} is terminal");

            // Try all possible transitions
            foreach (Order::VALID_TRANSITIONS as $fromStatus => $toStatuses) {
                if ($fromStatus === $status) {
                    $this->assertEmpty($toStatuses, "Terminal status {$status} should have no valid transitions");
                }
            }

            // Explicitly test common targets
            foreach ([Order::STATUS_PLACED, Order::STATUS_ACCEPTED, Order::STATUS_PREPARING, Order::STATUS_READY, Order::STATUS_RIDER_ASSIGNED, Order::STATUS_PICKED_UP, Order::STATUS_ON_THE_WAY, Order::STATUS_DELIVERED, Order::STATUS_CANCELLED, Order::STATUS_REFUNDED] as $target) {
                if ($target !== $status) {
                    $this->assertFalse($order->canTransitionTo($target), "Terminal {$status} cannot go to {$target}");
                }
            }
        }

        // delivered can transition to refunded
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => Order::STATUS_DELIVERED,
        ]);

        $this->assertTrue($order->isTerminal(), 'delivered is terminal');
        $this->assertTrue($order->canTransitionTo(Order::STATUS_REFUNDED), 'delivered can go to refunded');
        $this->assertFalse($order->canTransitionTo(Order::STATUS_CANCELLED), 'delivered cannot go to cancelled');
        $this->assertFalse($order->canTransitionTo(Order::STATUS_ON_THE_WAY), 'delivered cannot go back to on_the_way');
    }

    public function test_delivered_can_only_go_to_refunded()
    {
        $order = Order::factory()->delivered()->create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->assertTrue($order->canTransitionTo(Order::STATUS_REFUNDED));
        $this->assertFalse($order->canTransitionTo(Order::STATUS_CANCELLED));
        $this->assertFalse($order->canTransitionTo(Order::STATUS_ON_THE_WAY));
    }

    public function test_is_terminal_accessor_works()
    {
        $terminalOrder = Order::factory()->delivered()->create();
        $this->assertTrue($terminalOrder->is_terminal);
        $this->assertTrue($terminalOrder->isTerminal());

        $activeOrder = Order::factory()->placed()->create();
        $this->assertFalse($activeOrder->is_terminal);
        $this->assertFalse($activeOrder->isTerminal());
    }

    public function test_status_label_accessor_returns_human_readable_labels()
    {
        $labels = [
            Order::STATUS_PENDING_PAYMENT => 'Pending Payment',
            Order::STATUS_PLACED => 'Order Placed',
            Order::STATUS_ACCEPTED => 'Accepted by Restaurant',
            Order::STATUS_PREPARING => 'Preparing',
            Order::STATUS_READY => 'Ready for Pickup',
            Order::STATUS_RIDER_ASSIGNED => 'Rider Assigned',
            Order::STATUS_PICKED_UP => 'Picked Up',
            Order::STATUS_ON_THE_WAY => 'On the Way',
            Order::STATUS_DELIVERED => 'Delivered',
            Order::STATUS_CANCELLED => 'Cancelled',
            Order::STATUS_REFUNDED => 'Refunded',
        ];

        foreach ($labels as $status => $expectedLabel) {
            $order = Order::factory()->create(['status' => $status]);
            $this->assertEquals($expectedLabel, $order->status_label);
        }
    }

    public function test_scopes_return_correct_orders()
    {
        Order::factory()->pendingPayment()->create(['user_id' => $this->customer->id, 'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);
        Order::factory()->placed()->create(['user_id' => $this->customer->id, 'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);
        Order::factory()->accepted()->create(['user_id' => $this->customer->id, 'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);
        Order::factory()->preparing()->create(['user_id' => $this->customer->id, 'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);
        Order::factory()->ready()->create(['user_id' => $this->customer->id, 'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);
        Order::factory()->riderAssigned()->create(['user_id' => $this->customer->id, 'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);
        Order::factory()->pickedUp()->create(['user_id' => $this->customer->id, 'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);
        Order::factory()->onTheWay()->create(['user_id' => $this->customer->id, 'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);
        Order::factory()->delivered()->create(['user_id' => $this->customer->id, 'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);
        Order::factory()->cancelled()->create(['user_id' => $this->customer->id, 'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);
        Order::factory()->refunded()->create(['user_id' => $this->customer->id, 'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);

        $this->assertEquals(1, Order::pendingPayment()->count());
        $this->assertEquals(1, Order::placed()->count());
        $this->assertEquals(1, Order::accepted()->count());
        $this->assertEquals(1, Order::preparing()->count());
        $this->assertEquals(1, Order::ready()->count());
        $this->assertEquals(1, Order::riderAssigned()->count());
        $this->assertEquals(1, Order::pickedUp()->count());
        $this->assertEquals(1, Order::onTheWay()->count());
        $this->assertEquals(1, Order::preparing()->count());
        $this->assertEquals(1, Order::ready()->count());
        $this->assertEquals(1, Order::riderAssigned()->count());
        $this->assertEquals(1, Order::pickedUp()->count());
        $this->assertEquals(1, Order::onTheWay()->count());
        $this->assertEquals(1, Order::delivered()->count());
        $this->assertEquals(1, Order::cancelled()->count());
        $this->assertEquals(1, Order::refunded()->count());
        // Active = all except delivered, cancelled, refunded = 8
        $this->assertEquals(8, Order::active()->count());
    }
}
