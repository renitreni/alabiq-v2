<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Process a refund (super admin only).
     */
    public function refund(Request $request, PaymentTransaction $transaction): JsonResponse
    {
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->paymentService->refund(
                $transaction,
                (float) $validated['amount'],
                $user->id,
                $validated['reason'] ?? null
            );

            return response()->json([
                'data' => [
                    'transaction' => $result['transaction'],
                    'refund' => $result['refund'],
                ],
                'message' => 'Refund processed',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['message' => 'Refund failed'], 500);
        }
    }
}
