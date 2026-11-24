<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private CartService $cartService
    ) {}

    public function index(): JsonResponse
    {
        $cart = $this->cartService->getCart();

        return response()->json([
            'data' => $this->cartPayload($cart),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'menu_item_id' => 'required|integer|exists:menu_items,id',
            'quantity' => 'required|integer|min:1|max:99',
            'options' => 'nullable|array',
            'options.*' => 'array',
            'options.*.*' => 'integer',
        ]);

        try {
            $cart = $this->cartService->addItem(
                $request->integer('menu_item_id'),
                $request->integer('quantity', 1),
                $request->input('options', [])
            );

            return response()->json([
                'data' => $this->cartPayload($cart),
                'message' => 'Item added to cart',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, string $itemKey): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:0|max:99',
        ]);

        try {
            $cart = $this->cartService->updateQuantity($itemKey, $request->integer('quantity'));

            return response()->json([
                'data' => $this->cartPayload($cart),
                'message' => 'Cart updated',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy(string $itemKey): JsonResponse
    {
        $cart = $this->cartService->removeItem($itemKey);

        return response()->json([
            'data' => $this->cartPayload($cart),
            'message' => 'Item removed from cart',
        ]);
    }

    public function clear(): JsonResponse
    {
        $this->cartService->clear();

        return response()->json([
            'message' => 'Cart cleared',
        ]);
    }

    public function setBranch(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => 'required|integer|exists:restaurant_branches,id',
        ]);

        try {
            $cart = $this->cartService->setBranch($request->integer('branch_id'));

            return response()->json([
                'data' => $this->cartPayload($cart),
                'message' => 'Branch updated',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @param  array{restaurant_id: int|null, branch_id: int|null, items: array<string, array<string, mixed>>, subtotal: float}  $cart
     * @return array{restaurant_id: int|null, branch_id: int|null, items: array<int, array<string, mixed>>, subtotal: float, item_count: int}
     */
    private function cartPayload(array $cart): array
    {
        $items = [];

        foreach ($cart['items'] as $itemKey => $item) {
            $items[] = array_merge($item, ['item_key' => (string) $itemKey]);
        }

        return [
            'restaurant_id' => $cart['restaurant_id'],
            'branch_id' => $cart['branch_id'],
            'items' => $items,
            'subtotal' => $cart['subtotal'],
            'item_count' => $this->cartService->getItemCount(),
        ];
    }

    public function validateCheckout(): JsonResponse
    {
        $result = $this->cartService->validateForCheckout();

        if (! $result['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $result['message'],
                'minimum_order' => $result['minimum_order'] ?? null,
            ], 400);
        }

        return response()->json([
            'valid' => true,
            'data' => $this->cartService->getCartForCheckout(),
        ]);
    }

    public function checkout(): JsonResponse
    {
        $result = $this->cartService->validateForCheckout();

        if (! $result['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $result['message'],
            ], 400);
        }

        $cartData = $this->cartService->getCartForCheckout();

        return response()->json([
            'valid' => true,
            'data' => $cartData,
        ]);
    }
}
