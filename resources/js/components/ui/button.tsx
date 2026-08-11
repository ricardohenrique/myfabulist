import type { ButtonHTMLAttributes, ReactNode } from 'react';

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    children: ReactNode;
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
    size?: 'sm' | 'md';
};

export function Button({
    children,
    className = '',
    variant = 'secondary',
    size = 'md',
    ...props
}: ButtonProps) {
    return (
        <button
            className={`ui-button ui-button--${variant} ui-button--${size} ${className}`}
            type="button"
            {...props}
        >
            {children}
        </button>
    );
}
