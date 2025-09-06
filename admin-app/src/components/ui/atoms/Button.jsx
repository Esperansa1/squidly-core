/**
 * Button Component
 * 
 * Atomic component following Tailwind design system
 */

import React from 'react';
import { cva } from 'class-variance-authority';

const buttonVariants = cva(
  // Base styles
  "inline-flex items-center justify-center rounded-lg font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed",
  {
    variants: {
      variant: {
        primary: "bg-primary text-white hover:bg-primary-600 focus:ring-primary-500",
        secondary: "bg-secondary-200 text-neutral-800 hover:bg-secondary-300 focus:ring-secondary-400",
        outline: "border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50 focus:ring-neutral-500",
        ghost: "text-neutral-600 hover:bg-neutral-100 focus:ring-neutral-500",
        success: "bg-success text-white hover:bg-success-600 focus:ring-success-500",
        warning: "bg-warning text-white hover:bg-warning-600 focus:ring-warning-500",
        error: "bg-error text-white hover:bg-error-600 focus:ring-error-500",
      },
      size: {
        sm: "px-3 py-1.5 text-sm",
        md: "px-4 py-2 text-base",
        lg: "px-6 py-3 text-lg",
        icon: "p-2",
      },
    },
    defaultVariants: {
      variant: "primary",
      size: "md",
    },
  }
);

const Button = React.forwardRef(({ 
  className = '', 
  variant, 
  size, 
  children, 
  disabled,
  onClick,
  type = 'button',
  ...props 
}, ref) => {
  return (
    <button
      ref={ref}
      type={type}
      className={buttonVariants({ variant, size, className })}
      disabled={disabled}
      onClick={onClick}
      {...props}
    >
      {children}
    </button>
  );
});

Button.displayName = 'Button';

export default Button;