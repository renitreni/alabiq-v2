import { FormEvent, useEffect, useState } from 'react';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';

export interface MenuCategoryOption {
    id: number;
    name: string;
}

export interface MenuItemFormValues {
    menu_category_id: number | '';
    category_name: string;
    name: string;
    description: string;
    image: string;
    price: string;
    compare_at_price: string;
    calories: string;
    preparation_time: string;
    sort_order: string;
    is_vegetarian: boolean;
    is_vegan: boolean;
    is_gluten_free: boolean;
    is_spicy: boolean;
    is_popular: boolean;
    is_available: boolean;
}

const defaultValues: MenuItemFormValues = {
    menu_category_id: '',
    category_name: '',
    name: '',
    description: '',
    image: '',
    price: '',
    compare_at_price: '',
    calories: '',
    preparation_time: '',
    sort_order: '0',
    is_vegetarian: false,
    is_vegan: false,
    is_gluten_free: false,
    is_spicy: false,
    is_popular: false,
    is_available: true,
};

interface MenuItemFormProps {
    categories: MenuCategoryOption[];
    initialValues?: Partial<MenuItemFormValues>;
    submitLabel: string;
    submitting: boolean;
    error?: string | null;
    onSubmit: (values: MenuItemFormValues) => void;
}

export function MenuItemForm({
    categories,
    initialValues,
    submitLabel,
    submitting,
    error,
    onSubmit,
}: MenuItemFormProps) {
    const [form, setForm] = useState<MenuItemFormValues>(defaultValues);

    useEffect(() => {
        setForm({ ...defaultValues, ...initialValues });
    }, [initialValues]);

    const updateField = (field: keyof MenuItemFormValues, value: string | number | boolean) => {
        setForm((current) => ({ ...current, [field]: value }));
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        onSubmit(form);
    };

    const toggles: Array<{ field: keyof MenuItemFormValues; label: string }> = [
        { field: 'is_available', label: 'Available' },
        { field: 'is_popular', label: 'Popular' },
        { field: 'is_spicy', label: 'Spicy' },
        { field: 'is_vegetarian', label: 'Vegetarian' },
        { field: 'is_vegan', label: 'Vegan' },
        { field: 'is_gluten_free', label: 'Gluten free' },
    ];

    return (
        <Card>
            <CardBody className="p-4">
                {error && <div className="alert alert-danger">{error}</div>}

                <form onSubmit={handleSubmit}>
                    <div className="row g-3">
                        <div className="col-lg-8">
                            <label htmlFor="name" className="form-label fw-semibold">Item name</label>
                            <input
                                id="name"
                                className="form-control"
                                value={form.name}
                                onChange={(event) => updateField('name', event.target.value)}
                                placeholder="Chicken shawarma"
                                required
                            />
                        </div>
                        <div className="col-lg-4">
                            <label htmlFor="menu_category_id" className="form-label fw-semibold">Category</label>
                            <select
                                id="menu_category_id"
                                className="form-select"
                                value={form.menu_category_id}
                                onChange={(event) => setForm((current) => ({
                                    ...current,
                                    menu_category_id: event.target.value ? Number(event.target.value) : '',
                                    category_name: event.target.value ? '' : current.category_name,
                                }))}
                            >
                                <option value="">{categories.length ? 'Select a category' : 'Create new category'}</option>
                                {categories.map((category) => (
                                    <option key={category.id} value={category.id}>{category.name}</option>
                                ))}
                            </select>
                        </div>

                        {form.menu_category_id === '' && (
                            <div className="col-12">
                                <label htmlFor="category_name" className="form-label fw-semibold">New category name</label>
                                <input
                                    id="category_name"
                                    className="form-control"
                                    value={form.category_name}
                                    onChange={(event) => updateField('category_name', event.target.value)}
                                    placeholder="Main dishes"
                                    required
                                />
                            </div>
                        )}

                        <div className="col-12">
                            <label htmlFor="description" className="form-label fw-semibold">Description</label>
                            <textarea
                                id="description"
                                className="form-control"
                                rows={3}
                                value={form.description}
                                onChange={(event) => updateField('description', event.target.value)}
                                placeholder="Describe the item and serving size"
                            />
                        </div>

                        <div className="col-12">
                            <label htmlFor="image" className="form-label fw-semibold">Image URL</label>
                            <input
                                id="image"
                                type="url"
                                className="form-control"
                                value={form.image}
                                onChange={(event) => updateField('image', event.target.value)}
                                placeholder="https://example.com/item.jpg"
                            />
                        </div>

                        <div className="col-md-6 col-lg-3">
                            <label htmlFor="price" className="form-label fw-semibold">Price (SAR)</label>
                            <input
                                id="price"
                                type="number"
                                min="0"
                                step="0.01"
                                className="form-control"
                                value={form.price}
                                onChange={(event) => updateField('price', event.target.value)}
                                required
                            />
                        </div>
                        <div className="col-md-6 col-lg-3">
                            <label htmlFor="compare_at_price" className="form-label fw-semibold">Compare-at price</label>
                            <input
                                id="compare_at_price"
                                type="number"
                                min="0"
                                step="0.01"
                                className="form-control"
                                value={form.compare_at_price}
                                onChange={(event) => updateField('compare_at_price', event.target.value)}
                            />
                        </div>
                        <div className="col-md-6 col-lg-2">
                            <label htmlFor="calories" className="form-label fw-semibold">Calories</label>
                            <input
                                id="calories"
                                type="number"
                                min="0"
                                className="form-control"
                                value={form.calories}
                                onChange={(event) => updateField('calories', event.target.value)}
                            />
                        </div>
                        <div className="col-md-6 col-lg-2">
                            <label htmlFor="preparation_time" className="form-label fw-semibold">Prep time</label>
                            <input
                                id="preparation_time"
                                className="form-control"
                                value={form.preparation_time}
                                onChange={(event) => updateField('preparation_time', event.target.value)}
                                placeholder="15 mins"
                            />
                        </div>
                        <div className="col-md-6 col-lg-2">
                            <label htmlFor="sort_order" className="form-label fw-semibold">Sort order</label>
                            <input
                                id="sort_order"
                                type="number"
                                min="0"
                                className="form-control"
                                value={form.sort_order}
                                onChange={(event) => updateField('sort_order', event.target.value)}
                            />
                        </div>

                        <div className="col-12">
                            <div className="d-flex flex-wrap gap-3">
                                {toggles.map((toggle) => (
                                    <div key={toggle.field} className="form-check">
                                        <input
                                            id={toggle.field}
                                            type="checkbox"
                                            className="form-check-input"
                                            checked={Boolean(form[toggle.field])}
                                            onChange={(event) => updateField(toggle.field, event.target.checked)}
                                        />
                                        <label htmlFor={toggle.field} className="form-check-label">{toggle.label}</label>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <Button type="submit" variant="primary" className="mt-4" loading={submitting}>
                        {submitLabel}
                    </Button>
                </form>
            </CardBody>
        </Card>
    );
}
