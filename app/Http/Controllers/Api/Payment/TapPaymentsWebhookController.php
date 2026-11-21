<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhook;
use App\Services\PaymentService;
use App\Services\TapPaymentsGateway;
use Illuminate\Http\JsonResponse;
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
    public function return(Request $request): JsonResponse
    {
        $status = $request->query('status');

        return response()->json([
            'message' => 'Payment return handled',
            'data' => ['status' => $status],
        ]);
    }
}
