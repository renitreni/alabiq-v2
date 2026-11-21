import React from 'react';

interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
    variant?: 'default' | 'outlined' | 'elevated';
    hoverable?: boolean;
}

export const Card = React.forwardRef<HTMLDivElement, CardProps>(
    ({ variant = 'default', hoverable = false, className = '', children, ...props }, ref) => {
        const baseClasses = 'rounded-xl bg-white transition-shadow duration-200';

        const variantClasses = {
            default: 'border border-[var(--alabiq-gray-200)] shadow-[var(--alabiq-shadow-sm)]',
            outlined: 'border-2 border-[var(--alabiq-gray-200)]',
            elevated: 'border-none shadow-[var(--alabiq-shadow-lg)]',
        };

        const hoverClasses = hoverable ? 'hover:shadow-[var(--alabiq-shadow-xl)] hover:-translate-y-1' : '';

        return (
            <div
                ref={ref}
                className={`${baseClasses} ${variantClasses[variant]} ${hoverClasses} ${className}`}
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
        <div
            ref={ref}
            className={`px-5 py-4 border-b border-[var(--alabiq-gray-200)] bg-[var(--alabiq-gray-50)] rounded-t-xl ${className}`}
            {...props}
        >
            {children}
        </div>
    )
);
CardHeader.displayName = 'CardHeader';

export const CardBody = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
    ({ className = '', children, ...props }, ref) => (
        <div ref={ref} className={`p-5 ${className}`} {...props}>
            {children}
        </div>
    )
);
CardBody.displayName = 'CardBody';

export const CardFooter = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
    ({ className = '', children, ...props }, ref) => (
        <div
            ref={ref}
            className={`px-5 py-4 border-t border-[var(--alabiq-gray-200)] bg-[var(--alabiq-gray-50)] rounded-b-xl ${className}`}
            {...props}
        >
            {children}
        </div>
    )
);
CardFooter.displayName = 'CardFooter';