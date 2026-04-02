/**
 * Button Component
 *
 * Enhanced reusable button component with icon support, loading state, and full theme integration
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Button = React.forwardRef(({
  variant = 'primary',
  size = 'md',
  intent = 'default',
  className = '',
  children,
  disabled = false,
  loading = false,
  fullWidth = false,
  leftIcon: LeftIcon = null,
  rightIcon: RightIcon = null,
  onClick,
  type = 'button',
  ...props
}, ref) => {
  const theme = DEFAULT_THEME;

  // Base classes that work with Tailwind
  const baseClasses = [
    'btn-reset',
    'inline-flex',
    'items-center',
    'justify-center',
    'font-medium',
    'disabled:opacity-50',
    'disabled:cursor-not-allowed',
    'gap-2'
  ];

  // Full width option
  if (fullWidth) {
    baseClasses.push('w-full');
  }

  // Intent-based behavior
  const intentClasses = {
    default: ['transition-all', 'duration-200', 'hover:opacity-90'],
    tab: ['tab-button']
  };

  // Size variants using Tailwind classes
  const sizeClasses = {
    xs: ['px-2', 'py-1', 'text-xs', 'rounded'],
    sm: ['px-3', 'py-1.5', 'text-sm', 'rounded'],
    md: ['px-4', 'py-2', 'text-base', 'rounded-lg'],
    lg: ['px-6', 'py-3', 'text-lg', 'rounded-lg'],
    icon: ['p-2', 'rounded-lg']
  };

  // Icon sizes based on button size
  const iconSizeMap = {
    xs: 'w-3 h-3',
    sm: 'w-4 h-4',
    md: 'w-5 h-5',
    lg: 'w-6 h-6',
    icon: 'w-5 h-5'
  };

  const iconSize = iconSizeMap[size] || 'w-5 h-5';

  // Variant styles — use CSS variable references so colors update live
  // without requiring a React re-render when the theme changes.
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
          backgroundColor: 'var(--theme-success-color)',
          color: 'white'
        };
      case 'warning':
        return {
          backgroundColor: 'var(--theme-warning-color)',
          color: 'white'
        };
      case 'error':
        return {
          backgroundColor: 'var(--theme-danger-color)',
          color: 'white'
        };
      case 'link':
        return {
          backgroundColor: 'transparent',
          color: 'var(--theme-primary-color)',
          padding: 0
        };
      default:
        return {
          backgroundColor: 'var(--theme-primary-color)',
          color: 'white'
        };
    }
  };

  // Combine all classes
  const allClasses = [
    ...baseClasses,
    ...intentClasses[intent],
    ...sizeClasses[size],
    className
  ].filter(Boolean).join(' ');

  return (
    <button
      ref={ref}
      type={type}
      className={allClasses}
      style={getVariantStyle()}
      disabled={disabled || loading}
      onClick={onClick}
      {...props}
    >
      {loading ? (
        <>
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
          {children && <span>{children}</span>}
        </>
      ) : (
        <>
          {LeftIcon && <LeftIcon className={iconSize} />}
          {children}
          {RightIcon && <RightIcon className={iconSize} />}
        </>
      )}
    </button>
  );
});

Button.displayName = 'Button';

export default Button;