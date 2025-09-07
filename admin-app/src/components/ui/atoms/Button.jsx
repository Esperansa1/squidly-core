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
        primary: "text-white focus:ring-2",
        secondary: "border focus:ring-2",
        outline: "border bg-white focus:ring-2",
        ghost: "focus:ring-2",
        success: "text-white focus:ring-2",
        warning: "text-white focus:ring-2",
        error: "text-white focus:ring-2",
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
  // Get theme-based styles for each variant
  const getVariantStyles = (variant) => {
    switch (variant) {
      case 'primary':
        return {
          backgroundColor: 'var(--theme-primary-color)',
          color: 'white',
          '--tw-ring-color': 'var(--theme-primary-color)',
        };
      case 'secondary':
        return {
          backgroundColor: 'var(--theme-primary-color)',
          color: 'white',
          borderColor: 'var(--theme-primary-color)',
          '--tw-ring-color': 'var(--theme-primary-color)',
        };
      case 'outline':
        return {
          backgroundColor: 'var(--theme-bg-white)',
          color: 'var(--theme-text-primary)',
          borderColor: 'var(--theme-border-color)',
          '--tw-ring-color': 'var(--theme-border-color)',
        };
      case 'ghost':
        return {
          backgroundColor: 'transparent',
          color: 'var(--theme-text-secondary)',
          '--tw-ring-color': 'var(--theme-border-color)',
        };
      case 'success':
        return {
          backgroundColor: 'var(--theme-success-color)',
          color: 'white',
          '--tw-ring-color': 'var(--theme-success-color)',
        };
      case 'warning':
        return {
          backgroundColor: 'var(--theme-warning-color)',
          color: 'white',
          '--tw-ring-color': 'var(--theme-warning-color)',
        };
      case 'error':
        return {
          backgroundColor: 'var(--theme-danger-color)',
          color: 'white',
          '--tw-ring-color': 'var(--theme-danger-color)',
        };
      default:
        return {};
    }
  };

  return (
    <button
      ref={ref}
      type={type}
      className={buttonVariants({ variant, size, className })}
      style={getVariantStyles(variant)}
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