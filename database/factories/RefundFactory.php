<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_transaction_id' => PaymentTransaction::factory(),
            'order_id' => Order::factory(),
            'amount' => fake()->randomFloat(2, 1, 500),
            'currency' => 'SAR',
            'reason' => fake()->randomElement(['Customer request', 'Order cancelled', 'Item unavailable']),
            'processed_by' => User::factory(),
            'status' => 'processed',
            'gateway_response' => null,
        ];
    }
}
