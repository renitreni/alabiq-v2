<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Initiate a payment for an order.
     */
    public function initiate(Order $order): JsonResponse
    {
        $user = Auth::user();

        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Order already paid',
                'data' => ['order' => $order],
            ], 409);
        }

        try {
            $result = $this->paymentService->initiate($order);

            return response()->json([
                'data' => [
                    'transaction' => $result['transaction'],
                    'payment_url' => $result['payment_url'] ?? null,
                    'message' => $result['message'] ?? null,
                ],
                'message' => 'Payment initiated',
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Payment initiation failed',
            ], 500);
        }
    }

    /**
     * Get payment status for an order.
     */
    public function status(Order $order): JsonResponse
    {
        $user = Auth::user();

        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transactions = $order->transactions()->latest()->get();

        return response()->json([
            'data' => [
                'payment_status' => $order->payment_status,
                'transactions' => $transactions,
            ],
        ]);
    }
}
