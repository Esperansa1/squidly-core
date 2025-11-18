/**
 * Badge Component
 *
 * Status indicators and labels with theme-aware variants
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Badge = ({
  children,
  variant = 'default',
  size = 'md',
  rounded = true,
  className = '',
  ...props
}) => {
  const theme = DEFAULT_THEME;

  // Base classes
  const baseClasses = [
    'inline-flex',
    'items-center',
    'justify-center',
    'font-medium',
    'whitespace-nowrap'
  ];

  // Size variants
  const sizeClasses = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm',
    lg: 'px-3 py-1.5 text-base'
  };

  // Rounded options
  if (rounded) {
    baseClasses.push('rounded-full');
  } else {
    baseClasses.push('rounded');
  }

  // Variant styles
  const getVariantClasses = () => {
    switch (variant) {
      case 'primary':
        return 'bg-red-100 text-red-800';
      case 'success':
        return 'bg-green-100 text-green-800';
      case 'warning':
        return 'bg-yellow-100 text-yellow-800';
      case 'error':
      case 'danger':
        return 'bg-red-100 text-red-800';
      case 'info':
        return 'bg-blue-100 text-blue-800';
      case 'default':
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  // Combine all classes
  const allClasses = [
    ...baseClasses,
    sizeClasses[size] || sizeClasses.md,
    getVariantClasses(),
    className
  ].filter(Boolean).join(' ');

  return (
    <span className={allClasses} {...props}>
      {children}
    </span>
  );
};

export default Badge;
