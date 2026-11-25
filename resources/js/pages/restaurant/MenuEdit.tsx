import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useParams } from '@/hooks/useParams';
import { MenuItemForm, MenuCategoryOption, MenuItemFormValues } from '@/components/forms/MenuItemForm';
import { RestaurantLayout } from '@/components/layout/RestaurantLayout';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';
import api from '@/lib/axios';

interface MenuItemResponse {
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
}

export default function MenuEdit() {
    const { menuItem } = useParams<{ menuItem: string }>();
    const { auth } = usePage().props as unknown as {
        auth: {
            user?: {
                name: string;
                restaurant?: { name: string; logo?: string | null } | null;
            } | null;
        };
    };
    const restaurant = auth?.user?.restaurant;
    const [categories, setCategories] = useState<MenuCategoryOption[]>([]);
    const [initialValues, setInitialValues] = useState<Partial<MenuItemFormValues>>({});
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!menuItem) return;

        const loadMenuItem = async () => {
            setLoading(true);
            setError(null);

            try {
                const [{ data: menuData }, { data: itemData }] = await Promise.all([
                    api.get('/restaurant/menu'),
                    api.get(`/restaurant/menu/${menuItem}`),
                ]);

                const item = (itemData?.data ?? null) as MenuItemResponse | null;
                setCategories(menuData?.data?.categories ?? []);

                if (!item) {
                    setError('Menu item not found.');
                    return;
                }

                setInitialValues({
                    menu_category_id: item.menu_category_id,
                    category_name: '',
                    name: item.name,
                    description: item.description ?? '',
                    image: item.image ?? '',
                    price: String(item.price),
                    compare_at_price: item.compare_at_price ? String(item.compare_at_price) : '',
                    calories: item.calories ? String(item.calories) : '',
                    preparation_time: item.preparation_time ?? '',
                    sort_order: String(item.sort_order ?? 0),
                    is_vegetarian: item.is_vegetarian,
                    is_vegan: item.is_vegan,
                    is_gluten_free: item.is_gluten_free,
                    is_spicy: item.is_spicy,
                    is_popular: item.is_popular,
                    is_available: item.is_available,
                });
            } catch (e: any) {
                setError(e?.response?.data?.message ?? 'Could not load the menu item.');
            } finally {
                setLoading(false);
            }
        };

        loadMenuItem();
    }, [menuItem]);

    const handleSubmit = async (values: MenuItemFormValues) => {
        if (!menuItem) return;

        setSubmitting(true);
        setError(null);

        try {
            await api.put(`/restaurant/menu/${menuItem}`, {
                menu_category_id: values.menu_category_id,
                name: values.name,
                description: values.description || undefined,
                image: values.image || undefined,
                price: values.price,
                compare_at_price: values.compare_at_price || undefined,
                calories: values.calories || undefined,
                preparation_time: values.preparation_time || undefined,
                sort_order: values.sort_order || 0,
                is_vegetarian: values.is_vegetarian,
                is_vegan: values.is_vegan,
                is_gluten_free: values.is_gluten_free,
                is_spicy: values.is_spicy,
                is_popular: values.is_popular,
                is_available: values.is_available,
            });

            router.visit('/restaurant/menu');
        } catch (e: any) {
            const validationErrors = e?.response?.data?.errors;
            const firstError = validationErrors ? Object.values<string[]>(validationErrors)[0]?.[0] : null;
            setError(firstError ?? e?.response?.data?.message ?? 'Could not update the menu item.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <RestaurantLayout
            title={`Edit Menu Item ${menuItem ?? ''} - Alabiq`}
            restaurant={restaurant ? { name: restaurant.name, logo: restaurant.logo ?? undefined } : { name: auth?.user?.name ?? 'Restaurant' }}
        >
            <PageScaffold
                eyebrow="Menu"
                title={`Edit Menu Item #${menuItem ?? ''}`}
                description="Update catalog details, pricing, and availability."
            >
                {loading ? (
                    <Card>
                        <CardBody className="p-4 text-700">Loading menu item…</CardBody>
                    </Card>
                ) : (
                    <MenuItemForm
                        categories={categories}
                        initialValues={initialValues}
                        submitLabel="Update Menu Item"
                        submitting={submitting}
                        error={error}
                        onSubmit={handleSubmit}
                    />
                )}
            </PageScaffold>
        </RestaurantLayout>
    );
}
