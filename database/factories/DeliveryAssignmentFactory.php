<?php

namespace Database\Factories;

use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\Rider;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryAssignmentFactory extends Factory
{
    protected $model = DeliveryAssignment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->ready(),
            'rider_id' => Rider::factory()->online(),
            'status' => DeliveryAssignment::STATUS_ASSIGNED,
            'distance_km' => $this->faker->randomFloat(2, 0.5, 25),
            'delivery_fee' => $this->faker->randomFloat(2, 5, 40),
            'rejected_reason' => null,
            'offered_at' => now(),
            'accepted_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryAssignment::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryAssignment::STATUS_REJECTED,
            'rejected_reason' => 'Too far from branch',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => DeliveryAssignment::STATUS_COMPLETED]);
    }
}
