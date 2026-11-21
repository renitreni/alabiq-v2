<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * Place a new order (checkout).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_address_id' => 'nullable|integer|exists:customer_addresses,id',
            'payment_method' => 'required|string|in:cash_on_delivery,tap_payments',
            'delivery_instructions' => 'nullable|string|max:500',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        $user = Auth::user();

        try {
            $order = $this->orderService->placeOrder($validated, $user);

            return response()->json([
                'data' => $order,
                'message' => 'Order placed successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get authenticated user's orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $status = $request->query('status');

        $orders = $this->orderService->getCustomerOrders($user);

        if ($status) {
            $orders = $orders->filter(fn ($o) => $o->status === $status);
        }

        return response()->json([
            'data' => $orders->values(),
        ]);
    }

    /**
     * Get order details (customer, owning restaurant or super admin).
     */
    public function show(int $id): JsonResponse
    {
        $order = $this->orderService->getOrderWithDetails($id);

        if (! $order) {
            return response()->json([
                'message' => 'Order not found',
            ], 404);
        }

        $this->authorize('view', $order);

        return response()->json([
            'data' => $order,
        ]);
    }

    /**
     * Update order status (restaurant/rider/admin actions).
     *
     * Invalid transitions and unauthorized actors return 409/403 so the
     * client can distinguish them from validation errors (422).
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:placed,accepted,preparing,ready,rider_assigned,picked_up,on_the_way,delivered,cancelled,refunded',
            'note' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $toStatus = $request->string('status')->toString();
        $note = $request->input('note');

        $success = false;

        // Restaurant actions
        if ($user->hasRole('restaurant')) {
            if ($user->restaurant?->id !== $order->restaurant_id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $this->authorize('update', $order);

            switch ($toStatus) {
                case Order::STATUS_ACCEPTED:
                    $success = $this->orderService->restaurantAccept($order, $order->restaurant_id);
                    break;
                case Order::STATUS_CANCELLED:
                    $success = $this->orderService->restaurantReject($order, $order->restaurant_id, $note ?? 'Restaurant rejected the order');
                    break;
                case Order::STATUS_PREPARING:
                case Order::STATUS_READY:
                    $success = $this->orderService->restaurantUpdatePrepStatus($order, $toStatus, $order->restaurant_id);
                    break;
                case Order::STATUS_RIDER_ASSIGNED:
                    $success = $this->orderService->assignRider($order, $user->id, 'restaurant');
                    break;
            }
        }
        // Rider actions
        elseif ($user->hasRole('rider')) {
            switch ($toStatus) {
                case Order::STATUS_PICKED_UP:
                    $success = $this->orderService->riderPickup($order, $user->id);
                    break;
                case Order::STATUS_ON_THE_WAY:
                    $success = $this->orderService->riderOnTheWay($order, $user->id);
                    break;
                case Order::STATUS_DELIVERED:
                    $success = $this->orderService->riderDeliver($order, $user->id);
                    break;
            }
        }
        // Admin actions
        elseif ($user->hasRole('super_admin')) {
            switch ($toStatus) {
                case Order::STATUS_CANCELLED:
                    $success = $this->orderService->adminCancel($order, $user->id, $note ?? 'Admin cancelled the order');
                    break;
                case Order::STATUS_REFUNDED:
                    $success = $this->orderService->processRefund($order, $user->id, $note ?? 'Admin processed refund');
                    break;
                case Order::STATUS_RIDER_ASSIGNED:
                    $success = $this->orderService->assignRider($order, $user->id, 'admin');
                    break;
            }
        }
        // Customer actions (cancel own order)
        elseif ($order->user_id === $user->id) {
            if ($toStatus === Order::STATUS_CANCELLED) {
                $this->authorize('cancel', $order);
                $success = $this->orderService->customerCancel($order, $user->id, $note ?? 'Customer cancelled the order');
            }
        }

        if (! $success) {
            return response()->json([
                'message' => 'Invalid status transition or unauthorized',
            ], 409);
        }

        $order->refresh()->load('statusHistories', 'items');

        return response()->json([
            'data' => $order,
            'message' => 'Order status updated',
        ]);
    }
}
