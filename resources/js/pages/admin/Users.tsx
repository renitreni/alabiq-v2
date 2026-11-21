import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { SuperAdminLayout } from '@/components/layout/SuperAdminLayout';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
}

const ROLES = ['customer', 'restaurant', 'rider', 'super_admin'];

export default function Users() {
    const { auth } = usePage().props as unknown as {
        auth: { user?: { id: number; name: string; avatar?: string } | null };
    };
    const [users, setUsers] = useState<UserRow[]>([]);
    const [search, setSearch] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);

    const load = useCallback(async (q: string = '') => {
        try {
            const { data } = await api.get('/admin/users', { params: q ? { search: q } : {} });
            setUsers(data?.data?.data ?? []);
        } catch {
            setError('Could not load users.');
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const changeRole = async (u: UserRow, role: string) => {
        setBusyId(u.id);
        setError(null);
        setMessage(null);
        try {
            await api.patch(`/admin/users/${u.id}/role`, { role });
            setMessage(`Updated ${u.name}'s role to ${role}.`);
            await load(search);
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Role update failed.');
        } finally {
            setBusyId(null);
        }
    };

    return (
        <SuperAdminLayout title="Users - Alabiq Admin" admin={auth?.user}>
            <div className="container py-4">
                <h4 className="fw-bold mb-3">User Management</h4>
                {message && <div className="alert alert-success">{message}</div>}
                {error && <div className="alert alert-danger">{error}</div>}

                <Card>
                    <CardBody className="p-4">
                        <div className="input-group mb-3">
                            <input
                                type="text"
                                className="form-control"
                                placeholder="Search by name or email…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && load(search)}
                            />
                            <button type="button" className="btn btn-outline-primary" onClick={() => load(search)}>Search</button>
                        </div>

                        <div className="list-group list-group-flush">
                            {users.map((u) => (
                                <div key={u.id} className="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <div className="fw-semibold">{u.name}</div>
                                        <div className="small text-700">{u.email}</div>
                                    </div>
                                    <div className="d-flex align-items-center gap-2">
                                        {u.id === auth?.user?.id ? (
                                            <span className="badge bg-secondary">You (role locked)</span>
                                        ) : (
                                            <select
                                                className="form-select form-select-sm"
                                                style={{ width: 'auto' }}
                                                value={u.role}
                                                disabled={busyId === u.id}
                                                onChange={(e) => changeRole(u, e.target.value)}
                                            >
                                                {ROLES.map((r) => <option key={r} value={r}>{r.replace('_', ' ')}</option>)}
                                            </select>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {users.length === 0 && <p className="text-700 mb-0">No users found.</p>}
                        </div>
                    </CardBody>
                </Card>
            </div>
        </SuperAdminLayout>
    );
}
