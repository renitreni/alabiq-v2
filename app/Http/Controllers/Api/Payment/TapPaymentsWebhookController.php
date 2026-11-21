<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
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
     */
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('x-tap-signature', '');

        if (! $this->gateway->validateWebhookSignature($request->all(), $signature)) {
            report(new \RuntimeException('Invalid Tap webhook signature'));

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $parsed = $this->gateway->parseWebhookPayload($request->all());

        $transaction = $this->paymentService->handleWebhook($parsed);

        return response()->json([
            'message' => $transaction ? 'Webhook processed' : 'Webhook ignored',
            'data' => $transaction,
        ]);
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
