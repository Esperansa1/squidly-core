/**
 * Card Component
 *
 * Enhanced content container with header/footer slots, loading overlay, and hover effects
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Card = React.forwardRef(({
  className = '',
  variant = 'default',
  padding = 'md',
  header = null,
  footer = null,
  loading = false,
  hoverable = false,
  children,
  ...props
}, ref) => {
  const theme = DEFAULT_THEME;

  // Base classes
  const baseClasses = ['rounded-lg', 'border', 'transition-all', 'relative'];

  // Variant classes
  const variantClasses = {
    default: ['bg-white', 'border-gray-200', 'card-shadow'],
    outlined: ['bg-white', 'border-gray-300'],
    elevated: ['bg-white', 'border-gray-200', 'shadow-md'],
    flat: ['bg-gray-50', 'border-gray-200'],
  };

  // Hover effect
  if (hoverable) {
    baseClasses.push('hover:shadow-lg', 'hover:border-gray-300', 'cursor-pointer');
  }

  // Padding classes (only applied to body if header/footer exist)
  const paddingClasses = {
    none: 'p-0',
    sm: 'p-4',
    md: 'p-6',
    lg: 'p-8',
  };

  const bodyPadding = paddingClasses[padding] || paddingClasses.md;

  // Combine all classes
  const allClasses = [
    ...baseClasses,
    ...variantClasses[variant] || variantClasses.default,
    !header && !footer ? bodyPadding : '', // Only add padding to card if no header/footer
    className
  ].filter(Boolean).join(' ');

  return (
    <div
      ref={ref}
      className={allClasses}
      {...props}
    >
      {/* Loading Overlay */}
      {loading && (
        <div
          className="absolute inset-0 flex items-center justify-center z-10"
          style={{ backgroundColor: 'rgba(255, 255, 255, 0.8)' }}
        >
          <svg
            className="animate-spin h-8 w-8"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            style={{ color: theme.primary_color }}
          >
            <circle
              className="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              strokeWidth="4"
            />
            <path
              className="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
          </svg>
        </div>
      )}

      {/* Header */}
      {header && (
        <div className="border-b border-gray-200 px-6 py-4">
          {header}
        </div>
      )}

      {/* Body */}
      <div className={`${header || footer ? bodyPadding : ''} ${!header && !footer && padding === 'none' ? 'h-full flex flex-col' : ''}`}>
        {children}
      </div>

      {/* Footer */}
      {footer && (
        <div className="border-t border-gray-200 px-6 py-4">
          {footer}
        </div>
      )}
    </div>
  );
});

Card.displayName = 'Card';

export default Card;