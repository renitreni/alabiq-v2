import React from 'react';
import { Card, CardBody } from '@/components/ui/Card';

interface EmptyStateProps {
    title: string;
    description?: string;
    icon?: string;
    action?: React.ReactNode;
}

export const EmptyState: React.FC<EmptyStateProps> = ({
    title,
    description,
    icon = '📭',
    action,
}) => (
    <Card variant="outlined">
        <CardBody className="p-5 text-center">
            <div className="fs-1 mb-3" aria-hidden="true">{icon}</div>
            <h2 className="fs-5 fw-bold text-1000 mb-2">{title}</h2>
            {description && <p className="text-700 mb-4" style={{ maxWidth: 520, margin: '0 auto 1.5rem' }}>{description}</p>}
            {action}
        </CardBody>
    </Card>
);
