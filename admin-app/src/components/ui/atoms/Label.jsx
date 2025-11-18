/**
 * Label Component
 *
 * Form label with required indicator support
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Label = ({
  htmlFor,
  children,
  required = false,
  disabled = false,
  size = 'md',
  className = '',
  ...props
}) => {
  const theme = DEFAULT_THEME;

  // Size variants
  const sizeClasses = {
    sm: 'text-sm',
    md: 'text-base',
    lg: 'text-lg'
  };

  const labelSize = sizeClasses[size] || sizeClasses.md;

  // Dynamic style
  const getLabelStyle = () => ({
    color: disabled ? theme.text_muted : theme.text_primary
  });

  return (
    <label
      htmlFor={htmlFor}
      className={`inline-block font-medium mb-1 ${labelSize} ${className}`}
      style={getLabelStyle()}
      {...props}
    >
      {children}
      {required && (
        <span className="mr-1" style={{ color: theme.danger_color }}>
          *
        </span>
      )}
    </label>
  );
};

export default Label;
