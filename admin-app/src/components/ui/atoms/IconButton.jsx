/**
 * IconButton Component
 *
 * Button optimized for icon-only usage with proper sizing and variants
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const IconButton = React.forwardRef(({
  icon: Icon,
  variant = 'ghost',
  size = 'md',
  disabled = false,
  loading = false,
  tooltip = '',
  onClick,
  className = '',
  ...props
}, ref) => {
  const theme = DEFAULT_THEME;

  // Base classes
  const baseClasses = [
    'inline-flex',
    'items-center',
    'justify-center',
    'rounded-lg',
    'transition-all',
    'duration-200',
    'disabled:opacity-50',
    'disabled:cursor-not-allowed',
    'hover:opacity-90'
  ];

  // Size variants
  const sizeClasses = {
    xs: 'w-6 h-6 p-1',
    sm: 'w-8 h-8 p-1.5',
    md: 'w-10 h-10 p-2',
    lg: 'w-12 h-12 p-3',
    xl: 'w-14 h-14 p-3.5'
  };

  // Icon sizes
  const iconSizeMap = {
    xs: 'w-4 h-4',
    sm: 'w-5 h-5',
    md: 'w-6 h-6',
    lg: 'w-6 h-6',
    xl: 'w-7 h-7'
  };

  const iconSize = iconSizeMap[size] || 'w-6 h-6';

  // Variant styles
  const getVariantStyle = () => {
    switch (variant) {
      case 'primary':
        return {
          backgroundColor: 'var(--theme-primary-color)',
          color: 'white'
        };
      case 'secondary':
        return {
          backgroundColor: 'transparent',
          color: 'var(--theme-primary-color)',
          border: '1px solid var(--theme-primary-color)'
        };
      case 'outline':
        return {
          backgroundColor: 'var(--theme-bg-white)',
          color: 'var(--theme-text-primary)',
          border: '1px solid var(--theme-border-color)'
        };
      case 'ghost':
        return {
          backgroundColor: 'transparent',
          color: 'var(--theme-text-secondary)'
        };
      case 'success':
        return {
          backgroundColor: theme.success_color,
          color: 'white'
        };
      case 'warning':
        return {
          backgroundColor: theme.warning_color,
          color: 'white'
        };
      case 'error':
        return {
          backgroundColor: theme.danger_color,
          color: 'white'
        };
      default:
        return {
          backgroundColor: 'transparent',
          color: theme.text_secondary
        };
    }
  };

  // Combine all classes
  const allClasses = [
    ...baseClasses,
    sizeClasses[size] || sizeClasses.md,
    className
  ].filter(Boolean).join(' ');

  return (
    <button
      ref={ref}
      type="button"
      className={allClasses}
      style={getVariantStyle()}
      disabled={disabled || loading}
      onClick={onClick}
      title={tooltip}
      aria-label={tooltip}
      {...props}
    >
      {loading ? (
        <svg
          className={`animate-spin ${iconSize}`}
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
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
      ) : (
        Icon && <Icon className={iconSize} />
      )}
    </button>
  );
});

IconButton.displayName = 'IconButton';

export default IconButton;
