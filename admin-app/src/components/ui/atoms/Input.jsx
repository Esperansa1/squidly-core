/**
 * Input Component
 * 
 * Atomic component for form inputs
 */

import React from 'react';
import { cva } from 'class-variance-authority';

const inputVariants = cva(
  "block w-full rounded-lg border transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed",
  {
    variants: {
      variant: {
        default: "border-neutral-300 bg-white text-neutral-900 focus:border-primary focus:ring-primary-500",
        error: "border-error bg-white text-neutral-900 focus:border-error focus:ring-error-500",
        success: "border-success bg-white text-neutral-900 focus:border-success focus:ring-success-500",
      },
      size: {
        sm: "px-3 py-1.5 text-sm",
        md: "px-4 py-2 text-base",
        lg: "px-4 py-3 text-lg",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "md",
    },
  }
);

const Input = React.forwardRef(({ 
  className = '', 
  variant, 
  size,
  type = 'text',
  ...props 
}, ref) => {
  return (
    <input
      ref={ref}
      type={type}
      className={inputVariants({ variant, size, className })}
      {...props}
    />
  );
});

Input.displayName = 'Input';

export default Input;