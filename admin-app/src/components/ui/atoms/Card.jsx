/**
 * Card Component
 * 
 * Atomic component for content containers
 */

import React from 'react';
import { cva } from 'class-variance-authority';

const cardVariants = cva(
  "rounded-lg border transition-shadow",
  {
    variants: {
      variant: {
        default: "bg-white border-neutral-200 shadow-sm",
        outlined: "bg-white border-neutral-300",
        elevated: "bg-white border-neutral-200 shadow-md",
        flat: "bg-neutral-50 border-neutral-200",
      },
      padding: {
        none: "p-0",
        sm: "p-4",
        md: "p-6",
        lg: "p-8",
      },
    },
    defaultVariants: {
      variant: "default",
      padding: "md",
    },
  }
);

const Card = React.forwardRef(({ 
  className = '', 
  variant, 
  padding,
  children, 
  ...props 
}, ref) => {
  return (
    <div
      ref={ref}
      className={cardVariants({ variant, padding, className })}
      {...props}
    >
      {children}
    </div>
  );
});

Card.displayName = 'Card';

export default Card;