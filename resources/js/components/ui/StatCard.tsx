import React from 'react';
import { Card, CardBody } from '@/components/ui/Card';

interface StatCardProps {
    label: string;
    value: string | number;
    hint?: string;
}

export const StatCard: React.FC<StatCardProps> = ({ label, value, hint }) => (
    <Card>
        <CardBody className="p-3 p-md-4">
            <div className="text-700 small text-uppercase fw-bold">{label}</div>
            <div className="fs-4 fw-bolder text-1000">{value}</div>
            {hint && <div className="small text-700">{hint}</div>}
        </CardBody>
    </Card>
);
