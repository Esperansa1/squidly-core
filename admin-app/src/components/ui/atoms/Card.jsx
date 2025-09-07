/**
 * Card Component
 * 
 * Atomic component for content containers
 */

import React from 'react';

const Card = React.forwardRef(({ 
  className = '', 
  variant = 'default',
  padding = 'md',
  children, 
  ...props 
}, ref) => {
  // Base classes
  const baseClasses = ['rounded-lg', 'border', 'transition-shadow'];
  
  // Variant classes
  const variantClasses = {
    default: ['bg-white', 'border-gray-200', 'card-shadow'],
    outlined: ['bg-white', 'border-gray-300'],
    elevated: ['bg-white', 'border-gray-200', 'shadow-md'],
    flat: ['bg-gray-50', 'border-gray-200'],
  };
  
  // Padding classes
  const paddingClasses = {
    none: ['p-0'],
    sm: ['p-4'],
    md: ['p-6'], 
    lg: ['p-8'],
  };
  
  // Combine all classes
  const allClasses = [
    ...baseClasses,
    ...variantClasses[variant] || variantClasses.default,
    ...paddingClasses[padding] || paddingClasses.md,
    className
  ].filter(Boolean).join(' ');

  return (
    <div
      ref={ref}
      className={allClasses}
      {...props}
    >
      {children}
    </div>
  );
});

Card.displayName = 'Card';

export default Card;