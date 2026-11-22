import React from 'react';

interface PageScaffoldProps {
    title: string;
    description?: string;
    eyebrow?: string;
    actions?: React.ReactNode;
    children?: React.ReactNode;
}

export const PageScaffold: React.FC<PageScaffoldProps> = ({
    title,
    description,
    eyebrow,
    actions,
    children,
}) => (
    <div className="container py-4 animate-fade-in">
        <div className="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                {eyebrow && <div className="text-uppercase text-danger small fw-bold mb-1">{eyebrow}</div>}
                <h1 className="fs-3 fw-bold text-1000 mb-1">{title}</h1>
                {description && <p className="text-700 mb-0" style={{ maxWidth: 720 }}>{description}</p>}
            </div>
            {actions && <div className="d-flex gap-2 flex-wrap">{actions}</div>}
        </div>
        {children}
    </div>
);
