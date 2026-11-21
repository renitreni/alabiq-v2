import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { SuperAdminLayout } from '@/components/layout/SuperAdminLayout';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';

const BOOLEAN_KEYS = ['cod_enabled', 'online_payments_enabled', 'maintenance_mode'];

export default function Settings() {
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
    };
    const [settings, setSettings] = useState<Record<string, any>>({});
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        api.get('/admin/settings').then(({ data }) => setSettings(data?.data ?? {})).catch(() => setError('Could not load settings.'));
    }, []);

    const save = async () => {
        setBusy(true);
        setError(null);
        setMessage(null);
        try {
            const { data } = await api.put('/admin/settings', { settings });
            setSettings(data?.data ?? settings);
            setMessage('Settings saved.');
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not save settings.');
        } finally {
            setBusy(false);
        }
    };

    const renderInput = (key: string, value: any) => {
        if (BOOLEAN_KEYS.includes(key)) {
            return (
                <select className="form-select" value={String(Boolean(value))} onChange={(e) => setSettings({ ...settings, [key]: e.target.value === 'true' })}>
                    <option value="true">Enabled</option>
                    <option value="false">Disabled</option>
                </select>
            );
        }
        return (
            <input
                type="text"
                className="form-control"
                value={value ?? ''}
                onChange={(e) => setSettings({ ...settings, [key]: e.target.value })}
            />
        );
    };

    return (
        <SuperAdminLayout title="Platform Settings - Alabiq Admin" admin={auth?.user}>
            <div className="container py-4">
                <h4 className="fw-bold mb-3">Platform Settings</h4>
                {message && <div className="alert alert-success">{message}</div>}
                {error && <div className="alert alert-danger">{error}</div>}

                <Card>
                    <CardBody className="p-4">
                        {Object.keys(settings).length === 0 ? (
                            <p className="text-700 mb-0">Loading…</p>
                        ) : (
                            <>
                                <div className="row g-3">
                                    {Object.entries(settings).map(([key, value]) => (
                                        <div key={key} className="col-md-6">
                                            <label className="form-label small fw-bold text-uppercase text-700">{key.replace(/_/g, ' ')}</label>
                                            {renderInput(key, value)}
                                        </div>
                                    ))}
                                </div>
                                <button type="button" className="btn btn-primary mt-4" disabled={busy} onClick={save}>
                                    {busy ? 'Saving…' : 'Save Settings'}
                                </button>
                            </>
                        )}
                    </CardBody>
                </Card>
            </div>
        </SuperAdminLayout>
    );
}
