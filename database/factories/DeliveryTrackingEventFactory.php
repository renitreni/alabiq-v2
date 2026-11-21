<?php

namespace Database\Factories;

use App\Models\DeliveryTrackingEvent;
use App\Models\Order;
use App\Models\Rider;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryTrackingEventFactory extends Factory
{
    protected $model = DeliveryTrackingEvent::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'rider_id' => Rider::factory(),
            'event' => 'assigned',
            'latitude' => $this->faker->latitude(24.5, 25.0),
            'longitude' => $this->faker->longitude(46.5, 47.0),
            'metadata' => null,
        ];
    }
}
