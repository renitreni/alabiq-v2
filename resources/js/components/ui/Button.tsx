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
        const variantClasses = {
            primary: 'btn btn-primary',
            secondary: 'btn btn-secondary',
            outline: 'btn btn-outline-danger',
            ghost: 'btn btn-link',
            danger: 'btn btn-danger',
        };

        const sizeClasses = {
            sm: 'btn-sm',
            md: '',
            lg: 'btn-lg',
        };

        return (
            <Component
                ref={ref}
                className={`${variantClasses[variant]} ${sizeClasses[size]} ${className}`.trim()}
                disabled={disabled || loading}
                {...props}
            >
                {loading && (
                    <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />
                )}
                {children}
            </Component>
        );
    }
);

Button.displayName = 'Button';

export default Button;