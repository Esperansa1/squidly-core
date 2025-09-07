/**
 * Button Component
 * 
 * Clean, reusable button component with proper variant system
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Button = React.forwardRef(({ 
  variant = 'primary',
  size = 'md',
  intent = 'default', // 'default' | 'tab' - determines behavior
  className = '',
  children, 
  disabled = false,
  onClick,
  type = 'button',
  ...props 
}, ref) => {
  const theme = DEFAULT_THEME;

  // Base classes that work with Tailwind
  const baseClasses = [
    'btn-reset', // Our CSS reset class
    'inline-flex',
    'items-center', 
    'justify-center',
    'font-medium',
    'disabled:opacity-50',
    'disabled:cursor-not-allowed'
  ];

  // Intent-based behavior
  const intentClasses = {
    default: ['transition-all', 'duration-200'],
    tab: ['tab-button'] // Uses our CSS class for tab-specific behavior
  };

  // Size variants using Tailwind classes
  const sizeClasses = {
    sm: ['px-3', 'py-1.5', 'text-sm', 'rounded'],
    md: ['px-4', 'py-2', 'text-base', 'rounded-lg'], 
    lg: ['px-6', 'py-3', 'text-lg', 'rounded-lg'],
    icon: ['p-2', 'rounded-lg']
  };

  // Variant styles (still using inline styles for theme colors)
  const getVariantStyle = () => {
    switch (variant) {
      case 'primary':
        return {
          backgroundColor: theme.primary_color,
          color: 'white'
        };
      case 'secondary':
        return {
          backgroundColor: 'transparent',
          color: theme.primary_color,
          border: `1px solid ${theme.primary_color}`
        };
      case 'outline':
        return {
          backgroundColor: theme.bg_white,
          color: theme.text_primary,
          border: `1px solid ${theme.border_color}`
        };
      case 'ghost':
        return {
          backgroundColor: 'transparent',
          color: theme.text_secondary
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
          backgroundColor: theme.primary_color,
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