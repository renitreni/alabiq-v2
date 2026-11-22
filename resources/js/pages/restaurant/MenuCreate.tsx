import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { MenuItemForm, MenuCategoryOption, MenuItemFormValues } from '@/components/forms/MenuItemForm';
import { RestaurantLayout } from '@/components/layout/RestaurantLayout';
import { PageScaffold } from '@/components/ui/PageScaffold';
import api from '@/lib/axios';

export default function MenuCreate() {
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
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        api.get('/restaurant/menu')
            .then(({ data }) => setCategories(data?.data?.categories ?? []))
            .catch((e: any) => setError(e?.response?.data?.message ?? 'Could not load menu categories.'))
            .finally(() => setLoading(false));
    }, []);

    const handleSubmit = async (values: MenuItemFormValues) => {
        setSubmitting(true);
        setError(null);

        try {
            await api.post('/restaurant/menu', {
                menu_category_id: values.menu_category_id === '' ? undefined : values.menu_category_id,
                category_name: values.menu_category_id === '' ? values.category_name : undefined,
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
            setError(firstError ?? e?.response?.data?.message ?? 'Could not save the menu item.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <RestaurantLayout
            title="Create Menu Item - Alabiq"
            restaurant={restaurant ? { name: restaurant.name, logo: restaurant.logo ?? undefined } : { name: auth?.user?.name ?? 'Restaurant' }}
        >
            <PageScaffold
                eyebrow="Menu"
                title="Create Menu Item"
                description="Add a new item to the restaurant catalog."
            >
                {loading ? (
                    <div className="alert alert-info">Loading menu categories…</div>
                ) : (
                    <MenuItemForm
                        categories={categories}
                        submitLabel="Save Menu Item"
                        submitting={submitting}
                        error={error}
                        onSubmit={handleSubmit}
                    />
                )}
            </PageScaffold>
        </RestaurantLayout>
    );
}
