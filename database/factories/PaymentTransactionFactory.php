<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_transaction_id' => 'chg_'.$this->faker->unique()->numerify('##########'),
            'idempotency_key' => 'alabiq_'.$this->faker->unique()->uuid(),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'SAR',
            'status' => PaymentTransaction::STATUS_PENDING,
            'refunded_amount' => 0,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn () => ['status' => PaymentTransaction::STATUS_SUCCEEDED]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => PaymentTransaction::STATUS_PROCESSING]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => PaymentTransaction::STATUS_FAILED]);
    }
}
