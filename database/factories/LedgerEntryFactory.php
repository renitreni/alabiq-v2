<?php

namespace Database\Factories;

use App\Models\LedgerEntry;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'account' => LedgerEntry::ACCOUNT_RESTAURANT_REVENUE,
            'type' => LedgerEntry::TYPE_CREDIT,
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'balance_after' => 0,
            'reference' => 'ref_'.$this->faker->unique()->numerify('######'),
        ];
    }
}
