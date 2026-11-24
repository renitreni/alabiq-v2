import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import api from '@/lib/axios';
import { GuestLayout } from '@/components/layout/GuestLayout';
import { EmptyState } from '@/components/ui/EmptyState';

interface MenuItem {
    id: number;
    name: string;
    description?: string | null;
    price: number;
    branch_price?: number | null;
    image?: string | null;
    category?: string | null;
}

interface MenuCategory {
    id: string;
    name: string;
    image?: string | null;
    items: MenuItem[];
}

interface Branch {
    id: string;
    name: string;
    delivery_fee: number;
    minimum_order: number;
    estimated_delivery_time: number;
    is_open: boolean;
    address?: string | null;
    opens_at?: string | null;
    closes_at?: string | null;
}

interface RestaurantData {
    id: string;
    name: string;
    slug: string;
    description: string;
    logo?: string | null;
    cover_image?: string | null;
    phone?: string | null;
    address?: string | null;
    district?: string | null;
    city?: string | null;
    estimated_delivery_time: number;
    cuisines: Array<{ id: string; name: string; icon?: string | null }>;
}

interface PageProps {
    restaurant: RestaurantData;
    branch: Branch | null;
    categories: MenuCategory[];
}

interface CartItem {
    item_key: string;
    menu_item_id: number;
    name: string;
    final_price: number;
    quantity: number;
    image?: string | null;
    category?: string | null;
}

interface CartPayload {
    items: CartItem[];
    item_count: number;
    subtotal: number;
}

const emptyCart: CartPayload = { items: [], item_count: 0, subtotal: 0 };
const fallbackFoodImage = 'https://images.unsplash.com/photo-1571091718767-18b5b1457add?w=900&h=650&fit=crop';
const money = (value: number | null | undefined) => `${Number(value ?? 0).toFixed(2)} SAR`;
const categoryEmoji = (name: string) => {
    const category = name.toLowerCase();

    if (category.includes('pizza')) return '🍕';
    if (category.includes('burger')) return '🍔';
    if (category.includes('drink') || category.includes('juice')) return '🥤';
    if (category.includes('dessert') || category.includes('ice')) return '🍨';
    if (category.includes('salad')) return '🥗';
    if (category.includes('pasta')) return '🍝';
    if (category.includes('sandwich') || category.includes('wrap')) return '🥪';

    return '🍽️';
};

export default function RestaurantShow() {
    const { restaurant, branch, categories } = usePage().props as unknown as PageProps;
    const menuSection = useRef<HTMLDivElement>(null);
    const [cart, setCart] = useState<CartPayload>(emptyCart);
    const [selectedQuantities, setSelectedQuantities] = useState<Record<number, number>>({});
    const [activeCategoryId, setActiveCategoryId] = useState<string>(categories[0]?.id ?? 'all');
    const [favorites, setFavorites] = useState<number[]>([]);
    const [adding, setAdding] = useState<number | null>(null);
    const [pendingItemKey, setPendingItemKey] = useState<string | null>(null);
    const [cartError, setCartError] = useState<string | null>(null);

    useEffect(() => {
        const loadCart = async () => {
            try {
                const { data } = await api.get('/marketplace/cart');
                setCart(data?.data ?? emptyCart);
            } catch (error) {
                console.error('Failed to load cart', error);
            }
        };

        loadCart();
    }, []);

    const addToCart = useCallback(async (menuItemId: number, quantity: number) => {
        setAdding(menuItemId);
        setCartError(null);

        try {
            const { data } = await api.post('/marketplace/cart', {
                menu_item_id: menuItemId,
                quantity,
            });

            setCart(data.data ?? emptyCart);
            setSelectedQuantities((current) => ({ ...current, [menuItemId]: 1 }));
        } catch (error) {
            setCartError(
                axios.isAxiosError<{ message?: string }>(error)
                    ? error.response?.data?.message ?? 'Could not add this item to your cart.'
                    : 'Could not add this item to your cart.'
            );
        } finally {
            setAdding(null);
        }
    }, []);

    const setSelectedQuantity = (menuItemId: number, quantity: number) => {
        setSelectedQuantities((current) => ({
            ...current,
            [menuItemId]: Math.min(99, Math.max(1, quantity)),
        }));
    };

    const updateCartQuantity = useCallback(async (item: CartItem, quantity: number) => {
        if (pendingItemKey || quantity < 0 || quantity > 99) return;

        setPendingItemKey(item.item_key);
        setCartError(null);

        try {
            const { data } = await api.put(`/marketplace/cart/${encodeURIComponent(item.item_key)}`, { quantity });
            setCart(data.data ?? emptyCart);
        } catch (error) {
            setCartError(
                axios.isAxiosError<{ message?: string }>(error)
                    ? error.response?.data?.message ?? 'Could not update your order. Please try again.'
                    : 'Could not update your order. Please try again.'
            );
        } finally {
            setPendingItemKey(null);
        }
    }, [pendingItemKey]);

    const cartQuantities = useMemo(
        () => cart.items.reduce<Record<number, number>>((quantities, item) => {
            quantities[item.menu_item_id] = (quantities[item.menu_item_id] ?? 0) + item.quantity;
            return quantities;
        }, {}),
        [cart.items]
    );
    const activeCategory = categories.find((category) => category.id === activeCategoryId);
    const visibleCategories = activeCategoryId === 'all'
        ? categories
        : categories.filter((category) => category.id === activeCategoryId);
    const visibleItems = visibleCategories.flatMap((category) =>
        category.items.map((item) => ({ item, categoryName: category.name }))
    );
    const deliveryFee = Number(branch?.delivery_fee ?? 0);
    const subtotal = Number(cart.subtotal ?? 0);
    const total = subtotal + deliveryFee;
    const hasItems = cart.item_count > 0;
    const estimatedTime = branch?.estimated_delivery_time ?? restaurant.estimated_delivery_time;
    const menuHeading = activeCategoryId === 'all'
        ? 'Explore the full menu'
        : `Choose your ${activeCategory?.name.toLowerCase() ?? 'favorites'}`;

    const toggleFavorite = (menuItemId: number) => {
        setFavorites((current) => current.includes(menuItemId)
            ? current.filter((id) => id !== menuItemId)
            : [...current, menuItemId]
        );
    };

    return (
        <GuestLayout title={`${restaurant.name} - Alabiq`} description={restaurant.description}>
            <div className="restaurant-show">
                <div className="restaurant-show__shell">
                    <header className="restaurant-show__restaurant">
                        <div className="restaurant-show__identity">
                            <div className="restaurant-show__logo">
                                {restaurant.logo ? (
                                    <img src={restaurant.logo} alt="" />
                                ) : (
                                    <i className="fa-solid fa-utensils" aria-hidden="true" />
                                )}
                            </div>
                            <div>
                                <span className="restaurant-show__eyebrow">Order from</span>
                                <h1>{restaurant.name}</h1>
                            </div>
                        </div>
                        <div className="restaurant-show__restaurant-meta">
                            {branch && (
                                <span className={`restaurant-show__status ${branch.is_open ? 'is-open' : 'is-closed'}`}>
                                    <span aria-hidden="true" />
                                    {branch.is_open ? 'Open now' : 'Currently closed'}
                                </span>
                            )}
                            <button
                                type="button"
                                className="restaurant-show__menu-link"
                                onClick={() => menuSection.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })}
                            >
                                View menu <i className="fa-solid fa-arrow-down" aria-hidden="true" />
                            </button>
                        </div>
                    </header>

                    {cartError && (
                        <div className="restaurant-show__alert" role="alert">
                            <i className="fa-solid fa-circle-exclamation" aria-hidden="true" />
                            <span>{cartError}</span>
                            <button type="button" onClick={() => setCartError(null)} aria-label="Dismiss cart error">
                                <i className="fa-solid fa-xmark" aria-hidden="true" />
                            </button>
                        </div>
                    )}

                    {categories.length ? (
                        <div className="restaurant-show__layout">
                            <div className="restaurant-show__content">
                                <div className="restaurant-show__categories" role="tablist" aria-label="Menu categories">
                                    {categories.map((category) => {
                                        const isActive = activeCategoryId === category.id;

                                        return (
                                            <button
                                                key={category.id}
                                                type="button"
                                                role="tab"
                                                aria-selected={isActive}
                                                className={`restaurant-show__category ${isActive ? 'is-active' : ''}`}
                                                onClick={() => setActiveCategoryId(category.id)}
                                            >
                                                <span className="restaurant-show__category-image">
                                                    {category.image ? (
                                                        <img src={category.image} alt="" />
                                                    ) : (
                                                        <span role="img" aria-hidden="true">{categoryEmoji(category.name)}</span>
                                                    )}
                                                </span>
                                                <span>{category.name}</span>
                                            </button>
                                        );
                                    })}
                                </div>

                                <div className="restaurant-show__menu-heading" ref={menuSection}>
                                    <div>
                                        <span className="restaurant-show__eyebrow">Freshly made for you</span>
                                        <h2>{menuHeading}</h2>
                                    </div>
                                    <button
                                        type="button"
                                        className={activeCategoryId === 'all' ? 'is-active' : ''}
                                        onClick={() => setActiveCategoryId('all')}
                                    >
                                        See all <i className="fa-solid fa-arrow-right" aria-hidden="true" />
                                    </button>
                                </div>

                                <div className="restaurant-show__food-grid" role="tabpanel">
                                    {visibleItems.map(({ item, categoryName }, index) => {
                                        const selectedQuantity = selectedQuantities[item.id] ?? 1;
                                        const quantityInCart = cartQuantities[item.id] ?? 0;
                                        const isFavorite = favorites.includes(item.id);
                                        const tags = [...new Set([
                                            categoryName,
                                            ...restaurant.cuisines.map((cuisine) => cuisine.name),
                                        ])].slice(0, 3);

                                        return (
                                            <article
                                                key={item.id}
                                                className="restaurant-show__food-card"
                                                style={{ animationDelay: `${Math.min(index, 8) * 55}ms` }}
                                            >
                                                <div className="restaurant-show__food-image">
                                                    <img
                                                        src={item.image ?? restaurant.cover_image ?? fallbackFoodImage}
                                                        alt={item.name}
                                                        loading="lazy"
                                                    />
                                                    <span className="restaurant-show__food-category">
                                                        <i className="fa-solid fa-star" aria-hidden="true" /> {categoryName}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        className={`restaurant-show__favorite ${isFavorite ? 'is-active' : ''}`}
                                                        aria-label={`${isFavorite ? 'Remove' : 'Add'} ${item.name} ${isFavorite ? 'from' : 'to'} favorites`}
                                                        aria-pressed={isFavorite}
                                                        onClick={() => toggleFavorite(item.id)}
                                                    >
                                                        <i className={`fa-${isFavorite ? 'solid' : 'regular'} fa-heart`} aria-hidden="true" />
                                                    </button>
                                                    {quantityInCart > 0 && (
                                                        <span className="restaurant-show__in-cart">{quantityInCart} in your order</span>
                                                    )}
                                                </div>

                                                <div className="restaurant-show__food-body">
                                                    <div className="restaurant-show__food-title">
                                                        <h3>
                                                            {item.name}
                                                            <i className="fa-solid fa-circle-check" title="Available to order" aria-label="Available to order" />
                                                        </h3>
                                                        <strong>{money(item.branch_price ?? item.price)}</strong>
                                                    </div>
                                                    {item.description && <p>{item.description}</p>}
                                                    <div className="restaurant-show__food-meta">
                                                        <span>
                                                            <i className="fa-solid fa-motorcycle" aria-hidden="true" />
                                                            {deliveryFee === 0 ? 'Free delivery' : `${money(deliveryFee)} delivery`}
                                                        </span>
                                                        <span>
                                                            <i className="fa-regular fa-clock" aria-hidden="true" />
                                                            {estimatedTime}–{estimatedTime + 5} mins
                                                        </span>
                                                    </div>
                                                    <div className="restaurant-show__tags">
                                                        {tags.map((tag) => <span key={tag}>{tag}</span>)}
                                                    </div>
                                                    <div className="restaurant-show__food-actions">
                                                        <div className="restaurant-show__quantity" aria-label={`Quantity for ${item.name}`}>
                                                            <button
                                                                type="button"
                                                                aria-label={`Decrease ${item.name} quantity`}
                                                                disabled={selectedQuantity <= 1 || adding !== null}
                                                                onClick={() => setSelectedQuantity(item.id, selectedQuantity - 1)}
                                                            >
                                                                <i className="fa-solid fa-minus" aria-hidden="true" />
                                                            </button>
                                                            <span aria-live="polite">{selectedQuantity}</span>
                                                            <button
                                                                type="button"
                                                                aria-label={`Increase ${item.name} quantity`}
                                                                disabled={selectedQuantity >= 99 || adding !== null}
                                                                onClick={() => setSelectedQuantity(item.id, selectedQuantity + 1)}
                                                            >
                                                                <i className="fa-solid fa-plus" aria-hidden="true" />
                                                            </button>
                                                        </div>
                                                        <button
                                                            type="button"
                                                            className="restaurant-show__add"
                                                            disabled={adding !== null}
                                                            aria-busy={adding === item.id}
                                                            onClick={() => addToCart(item.id, selectedQuantity)}
                                                        >
                                                            {adding === item.id ? (
                                                                <span className="spinner-border spinner-border-sm" role="status" aria-label="Adding to order" />
                                                            ) : (
                                                                <>Add <i className="fa-solid fa-plus" aria-hidden="true" /></>
                                                            )}
                                                        </button>
                                                    </div>
                                                </div>
                                            </article>
                                        );
                                    })}
                                </div>
                            </div>

                            <aside className="restaurant-show__order" aria-label="Current order">
                                <div className="restaurant-show__order-heading">
                                    <div>
                                        <span>Current Order</span>
                                        <strong>{branch?.name ?? restaurant.name}</strong>
                                    </div>
                                    <div>
                                        <span>Items</span>
                                        <strong>{cart.item_count}</strong>
                                    </div>
                                </div>

                                <div className="restaurant-show__order-modes">
                                    <span className="is-active"><i className="fa-solid fa-motorcycle" aria-hidden="true" /> Delivery</span>
                                    <span><i className="fa-regular fa-clock" aria-hidden="true" /> {estimatedTime} mins</span>
                                </div>

                                <div className="restaurant-show__order-title">
                                    <h2>My Orders</h2>
                                    {hasItems && <span>{cart.item_count} item{cart.item_count === 1 ? '' : 's'}</span>}
                                </div>

                                {hasItems ? (
                                    <div className="restaurant-show__order-list">
                                        {cart.items.map((item) => (
                                            <div
                                                key={item.item_key}
                                                className="restaurant-show__order-item"
                                                aria-busy={pendingItemKey === item.item_key}
                                            >
                                                <img src={item.image ?? fallbackFoodImage} alt={item.name} />
                                                <div className="restaurant-show__order-item-details">
                                                    <div>
                                                        <h3>{item.name}</h3>
                                                        <button
                                                            type="button"
                                                            aria-label={`Remove ${item.name} from order`}
                                                            disabled={pendingItemKey !== null}
                                                            onClick={() => updateCartQuantity(item, 0)}
                                                        >
                                                            <i className="fa-regular fa-trash-can" aria-hidden="true" />
                                                        </button>
                                                    </div>
                                                    {item.category && <span>{item.category}</span>}
                                                    <div className="restaurant-show__order-item-bottom">
                                                        <div className="restaurant-show__quantity is-small">
                                                            <button
                                                                type="button"
                                                                aria-label={`Decrease ${item.name} quantity`}
                                                                disabled={pendingItemKey !== null}
                                                                onClick={() => updateCartQuantity(item, item.quantity - 1)}
                                                            >
                                                                <i className="fa-solid fa-minus" aria-hidden="true" />
                                                            </button>
                                                            <span aria-live="polite">{item.quantity}</span>
                                                            <button
                                                                type="button"
                                                                aria-label={`Increase ${item.name} quantity`}
                                                                disabled={pendingItemKey !== null || item.quantity >= 99}
                                                                onClick={() => updateCartQuantity(item, item.quantity + 1)}
                                                            >
                                                                <i className="fa-solid fa-plus" aria-hidden="true" />
                                                            </button>
                                                        </div>
                                                        <strong>{money(item.final_price * item.quantity)}</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="restaurant-show__empty-order">
                                        <div><i className="fa-solid fa-bag-shopping" aria-hidden="true" /></div>
                                        <h3>Your order is empty</h3>
                                        <p>Add something delicious from the menu and it will appear here.</p>
                                    </div>
                                )}

                                <div className="restaurant-show__summary">
                                    <div><span>Subtotal</span><strong>{money(subtotal)}</strong></div>
                                    <div>
                                        <span>Delivery fee</span>
                                        <strong>{deliveryFee === 0 ? 'Free' : money(deliveryFee)}</strong>
                                    </div>
                                    <div className="restaurant-show__total"><span>Total</span><strong>{money(total)}</strong></div>
                                </div>

                                {hasItems ? (
                                    <Link href="/customer/cart" className="restaurant-show__checkout">
                                        Continue to checkout <i className="fa-solid fa-arrow-right" aria-hidden="true" />
                                    </Link>
                                ) : (
                                    <button type="button" className="restaurant-show__checkout" disabled>
                                        Continue to checkout <i className="fa-solid fa-arrow-right" aria-hidden="true" />
                                    </button>
                                )}
                                <span className="restaurant-show__secure">
                                    <i className="fa-solid fa-shield-halved" aria-hidden="true" /> Secure checkout, made simple
                                </span>
                            </aside>
                        </div>
                    ) : (
                        <EmptyState
                            title="No menu items available"
                            description="This restaurant has no items to order at the moment."
                            icon="🚫"
                        />
                    )}
                </div>
            </div>
        </GuestLayout>
    );
}
