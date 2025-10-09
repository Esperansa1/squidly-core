/**
 * Spinner Component
 *
 * Loading indicator in various sizes
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Spinner = ({
  size = 'md',
  color = 'primary',
  className = '',
  ...props
}) => {
  const theme = DEFAULT_THEME;

  // Size variants
  const sizeClasses = {
    xs: 'w-4 h-4',
    sm: 'w-5 h-5',
    md: 'w-8 h-8',
    lg: 'w-12 h-12',
    xl: 'w-16 h-16'
  };

  // Color mapping
  const colorMap = {
    primary: theme.primary_color,
    success: theme.success_color,
    warning: theme.warning_color,
    error: theme.danger_color,
    info: theme.info_color,
    white: '#FFFFFF'
  };

  const spinnerSize = sizeClasses[size] || sizeClasses.md;
  const spinnerColor = colorMap[color] || theme.primary_color;

  return (
    <svg
      className={`animate-spin ${spinnerSize} ${className}`}
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      style={{ color: spinnerColor }}
      {...props}
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
  );
};

export default Spinner;
