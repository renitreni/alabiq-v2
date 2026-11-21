<?php

namespace Database\Factories;

use App\Models\PaymentWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentWebhook>
 */
class PaymentWebhookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gateway' => 'tap_payments',
            'gateway_event_id' => 'chg_'.fake()->uuid(),
            'signature' => hash('sha256', fake()->sha1()),
            'signature_valid' => true,
            'status' => 'CAPTURED',
            'processed' => true,
            'payload' => ['id' => 'chg_'.fake()->uuid(), 'status' => 'CAPTURED'],
            'error' => null,
        ];
    }
}
