<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;

interface PaymentGateway
{
    /**
     * Initiate a payment and return gateway response containing payment URL/client token.
     */
    public function initiate(Order $order, PaymentTransaction $transaction): array;

    /**
     * Verify a payment with the gateway.
     */
    public function verify(string $gatewayTransactionId): array;

    /**
     * Process a refund with the gateway.
     */
    public function refund(PaymentTransaction $transaction, float $amount): array;

    /**
     * Validate an incoming webhook payload signature.
     */
    public function validateWebhookSignature(array $payload, string $signature): bool;

    /**
     * Extract standardized data from a gateway webhook payload.
     */
    public function parseWebhookPayload(array $payload): array;
}
