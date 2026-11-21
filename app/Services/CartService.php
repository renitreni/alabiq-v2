<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\RestaurantBranch;
use Illuminate\Support\Facades\Session;

class CartService
{
    const SESSION_KEY = 'alabiq_cart';

    public function getCart(): array
    {
        return Session::get(self::SESSION_KEY, [
            'restaurant_id' => null,
            'branch_id' => null,
            'items' => [],
            'subtotal' => 0,
        ]);
    }

    public function getCartId(): ?int
    {
        return Session::get(self::SESSION_KEY.'.restaurant_id');
    }

    public function getBranchId(): ?int
    {
        return Session::get(self::SESSION_KEY.'.branch_id');
    }

    public function isEmpty(): bool
    {
        $cart = $this->getCart();

        return empty($cart['items']);
    }

    public function getItemCount(): int
    {
        $cart = $this->getCart();

        return array_sum(array_column($cart['items'], 'quantity'));
    }

    public function getSubtotal(): float
    {
        $cart = $this->getCart();

        return (float) $cart['subtotal'];
    }

    public function addItem(int $menuItemId, int $quantity = 1, array $options = []): array
    {
        $menuItem = MenuItem::with(['options.values', 'restaurant', 'category'])->findOrFail($menuItemId);

        $cart = $this->getCart();

        if ($cart['restaurant_id'] && $cart['restaurant_id'] !== $menuItem->restaurant_id) {
            throw new \Exception('Cannot add items from different restaurants. Please clear your cart first.');
        }

        $branchId = $cart['branch_id'] ?? $menuItem->restaurant->getPrimaryBranch()?->id;
        if ($branchId && ! $menuItem->isAvailableAtBranch($branchId)) {
            throw new \Exception('This item is not available at the selected branch.');
        }

        $price = $branchId ? $menuItem->getPriceAtBranch($branchId) : $menuItem->price;

        $optionsPrice = 0;
        $selectedOptions = [];
        foreach ($options as $optionId => $valueIds) {
            $option = $menuItem->options()->find($optionId);
            if (! $option) {
                continue;
            }

            $valueIds = is_array($valueIds) ? $valueIds : [$valueIds];
            foreach ($valueIds as $valueId) {
                $value = $option->values()->find($valueId);
                if ($value) {
                    $optionsPrice += (float) $value->price_adjustment;
                    $selectedOptions[] = [
                        'option_id' => $optionId,
                        'option_name' => $option->name,
                        'value_id' => $valueId,
                        'value_name' => $value->name,
                        'price_adjustment' => (float) $value->price_adjustment,
                    ];
                }
            }
        }

        $finalPrice = $price + $optionsPrice;

        $itemKey = $this->generateItemKey($menuItemId, $options);

        if (isset($cart['items'][$itemKey])) {
            $cart['items'][$itemKey]['quantity'] += $quantity;
        } else {
            $cart['items'][$itemKey] = [
                'menu_item_id' => $menuItemId,
                'name' => $menuItem->name,
                'price' => $price,
                'options_price' => $optionsPrice,
                'final_price' => $finalPrice,
                'quantity' => $quantity,
                'options' => $selectedOptions,
                'image' => $menuItem->image,
                'category' => $menuItem->category?->name,
            ];
        }

        $cart['restaurant_id'] = $menuItem->restaurant_id;
        $cart['branch_id'] = $branchId;
        $cart['subtotal'] = $this->calculateSubtotal($cart['items']);

        Session::put(self::SESSION_KEY, $cart);

        return $this->getCart();
    }

    public function updateQuantity(string $itemKey, int $quantity): array
    {
        $cart = $this->getCart();

        if (! isset($cart['items'][$itemKey])) {
            throw new \Exception('Item not found in cart');
        }

        if ($quantity <= 0) {
            unset($cart['items'][$itemKey]);
        } else {
            $cart['items'][$itemKey]['quantity'] = $quantity;
        }

        $cart['subtotal'] = $this->calculateSubtotal($cart['items']);

        if (empty($cart['items'])) {
            $cart['restaurant_id'] = null;
            $cart['branch_id'] = null;
        }

        Session::put(self::SESSION_KEY, $cart);

        return $this->getCart();
    }

    public function removeItem(string $itemKey): array
    {
        $cart = $this->getCart();

        unset($cart['items'][$itemKey]);

        $cart['subtotal'] = $this->calculateSubtotal($cart['items']);

        if (empty($cart['items'])) {
            $cart['restaurant_id'] = null;
            $cart['branch_id'] = null;
        }

        Session::put(self::SESSION_KEY, $cart);

        return $this->getCart();
    }

    public function clear(): array
    {
        Session::forget(self::SESSION_KEY);

        return $this->getCart();
    }

    public function setBranch(int $branchId): array
    {
        $cart = $this->getCart();
        $branch = RestaurantBranch::findOrFail($branchId);

        if ($cart['restaurant_id'] && $cart['restaurant_id'] !== $branch->restaurant_id) {
            throw new \Exception('Branch does not belong to the current restaurant');
        }

        $cart['branch_id'] = $branchId;

        foreach ($cart['items'] as $key => $item) {
            $menuItem = MenuItem::find($item['menu_item_id']);
            if ($menuItem) {
                $newPrice = $menuItem->getPriceAtBranch($branchId);
                $cart['items'][$key]['price'] = $newPrice;
                $cart['items'][$key]['final_price'] = $newPrice + $item['options_price'];
            }
        }

        $cart['subtotal'] = $this->calculateSubtotal($cart['items']);

        Session::put(self::SESSION_KEY, $cart);

        return $this->getCart();
    }

    public function validateForCheckout(): array
    {
        $cart = $this->getCart();

        if (empty($cart['items'])) {
            return ['valid' => false, 'message' => 'Cart is empty'];
        }

        if (! $cart['branch_id']) {
            return ['valid' => false, 'message' => 'No delivery branch selected'];
        }

        $branch = RestaurantBranch::with('deliveryZones')->find($cart['branch_id']);
        if (! $branch || ! $branch->accepts_orders || ! $branch->is_active) {
            return ['valid' => false, 'message' => 'Selected branch is not accepting orders'];
        }

        if (! $branch->isOpen()) {
            return ['valid' => false, 'message' => 'Selected branch is currently closed'];
        }

        if ($cart['subtotal'] < $branch->minimum_order) {
            return [
                'valid' => false,
                'message' => "Minimum order amount is {$branch->minimum_order} SAR",
                'minimum_order' => (float) $branch->minimum_order,
            ];
        }

        foreach ($cart['items'] as $item) {
            $menuItem = MenuItem::find($item['menu_item_id']);
            if (! $menuItem || ! $menuItem->isAvailableAtBranch($cart['branch_id'])) {
                return ['valid' => false, 'message' => "{$item['name']} is no longer available"];
            }
        }

        return ['valid' => true];
    }

    public function getCartForCheckout(): array
    {
        $cart = $this->getCart();

        $branch = null;
        if ($cart['branch_id']) {
            $branch = RestaurantBranch::with('deliveryZones')->find($cart['branch_id']);
        }

        $deliveryFee = 0;
        if ($branch) {
            $deliveryFee = (float) $branch->delivery_fee;
        }

        return [
            'restaurant_id' => $cart['restaurant_id'],
            'branch_id' => $cart['branch_id'],
            'branch' => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
                'delivery_fee' => $deliveryFee,
                'minimum_order' => (float) $branch->minimum_order,
                'estimated_delivery_time' => $branch->estimated_delivery_time,
            ] : null,
            'items' => array_values($cart['items']),
            'subtotal' => $cart['subtotal'],
            'delivery_fee' => $deliveryFee,
            'total' => $cart['subtotal'] + $deliveryFee,
        ];
    }

    private function calculateSubtotal(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['final_price'] * $item['quantity'];
        }

        return round($total, 2);
    }

    private function generateItemKey(int $menuItemId, array $options): string
    {
        ksort($options);
        $optionsString = '';
        foreach ($options as $optionId => $valueIds) {
            $valueIds = is_array($valueIds) ? $valueIds : [$valueIds];
            sort($valueIds);
            $optionsString .= "{$optionId}:".implode(',', $valueIds).';';
        }

        return $menuItemId.'_'.md5($optionsString);
    }
}
