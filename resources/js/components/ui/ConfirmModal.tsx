import { useEffect } from 'react';
import Button from '@/components/ui/Button';

interface ConfirmModalProps {
    show: boolean;
    title: string;
    message: React.ReactNode;
    confirmLabel?: string;
    loading?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

export function ConfirmModal({ show, title, message, confirmLabel = 'Confirm', loading = false, onConfirm, onCancel }: ConfirmModalProps) {
    useEffect(() => {
        if (!show) return;
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && !loading) onCancel();
        };
        document.addEventListener('keydown', onKeyDown);
        document.body.classList.add('modal-open');
        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.classList.remove('modal-open');
        };
    }, [show, loading, onCancel]);

    if (!show) return null;

    return (
        <>
            <div className="modal fade show d-block" tabIndex={-1} role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title" onClick={() => !loading && onCancel()}>
                <div className="modal-dialog modal-dialog-centered" onClick={(event) => event.stopPropagation()}>
                    <div className="modal-content border-0 shadow-lg">
                        <div className="modal-body text-center p-4">
                            <div className="rounded-circle bg-soft-danger text-danger d-inline-flex align-items-center justify-content-center mb-3" style={{ width: 56, height: 56, fontSize: 24 }}>
                                <i className="bi bi-trash" aria-hidden="true">🗑</i>
                            </div>
                            <h5 id="confirm-modal-title" className="fw-bold text-1000">{title}</h5>
                            <p className="text-700 mb-4">{message}</p>
                            <div className="d-flex gap-2 justify-content-center">
                                <Button type="button" variant="secondary" onClick={onCancel} disabled={loading}>Cancel</Button>
                                <Button type="button" variant="danger" onClick={onConfirm} loading={loading}>{confirmLabel}</Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div className="modal-backdrop fade show" />
        </>
    );
}
