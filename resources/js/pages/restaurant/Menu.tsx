import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { RestaurantLayout } from '@/components/layout/RestaurantLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageScaffold } from '@/components/ui/PageScaffold';
import api from '@/lib/axios';

interface MenuCategory {
    id: number;
    name: string;
}

interface MenuItemRecord {
    id: number;
    menu_category_id: number;
    name: string;
    description?: string | null;
    image?: string | null;
    price: number | string;
    compare_at_price?: number | string | null;
    calories?: number | null;
    preparation_time?: string | null;
    sort_order: number;
    is_vegetarian: boolean;
    is_vegan: boolean;
    is_gluten_free: boolean;
    is_spicy: boolean;
    is_popular: boolean;
    is_available: boolean;
    category?: MenuCategory | null;
}

const money = (value: number | string) => `${Number(value).toFixed(2)} SAR`;

export default function Menu() {
    const { auth } = usePage().props as unknown as {
        auth: {
            user?: {
                name: string;
                restaurant?: { name: string; logo?: string | null } | null;
            } | null;
        };
    };
    const restaurant = auth?.user?.restaurant;
    const [items, setItems] = useState<MenuItemRecord[]>([]);
    const [categories, setCategories] = useState<MenuCategory[]>([]);
    const [loading, setLoading] = useState(true);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const loadMenu = useCallback(async () => {
        const { data } = await api.get('/restaurant/menu');
        setItems(data?.data?.items ?? []);
        setCategories(data?.data?.categories ?? []);
    }, []);

    useEffect(() => {
        loadMenu()
            .catch((e: any) => setError(e?.response?.data?.message ?? 'Could not load the restaurant menu.'))
            .finally(() => setLoading(false));
    }, [loadMenu]);

    const updateItem = async (item: MenuItemRecord, isAvailable: boolean) => {
        setBusyId(item.id);
        setError(null);
        setNotice(null);

        try {
            await api.put(`/restaurant/menu/${item.id}`, {
                menu_category_id: item.menu_category_id,
                name: item.name,
                description: item.description ?? undefined,
                image: item.image ?? undefined,
                price: item.price,
                compare_at_price: item.compare_at_price ?? undefined,
                calories: item.calories ?? undefined,
                preparation_time: item.preparation_time ?? undefined,
                sort_order: item.sort_order,
                is_vegetarian: item.is_vegetarian,
                is_vegan: item.is_vegan,
                is_gluten_free: item.is_gluten_free,
                is_spicy: item.is_spicy,
                is_popular: item.is_popular,
                is_available: isAvailable,
            });
            setNotice(`"${item.name}" availability updated.`);
            await loadMenu();
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not update the menu item.');
        } finally {
            setBusyId(null);
        }
    };

    const removeItem = async (item: MenuItemRecord) => {
        if (!window.confirm(`Delete "${item.name}" from the menu?`)) {
            return;
        }

        setBusyId(item.id);
        setError(null);
        setNotice(null);

        try {
            await api.delete(`/restaurant/menu/${item.id}`);
            setNotice(`"${item.name}" was deleted.`);
            await loadMenu();
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not delete the menu item.');
        } finally {
            setBusyId(null);
        }
    };

    const groupedItems = categories
        .map((category) => ({
            category,
            items: items.filter((item) => item.menu_category_id === category.id),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <RestaurantLayout
            title="Restaurant Menu - Alabiq"
            restaurant={restaurant ? { name: restaurant.name, logo: restaurant.logo ?? undefined } : { name: auth?.user?.name ?? 'Restaurant' }}
        >
            <PageScaffold
                eyebrow="Menu"
                title="Menu Management"
                description="Manage categories, item pricing, availability, and catalog visibility."
                actions={<Button variant="primary" size="sm" as="a" href="/restaurant/menu/create">Add Menu Item</Button>}
            >
                {error && <div className="alert alert-danger">{error}</div>}
                {notice && <div className="alert alert-success">{notice}</div>}

                {loading ? (
                    <Card>
                        <CardBody className="p-4 text-700">Loading menu…</CardBody>
                    </Card>
                ) : items.length ? (
                    <div className="d-flex flex-column gap-4">
                        {groupedItems.map(({ category, items: categoryItems }) => (
                            <Card key={category.id}>
                                <CardBody className="p-4">
                                    <div className="d-flex justify-content-between align-items-center mb-3">
                                        <h2 className="fs-5 fw-bold text-1000 mb-0">{category.name}</h2>
                                        <span className="badge bg-soft-primary text-primary">{categoryItems.length} items</span>
                                    </div>
                                    <div className="list-group list-group-flush">
                                        {categoryItems.map((item) => (
                                            <div key={item.id} className="list-group-item px-0">
                                                <div className="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                                    <div>
                                                        <div className="d-flex align-items-center gap-2 flex-wrap">
                                                            <span className="fw-semibold text-1000">{item.name}</span>
                                                            <span className={`badge ${item.is_available ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary'}`}>
                                                                {item.is_available ? 'Available' : 'Hidden'}
                                                            </span>
                                                            {item.is_popular && <span className="badge bg-soft-warning text-dark">Popular</span>}
                                                        </div>
                                                        {item.description && <div className="small text-700 mt-1">{item.description}</div>}
                                                        <div className="small text-700 mt-1">{money(item.price)}</div>
                                                    </div>
                                                    <div className="d-flex gap-2 align-items-start">
                                                        <Button variant="outline" size="sm" as="a" href={`/restaurant/menu/${item.id}/edit`}>Edit</Button>
                                                        <Button
                                                            variant="secondary"
                                                            size="sm"
                                                            loading={busyId === item.id}
                                                            disabled={busyId !== null}
                                                            onClick={() => updateItem(item, !item.is_available)}
                                                        >
                                                            {item.is_available ? 'Hide' : 'Show'}
                                                        </Button>
                                                        <Button
                                                            variant="danger"
                                                            size="sm"
                                                            loading={busyId === item.id}
                                                            disabled={busyId !== null}
                                                            onClick={() => removeItem(item)}
                                                        >
                                                            Delete
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardBody>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        title="No menu items yet"
                        description="Create your first menu item to start building the restaurant catalog."
                        icon="🍽️"
                        action={<Button variant="primary" size="sm" as="a" href="/restaurant/menu/create">Create Menu Item</Button>}
                    />
                )}
            </PageScaffold>
        </RestaurantLayout>
    );
}
