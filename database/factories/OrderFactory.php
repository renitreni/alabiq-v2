<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => $order->status,
                'changed_by' => $order->user_id,
                'changed_by_type' => 'system',
                'note' => 'Order created',
            ]);
        });
    }

    public function definition(): array
    {
        $restaurant = Restaurant::inRandomOrder()->first() ?? Restaurant::factory()->create();
        $branch = $restaurant->activeBranches()->inRandomOrder()->first() ?? RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $user = User::inRandomOrder()->first() ?? User::factory()->create();

        $statuses = [
            Order::STATUS_PENDING_PAYMENT,
            Order::STATUS_PLACED,
            Order::STATUS_ACCEPTED,
            Order::STATUS_PREPARING,
            Order::STATUS_READY,
            Order::STATUS_RIDER_ASSIGNED,
            Order::STATUS_PICKED_UP,
            Order::STATUS_ON_THE_WAY,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ];

        $status = $this->faker->randomElement($statuses);
        $subtotal = $this->faker->randomFloat(2, 20, 200);
        $deliveryFee = $this->faker->randomFloat(2, 0, 30);
        $taxAmount = round($subtotal * 0.15, 2);
        $discountAmount = $this->faker->optional(0.2)->randomFloat(2, 0, 20);
        $totalAmount = $subtotal + $deliveryFee + $taxAmount - $discountAmount;

        return [
            'user_id' => $user->id,
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'customer_address_id' => null,
            'status' => $status,
            'payment_method' => $this->faker->randomElement(['cash_on_delivery', 'tap_payments']),
            'payment_status' => in_array($status, [Order::STATUS_DELIVERED, Order::STATUS_REFUNDED]) ? 'paid' : ($status === Order::STATUS_CANCELLED ? 'refunded' : 'pending'),
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => max(0, $totalAmount),
            'snapshot_data' => [
                'restaurant' => [
                    'name' => $restaurant->name,
                    'phone' => $restaurant->phone,
                    'address' => $restaurant->address,
                ],
                'branch' => [
                    'name' => $branch->name,
                    'phone' => $branch->phone,
                    'address' => $branch->address,
                ],
            ],
            'delivery_instructions' => $this->faker->optional(0.5)->sentence(),
            'estimated_delivery_time' => $this->faker->randomElement([20, 30, 40, 45, 60]),
            'delivered_at' => $status === Order::STATUS_DELIVERED ? $this->faker->dateTimeBetween('-1 week', 'now') : null,
            'cancelled_at' => $status === Order::STATUS_CANCELLED ? $this->faker->dateTimeBetween('-1 week', 'now') : null,
            'cancellation_reason' => $status === Order::STATUS_CANCELLED ? $this->faker->sentence() : null,
        ];
    }

    public function pendingPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => 'pending',
            'discount_amount' => $attributes['discount_amount'] ?? 0,
            'delivered_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);
    }

    public function placed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PLACED,
            'payment_status' => 'pending',
            'discount_amount' => $attributes['discount_amount'] ?? 0,
            'delivered_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => 'pending',
            'discount_amount' => $attributes['discount_amount'] ?? 0,
            'delivered_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);
    }

    public function preparing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PREPARING,
            'payment_status' => 'pending',
            'discount_amount' => $attributes['discount_amount'] ?? 0,
            'delivered_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_READY,
            'payment_status' => 'pending',
            'discount_amount' => $attributes['discount_amount'] ?? 0,
            'delivered_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);
    }

    public function riderAssigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_RIDER_ASSIGNED,
            'payment_status' => 'pending',
            'discount_amount' => $attributes['discount_amount'] ?? 0,
            'delivered_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);
    }

    public function pickedUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PICKED_UP,
            'payment_status' => 'pending',
            'discount_amount' => $attributes['discount_amount'] ?? 0,
            'delivered_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);
    }

    public function onTheWay(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_ON_THE_WAY,
            'payment_status' => 'pending',
            'discount_amount' => $attributes['discount_amount'] ?? 0,
            'delivered_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => 'paid',
            'discount_amount' => $attributes['discount_amount'] ?? 0,
            'delivered_at' => now(),
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => 'refunded',
            'discount_amount' => $attributes['discount_amount'] ?? 0,
            'cancelled_at' => now(),
            'delivered_at' => null,
            'cancellation_reason' => $this->faker->sentence(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_REFUNDED,
            'payment_status' => 'refunded',
            'discount_amount' => $attributes['discount_amount'] ?? 0,
            'delivered_at' => $attributes['delivered_at'] ?? now()->subHour(),
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);
    }
}
