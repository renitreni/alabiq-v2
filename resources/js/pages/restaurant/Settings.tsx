import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import RestaurantOperationsForm, {
    BranchSettings,
    RestaurantOperationsSettings,
} from '@/components/forms/RestaurantOperationsForm';
import { RestaurantLayout } from '@/components/layout/RestaurantLayout';
import { Card, CardBody } from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import { PageScaffold } from '@/components/ui/PageScaffold';
import api from '@/lib/axios';

interface AuthUser {
    id: number | string;
    name: string;
    restaurant?: { name: string; logo?: string | null } | null;
}

export default function Settings() {
    const { auth } = usePage().props as unknown as { auth: { user?: AuthUser | null } };
    const restaurant = auth?.user?.restaurant;
    const [settings, setSettings] = useState<RestaurantOperationsSettings | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [reloadKey, setReloadKey] = useState(0);

    useEffect(() => {
        let cancelled = false;

        const loadSettings = async () => {
            setLoading(true);
            setError(null);

            try {
                const { data } = await api.get('/restaurant/settings');
                if (!cancelled) {
                    setSettings(data.data as RestaurantOperationsSettings);
                }
            } catch (e: any) {
                if (!cancelled) {
                    setError(e?.response?.data?.message ?? 'Could not load restaurant settings.');
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        };

        loadSettings();

        return () => {
            cancelled = true;
        };
    }, [reloadKey]);

    const handleSaved = (updatedBranch: BranchSettings) => {
        setSettings((current) => {
            if (!current) {
                return current;
            }

            return {
                ...current,
                branches: current.branches.map((branch) => (
                    branch.id === updatedBranch.id ? updatedBranch : branch
                )),
            };
        });
    };

    return (
        <RestaurantLayout
            title="Restaurant Settings - Alabiq"
            restaurant={restaurant ? { name: restaurant.name, logo: restaurant.logo ?? undefined } : { name: auth?.user?.name ?? 'Restaurant' }}
        >
            <PageScaffold
                eyebrow="Settings"
                title="Restaurant Settings"
                description="Manage branch availability, delivery terms, and available payment methods."
            >
                {loading ? (
                    <Card>
                        <CardBody className="p-4 text-700">Loading restaurant settings…</CardBody>
                    </Card>
                ) : error ? (
                    <Card variant="outlined">
                        <CardBody className="p-4">
                            <div className="alert alert-danger mb-3" role="alert">{error}</div>
                            <Button variant="secondary" onClick={() => setReloadKey((current) => current + 1)}>
                                Try Again
                            </Button>
                        </CardBody>
                    </Card>
                ) : settings ? (
                    <RestaurantOperationsForm settings={settings} onSaved={handleSaved} />
                ) : null}
            </PageScaffold>
        </RestaurantLayout>
    );
}
