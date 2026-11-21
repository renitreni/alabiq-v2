import React from 'react';

interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
    variant?: 'default' | 'outlined' | 'elevated';
    hoverable?: boolean;
}

export const Card = React.forwardRef<HTMLDivElement, CardProps>(
    ({ variant = 'default', hoverable = false, className = '', children, ...props }, ref) => {
        const variantClasses = {
            default: 'card bg-white',
            outlined: 'card border-200 bg-white',
            elevated: 'card border-0 shadow-lg bg-white',
        };

        const hoverClasses = hoverable ? 'hover-top' : '';

        return (
            <div
                ref={ref}
                className={`${variantClasses[variant]} ${hoverClasses} ${className}`.trim()}
                {...props}
            >
                {children}
            </div>
        );
    }
);

Card.displayName = 'Card';

export const CardHeader = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
    ({ className = '', children, ...props }, ref) => (
        <div ref={ref} className={`card-header border-bottom-0 bg-100 ${className}`.trim()} {...props}>
            {children}
        </div>
    )
);
CardHeader.displayName = 'CardHeader';

export const CardBody = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
    ({ className = '', children, ...props }, ref) => (
        <div ref={ref} className={`card-body ${className}`.trim()} {...props}>
            {children}
        </div>
    )
);
CardBody.displayName = 'CardBody';

export const CardFooter = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
    ({ className = '', children, ...props }, ref) => (
        <div ref={ref} className={`card-footer border-top-0 bg-100 ${className}`.trim()} {...props}>
            {children}
        </div>
    )
);
CardFooter.displayName = 'CardFooter';