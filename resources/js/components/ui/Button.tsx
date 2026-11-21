import React, { forwardRef, ButtonHTMLAttributes, AnchorHTMLAttributes } from 'react';

type ButtonElement = HTMLButtonElement | HTMLAnchorElement;
type SharedButtonProps = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'href' | 'target' | 'rel' | 'download'> &
    Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'type' | 'form' | 'formAction' | 'formEncType' | 'formMethod' | 'formNoValidate' | 'formTarget'>;

interface ButtonProps extends SharedButtonProps {
    variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
    size?: 'sm' | 'md' | 'lg';
    loading?: boolean;
    as?: React.ElementType;
    children: React.ReactNode;
}

const Button = forwardRef<ButtonElement, ButtonProps>(
    ({ variant = 'primary', size = 'md', loading = false, as: Component = 'button', disabled, className = '', children, ...props }, ref) => {
        const baseClasses = 'inline-flex items-center justify-center font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

        const variantClasses = {
            primary: 'bg-[var(--alabiq-primary)] text-white hover:bg-[var(--alabiq-primary-hover)] focus:ring-[var(--alabiq-primary)] border border-[var(--alabiq-primary)]',
            secondary: 'bg-[var(--alabiq-secondary)] text-white hover:bg-[var(--alabiq-secondary-hover)] focus:ring-[var(--alabiq-secondary)] border border-[var(--alabiq-secondary)]',
            outline: 'border-2 border-[var(--alabiq-primary)] text-[var(--alabiq-primary)] hover:bg-[var(--alabiq-primary)] hover:text-white focus:ring-[var(--alabiq-primary)]',
            ghost: 'text-[var(--alabiq-gray-700)] hover:bg-[var(--alabiq-gray-100)] focus:ring-[var(--alabiq-gray-400)]',
            danger: 'bg-[var(--alabiq-danger)] text-white hover:bg-[var(--alabiq-danger)]/90 focus:ring-[var(--alabiq-danger)] border border-[var(--alabiq-danger)]',
        };

        const sizeClasses = {
            sm: 'px-3 py-1.5 text-sm gap-1.5',
            md: 'px-4 py-2 text-base gap-2',
            lg: 'px-6 py-3 text-lg gap-2.5',
        };

        return (
            <Component
                ref={ref}
                className={`${baseClasses} ${variantClasses[variant]} ${sizeClasses[size]} ${className}`}
                disabled={disabled || loading}
                {...props}
            >
                {loading && (
                    <svg className="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                )}
                {children}
            </Component>
        );
    }
);

Button.displayName = 'Button';

export default Button;