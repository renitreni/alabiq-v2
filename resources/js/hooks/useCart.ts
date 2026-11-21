import { useState, useCallback, useMemo } from 'react';
import { usePage, router } from '@inertiajs/react';
import { cartItemSchema, type CartItemFormData } from '@/lib/validation';

interface CartItem extends CartItemFormData {
    id: string;
    menuItem: {
        id: string;
        name: string;
        price: number;
        image?: string;
        restaurantId: string;
        restaurantName: string;
    };
    subtotal: number;
}

interface CartState {
    items: CartItem[];
    restaurantId: string | null;
    deliveryFee: number;
    subtotal: number;
    total: number;
}

type FormDataConvertible = string | number | boolean | File | Blob | Date | null | undefined;

interface RequestPayload {
    [key: string]: FormDataConvertible | FormDataConvertible[] | Record<string, FormDataConvertible>;
}

export function useCart() {
    const { props } = usePage<{ cart: CartState }>();
    const [localCart, setLocalCart] = useState<CartState>(props.cart || {
        items: [],
        restaurantId: null,
        deliveryFee: 0,
        subtotal: 0,
        total: 0,
    });

    const cart = useMemo(() => props.cart || localCart, [props.cart, localCart]);

    const addItem = useCallback(
        (item: Omit<CartItem, 'id' | 'subtotal'>) => {
            const validated = cartItemSchema.parse(item);
            const newItem: CartItem = {
                ...validated,
                id: `${validated.menuItemId}-${Date.now()}`,
                menuItem: {
                    id: validated.menuItemId,
                    name: '',
                    price: 0,
                    restaurantId: '',
                    restaurantName: '',
                },
                subtotal: validated.quantity * 0, // Will be calculated server-side
            };

            // Check if from same restaurant
            if (cart.restaurantId && cart.restaurantId !== newItem.menuItem.restaurantId) {
                // Need to clear cart first - show confirmation
                return { success: false, error: 'Cannot add items from different restaurants' };
            }

            setLocalCart((prev) => {
                const existingIndex = prev.items.findIndex(
                    (i) => i.menuItemId === newItem.menuItemId && JSON.stringify(i.options) === JSON.stringify(newItem.options)
                );

                let newItems: CartItem[];
                if (existingIndex >= 0) {
                    newItems = [...prev.items];
                    newItems[existingIndex] = {
                        ...newItems[existingIndex],
                        quantity: newItems[existingIndex].quantity + newItem.quantity,
                    };
                } else {
                    newItems = [...prev.items, newItem];
                }

                return {
                    ...prev,
                    items: newItems,
                    restaurantId: newItem.menuItem.restaurantId,
                };
            });

            // Sync with server
            const payload: RequestPayload = {
                menuItemId: item.menuItemId,
                quantity: item.quantity,
                specialInstructions: item.specialInstructions || '',
            };
            if (item.options) {
                payload.options = JSON.stringify(item.options);
            }
            router.post('/cart/add', payload);
            return { success: true };
        },
        [cart.restaurantId]
    );

    const updateQuantity = useCallback(
        (itemId: string, quantity: number) => {
            if (quantity < 1) {
                removeItem(itemId);
                return;
            }

            setLocalCart((prev) => ({
                ...prev,
                items: prev.items.map((item) =>
                    item.id === itemId ? { ...item, quantity } : item
                ),
            }));

            router.patch(`/cart/${itemId}`, { quantity });
        },
        []
    );

    const removeItem = useCallback(
        (itemId: string) => {
            setLocalCart((prev) => {
                const newItems = prev.items.filter((item) => item.id !== itemId);
                return {
                    ...prev,
                    items: newItems,
                    restaurantId: newItems.length > 0 ? prev.restaurantId : null,
                };
            });

            router.delete(`/cart/${itemId}`);
        },
        []
    );

    const clearCart = useCallback(() => {
        setLocalCart({
            items: [],
            restaurantId: null,
            deliveryFee: 0,
            subtotal: 0,
            total: 0,
        });

        router.delete('/cart');
    }, []);

    const itemCount = useMemo(() => cart.items.reduce((sum, item) => sum + item.quantity, 0), [cart.items]);

    return {
        ...cart,
        addItem,
        updateQuantity,
        removeItem,
        clearCart,
        itemCount,
        isEmpty: cart.items.length === 0,
    };
}