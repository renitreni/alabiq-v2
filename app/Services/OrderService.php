<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private CartService $cartService,
        private ?VatService $vatService = null
    ) {}

    /**
     * Lazily resolve the VAT service so direct `new OrderService(new CartService)`
     * call sites (tests, scripts) keep working.
     */
    private function vat(): VatService
    {
        return $this->vatService ??= new VatService;
    }

    /**
     * Place a new order from the cart.
     */
    public function placeOrder(array $validatedData, User $user): Order
    {
        $cart = $this->cartService->getCartForCheckout();

        if (empty($cart['items'])) {
            throw new \Exception('Cart is empty');
        }

        if (! $cart['branch']) {
            throw new \Exception('No delivery branch selected');
        }

        $validatedData = array_merge($validatedData, [
            'branch_id' => $cart['branch']['id'],
        ]);

        $branch = RestaurantBranch::with('deliveryZones')->find($cart['branch']['id']);
        if (! $branch || ! $branch->accepts_orders || ! $branch->is_active) {
            throw new \Exception('Selected branch is not accepting orders');
        }

        if (! $branch->isOpen()) {
            throw new \Exception('Selected branch is currently closed');
        }

        if ($cart['subtotal'] < $branch->minimum_order) {
            throw new \Exception("Minimum order amount is {$branch->minimum_order} SAR");
        }

        // Validate all items are still available
        foreach ($cart['items'] as $item) {
            $menuItem = MenuItem::find($item['menu_item_id']);
            if (! $menuItem || ! $menuItem->isAvailableAtBranch($cart['branch']['id'])) {
                throw new \Exception("{$item['name']} is no longer available");
            }
        }

        return DB::transaction(function () use ($cart, $validatedData, $user, $branch) {
            $vatAmount = $this->vat()->amount((float) $cart['subtotal']);

            $order = Order::create([
                'user_id' => $user->id,
                'restaurant_id' => $cart['restaurant_id'],
                'branch_id' => $cart['branch']['id'],
                'customer_address_id' => $validatedData['customer_address_id'] ?? null,
                'status' => Order::STATUS_PENDING_PAYMENT,
                'payment_method' => $validatedData['payment_method'] ?? 'cash_on_delivery',
                'payment_status' => 'pending',
                'subtotal' => $cart['subtotal'],
                'delivery_fee' => $cart['delivery_fee'],
                'tax_amount' => $vatAmount,
                'discount_amount' => $validatedData['discount_amount'] ?? 0,
                'total_amount' => $cart['total'] + $vatAmount - ($validatedData['discount_amount'] ?? 0),
                'snapshot_data' => [
                    'restaurant' => [
                        'id' => $cart['branch']['restaurant_id'] ?? $cart['restaurant_id'],
                        'name' => $branch->restaurant->name ?? '',
                        'phone' => $branch->restaurant->phone ?? '',
                        'address' => $branch->restaurant->address ?? '',
                    ],
                    'branch' => [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'phone' => $branch->phone,
                        'address' => $branch->address,
                        'delivery_fee' => (float) $branch->delivery_fee,
                        'minimum_order' => (float) $branch->minimum_order,
                        'estimated_delivery_time' => $branch->estimated_delivery_time,
                    ],
                ],
                'delivery_instructions' => $validatedData['delivery_instructions'] ?? null,
                'estimated_delivery_time' => $branch->estimated_delivery_time,
            ]);

            // Create order items with snapshot pricing
            foreach ($cart['items'] as $item) {
                $menuItem = MenuItem::with('options.values')->find($item['menu_item_id']);

                $optionsSnapshot = [];
                $optionsPrice = 0;

                if (! empty($item['options'])) {
                    foreach ($item['options'] as $selectedOption) {
                        $option = $menuItem->options()->find($selectedOption['option_id']);
                        if (! $option) {
                            continue;
                        }

                        foreach ($selectedOption['value_ids'] as $valueId) {
                            $value = $option->values()->find($valueId);
                            if ($value) {
                                $optionsPrice += (float) $value->price_adjustment;
                                $optionsSnapshot[] = [
                                    'option_id' => $option->id,
                                    'option_name' => $option->name,
                                    'value_id' => $value->id,
                                    'value_name' => $value->name,
                                    'price_adjustment' => (float) $value->price_adjustment,
                                ];
                            }
                        }
                    }
                }

                $price = $menuItem->getPriceAtBranch($branch->id);
                $finalPrice = $price + $optionsPrice;
                $subtotal = round($finalPrice * $item['quantity'], 2);

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'price' => $price,
                    'options_price' => $optionsPrice,
                    'final_price' => $finalPrice,
                    'quantity' => $item['quantity'],
                    'options_snapshot' => $optionsSnapshot ?: null,
                    'subtotal' => $subtotal,
                ]);
            }

            // Create initial status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => Order::STATUS_PENDING_PAYMENT,
                'changed_by' => $user->id,
                'changed_by_type' => 'customer',
                'note' => 'Order placed',
            ]);

            // Clear the cart
            $this->cartService->clear();

            return $order->load('items', 'branch', 'customerAddress', 'restaurant');
        });
    }

    /**
     * Transition order status with validation.
     */
    public function transitionStatus(Order $order, string $toStatus, int $changedBy, string $changedByType, ?string $note = null): bool
    {
        if (! $order->canTransitionTo($toStatus)) {
            return false;
        }

        $fromStatus = $order->status;

        // Special handling for terminal statuses
        $updates = ['status' => $toStatus];
        if ($toStatus === Order::STATUS_DELIVERED) {
            $updates['delivered_at'] = now();
            $updates['payment_status'] = 'paid';
        } elseif ($toStatus === Order::STATUS_CANCELLED) {
            $updates['cancelled_at'] = now();
            $updates['cancellation_reason'] = $note;
            $updates['payment_status'] = 'refunded';
        } elseif ($toStatus === Order::STATUS_REFUNDED) {
            $updates['payment_status'] = 'refunded';
        }

        $order->update($updates);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $changedBy,
            'changed_by_type' => $changedByType,
            'note' => $note,
        ]);

        return true;
    }

    /**
     * Restaurant accepts an order (placed -> accepted).
     */
    public function restaurantAccept(Order $order, int $restaurantId): bool
    {
        if ($order->restaurant_id !== $restaurantId) {
            return false;
        }

        $ownerId = Restaurant::find($restaurantId)?->owner_id ?? $restaurantId;

        return $this->transitionStatus($order, Order::STATUS_ACCEPTED, $ownerId, 'restaurant', 'Restaurant accepted the order');
    }

    /**
     * Restaurant rejects an order (placed -> cancelled).
     */
    public function restaurantReject(Order $order, int $restaurantId, string $reason): bool
    {
        if ($order->restaurant_id !== $restaurantId) {
            return false;
        }

        $ownerId = Restaurant::find($restaurantId)?->owner_id ?? $restaurantId;

        return $this->transitionStatus($order, Order::STATUS_CANCELLED, $ownerId, 'restaurant', $reason);
    }

    /**
     * Restaurant updates prep status (accepted -> preparing -> ready).
     */
    public function restaurantUpdatePrepStatus(Order $order, string $toStatus, int $restaurantId): bool
    {
        if ($order->restaurant_id !== $restaurantId) {
            return false;
        }

        $allowed = [Order::STATUS_PREPARING, Order::STATUS_READY];
        if (! in_array($toStatus, $allowed)) {
            return false;
        }

        $noteMap = [
            Order::STATUS_PREPARING => 'Restaurant started preparing the order',
            Order::STATUS_READY => 'Order is ready for pickup',
        ];

        $ownerId = Restaurant::find($restaurantId)?->owner_id ?? $restaurantId;

        return $this->transitionStatus($order, $toStatus, $ownerId, 'restaurant', $noteMap[$toStatus] ?? null);
    }

    /**
     * Rider picks up order (rider_assigned -> picked_up).
     */
    public function riderPickup(Order $order, int $riderId): bool
    {
        return $this->transitionStatus($order, Order::STATUS_PICKED_UP, $riderId, 'rider', 'Rider picked up the order');
    }

    /**
     * Rider marks order as on the way (picked_up -> on_the_way).
     */
    public function riderOnTheWay(Order $order, int $riderId): bool
    {
        return $this->transitionStatus($order, Order::STATUS_ON_THE_WAY, $riderId, 'rider', 'Order is on the way to customer');
    }

    /**
     * Rider delivers order (on_the_way -> delivered).
     */
    public function riderDeliver(Order $order, int $riderId): bool
    {
        return $this->transitionStatus($order, Order::STATUS_DELIVERED, $riderId, 'rider', 'Order delivered to customer');
    }

    /**
     * Customer cancels order (pending_payment/placed -> cancelled).
     */
    public function customerCancel(Order $order, int $userId, string $reason): bool
    {
        if ($order->user_id !== $userId) {
            return false;
        }

        return $this->transitionStatus($order, Order::STATUS_CANCELLED, $userId, 'customer', $reason);
    }

    /**
     * Admin cancels order.
     */
    public function adminCancel(Order $order, int $adminId, string $reason): bool
    {
        return $this->transitionStatus($order, Order::STATUS_CANCELLED, $adminId, 'admin', $reason);
    }

    /**
     * Process refund (delivered -> refunded).
     */
    public function processRefund(Order $order, int $adminId, string $reason): bool
    {
        if ($order->status !== Order::STATUS_DELIVERED) {
            return false;
        }

        return $this->transitionStatus($order, Order::STATUS_REFUNDED, $adminId, 'admin', $reason);
    }

    /**
     * Get orders for a customer.
     */
    public function getCustomerOrders(User $user): Collection
    {
        return Order::where('user_id', $user->id)
            ->with(['items.menuItem', 'branch', 'restaurant', 'customerAddress', 'statusHistories'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get orders for a restaurant.
     */
    public function getRestaurantOrders(int $restaurantId, ?string $status = null): Collection
    {
        $query = Order::where('restaurant_id', $restaurantId)
            ->with(['items.menuItem', 'branch', 'user', 'customerAddress', 'statusHistories'])
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Get order by ID with relationships.
     */
    public function getOrderWithDetails(int $orderId): ?Order
    {
        return Order::with(['items.menuItem', 'branch', 'restaurant', 'user', 'customerAddress', 'statusHistories'])
            ->find($orderId);
    }
}
