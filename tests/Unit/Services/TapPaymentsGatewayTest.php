<?php

namespace Tests\Unit\Services;

use App\Services\TapPaymentsGateway;
use Tests\TestCase;

class TapPaymentsGatewayTest extends TestCase
{
    private TapPaymentsGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'alabiq.tap_payments.secret_key' => 'sk_test_secret',
            'alabiq.tap_payments.webhook_secret' => 'sk_test_webhook_secret',
            'alabiq.tap_payments.sandbox' => true,
        ]);
        $this->gateway = new TapPaymentsGateway;
    }

    private function makeWebhookPayload(): array
    {
        return [
            'data' => [
                'object' => [
                    'id' => 'chg_12345',
                    'amount' => 110.00,
                    'currency' => 'SAR',
                    'reference' => [
                        'gateway' => 'gw_123',
                        'payment' => 'pay_456',
                    ],
                    'status' => 'CAPTURED',
                    'transaction' => [
                        'created' => '1700000000000',
                    ],
                    'metadata' => [
                        'order_id' => 42,
                    ],
                ],
            ],
        ];
    }

    private function computeSignature(array $payload): string
    {
        $data = $payload['data']['object'];

        $string = 'x_id'.$data['id']
            .'x_amount'.$data['amount']
            .'x_currency'.$data['currency']
            .'x_gateway_reference'.$data['reference']['gateway']
            .'x_payment_reference'.$data['reference']['payment']
            .'x_status'.$data['status']
            .'x_created'.$data['transaction']['created']
            .'';

        return hash_hmac('sha256', $string, 'sk_test_webhook_secret');
    }

    public function test_valid_webhook_signature_is_accepted(): void
    {
        $payload = $this->makeWebhookPayload();
        $signature = $this->computeSignature($payload);

        $this->assertTrue($this->gateway->validateWebhookSignature($payload, $signature));
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $payload = $this->makeWebhookPayload();

        $this->assertFalse($this->gateway->validateWebhookSignature($payload, 'invalid_signature'));
    }

    public function test_tampered_payload_fails_signature_validation(): void
    {
        $payload = $this->makeWebhookPayload();
        $signature = $this->computeSignature($payload);

        $payload['data']['object']['amount'] = 999.99;

        $this->assertFalse($this->gateway->validateWebhookSignature($payload, $signature));
    }

    public function test_parse_webhook_payload_extracts_expected_fields(): void
    {
        $payload = $this->makeWebhookPayload();

        $parsed = $this->gateway->parseWebhookPayload($payload);

        $this->assertEquals('chg_12345', $parsed['gateway_transaction_id']);
        $this->assertEquals('CAPTURED', $parsed['status']);
        $this->assertEquals(110.00, $parsed['amount']);
        $this->assertEquals('SAR', $parsed['currency']);
        $this->assertEquals('gw_123', $parsed['gateway_reference']);
        $this->assertEquals('pay_456', $parsed['payment_reference']);
        $this->assertEquals(42, $parsed['order_id']);
    }

    public function test_map_status_handles_tap_statuses(): void
    {
        $this->assertEquals('succeeded', $this->gateway->mapStatus('CAPTURED'));
        $this->assertEquals('succeeded', $this->gateway->mapStatus('approved'));
        $this->assertEquals('failed', $this->gateway->mapStatus('DECLINED'));
        $this->assertEquals('pending', $this->gateway->mapStatus('IN_PROGRESS'));
        $this->assertEquals('refunded', $this->gateway->mapStatus('REFUNDED'));
    }
}
