<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;

class TapPaymentsGateway implements PaymentGateway
{
    private string $baseUrl;

    private string $secretKey;

    private string $webhookSecret;

    public function __construct()
    {
        $sandbox = config('alabiq.tap_payments.sandbox', true);
        $this->baseUrl = $sandbox
            ? 'https://sandbox.api.tap.company/v2'
            : 'https://api.tap.company/v2';
        $this->secretKey = (string) config('alabiq.tap_payments.secret_key', '');
        $this->webhookSecret = (string) config('alabiq.tap_payments.webhook_secret', '');
    }

    public function initiate(Order $order, PaymentTransaction $transaction): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl.'/charges', [
            'amount' => (float) $order->total_amount,
            'currency' => config('alabiq.currency', 'SAR'),
            'customer' => [
                'first_name' => $order->user->name ?? 'Customer',
                'email' => $order->user->email ?? 'customer@example.com',
            ],
            'source' => ['type' => 'src_all'],
            'redirect' => [
                'url' => route('payments.tap.return', ['order' => $order->id]),
            ],
            'post' => [
                'url' => route('payments.tap.webhook'),
            ],
            'metadata' => [
                'order_id' => $order->id,
                'transaction_id' => $transaction->idempotency_key,
            ],
            'reference' => [
                'order' => (string) $order->id,
            ],
            'description' => 'Alabiq Order #'.$order->id,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Tap Payments charge creation failed: '.$response->body()
            );
        }

        $data = $response->json();

        return [
            'gateway_transaction_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'payment_url' => $data['transaction']['url'] ?? null,
            'raw_response' => $data,
        ];
    }

    public function verify(string $gatewayTransactionId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->secretKey,
        ])->get($this->baseUrl.'/charges/'.$gatewayTransactionId);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Tap Payments charge verification failed: '.$response->body()
            );
        }

        $data = $response->json();

        return [
            'gateway_transaction_id' => $data['id'] ?? $gatewayTransactionId,
            'status' => $data['status'] ?? 'unknown',
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? config('alabiq.currency', 'SAR'),
            'raw_response' => $data,
        ];
    }

    public function refund(PaymentTransaction $transaction, float $amount): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl.'/refunds', [
            'charge_id' => $transaction->gateway_transaction_id,
            'amount' => $amount,
            'currency' => $transaction->currency,
            'reason' => 'requested_by_customer',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Tap Payments refund failed: '.$response->body()
            );
        }

        $data = $response->json();

        return [
            'refund_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'amount' => $data['amount'] ?? $amount,
            'raw_response' => $data,
        ];
    }

    public function validateWebhookSignature(array $payload, string $signature): bool
    {
        $data = $payload['data']['object'] ?? $payload;

        $hashString = 'x_id'.($data['id'] ?? '')
            .'x_amount'.($data['amount'] ?? '')
            .'x_currency'.($data['currency'] ?? '')
            .'x_gateway_reference'.($data['reference']['gateway'] ?? '')
            .'x_payment_reference'.($data['reference']['payment'] ?? '')
            .'x_status'.($data['status'] ?? '')
            .'x_created'.($data['transaction']['created'] ?? '')
            .'';

        $expected = hash_hmac('sha256', $hashString, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookPayload(array $payload): array
    {
        $data = $payload['data']['object'] ?? $payload;

        return [
            'gateway_transaction_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'gateway_reference' => $data['reference']['gateway'] ?? null,
            'payment_reference' => $data['reference']['payment'] ?? null,
            'order_id' => $data['metadata']['order_id'] ?? null,
            'raw_response' => $data,
        ];
    }

    /**
     * Map Tap charge status to internal PaymentTransaction status.
     */
    public function mapStatus(string $tapStatus): string
    {
        return match (strtolower($tapStatus)) {
            'captured', 'approved', 'succeeded' => PaymentTransaction::STATUS_SUCCEEDED,
            'failed', 'declined', 'cancelled', 'void' => PaymentTransaction::STATUS_FAILED,
            'in_progress', 'pending', 'initiated' => PaymentTransaction::STATUS_PENDING,
            'refunded' => PaymentTransaction::STATUS_REFUNDED,
            default => PaymentTransaction::STATUS_PENDING,
        };
    }
}
