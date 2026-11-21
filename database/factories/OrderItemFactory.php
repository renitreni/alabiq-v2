<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $menuItem = MenuItem::inRandomOrder()->first() ?? MenuItem::factory()->create();
        $price = (float) $menuItem->price;
        $optionsPrice = $this->faker->optional(0.3)->randomFloat(2, 0, 15);
        $finalPrice = $price + $optionsPrice;
        $quantity = $this->faker->randomElement([1, 1, 1, 2, 2, 3]);
        $subtotal = round($finalPrice * $quantity, 2);

        return [
            'order_id' => Order::factory(),
            'menu_item_id' => $menuItem->id,
            'name' => $menuItem->name,
            'price' => $price,
            'options_price' => $optionsPrice,
            'final_price' => $finalPrice,
            'quantity' => $quantity,
            'options_snapshot' => $optionsPrice > 0 ? [
                'options' => [
                    [
                        'option_id' => $this->faker->randomNumber(2),
                        'option_name' => 'Size',
                        'value_id' => $this->faker->randomNumber(2),
                        'value_name' => 'Large',
                        'price_adjustment' => $optionsPrice,
                    ],
                ],
            ] : null,
            'subtotal' => $subtotal,
        ];
    }
}
