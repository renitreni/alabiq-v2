<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhook;
use App\Services\PaymentService;
use App\Services\TapPaymentsGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TapPaymentsWebhookController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private TapPaymentsGateway $gateway
    ) {}

    /**
     * Handle incoming Tap Payments webhook.
     *
     * Every delivery is audited (valid or invalid signature) before any
     * processing happens.
     */
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('x-tap-signature', '');
        $payload = $request->all();
        $valid = $this->gateway->validateWebhookSignature($payload, $signature);
        $parsed = $valid ? $this->gateway->parseWebhookPayload($payload) : null;

        $webhook = PaymentWebhook::create([
            'gateway' => PaymentTransaction::GATEWAY_TAP,
            'gateway_event_id' => $parsed['gateway_transaction_id'] ?? $request->input('id'),
            'signature' => $signature,
            'signature_valid' => $valid,
            'status' => $parsed['status'] ?? null,
            'processed' => false,
            'payload' => $payload,
        ]);

        if (! $valid) {
            report(new \RuntimeException('Invalid Tap webhook signature'));

            $webhook->update(['error' => 'Invalid signature']);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        try {
            $transaction = $this->paymentService->handleWebhook($parsed);

            $webhook->update([
                'processed' => $transaction !== null,
                'error' => $transaction === null ? 'No matching transaction' : null,
            ]);

            return response()->json([
                'message' => $transaction ? 'Webhook processed' : 'Webhook ignored',
                'data' => $transaction,
            ]);
        } catch (\Throwable $e) {
            report($e);

            $webhook->update(['error' => $e->getMessage()]);

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle customer return from Tap checkout (redirect URL).
     */
    public function return(Request $request): RedirectResponse
    {
        $orderId = (int) $request->query('order');
        $chargeId = (string) $request->query('tap_id');

        // Never trust the redirect query: confirm the charge with Tap itself.
        if ($chargeId !== '') {
            try {
                $charge = $this->gateway->verify($chargeId);
                $this->paymentService->handleWebhook([
                    'gateway_transaction_id' => $charge['gateway_transaction_id'],
                    'status' => $charge['status'],
                    'raw_response' => $charge['raw_response'],
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('customer.orders.show', ['order' => $orderId]);
    }
}
