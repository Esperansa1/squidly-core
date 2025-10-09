/**
 * Checkbox Component
 *
 * Theme-aware checkbox with label support
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Checkbox = React.forwardRef(({
  checked = false,
  onChange,
  label = '',
  disabled = false,
  error = false,
  size = 'md',
  className = '',
  ...props
}, ref) => {
  const theme = DEFAULT_THEME;

  // Size variants
  const sizeClasses = {
    sm: 'w-4 h-4',
    md: 'w-5 h-5',
    lg: 'w-6 h-6'
  };

  const labelSizeClasses = {
    sm: 'text-sm',
    md: 'text-base',
    lg: 'text-lg'
  };

  const checkboxSize = sizeClasses[size] || sizeClasses.md;
  const labelSize = labelSizeClasses[size] || labelSizeClasses.md;

  // Dynamic styles
  const getCheckboxStyle = () => {
    const baseStyle = {
      accentColor: error ? theme.danger_color : theme.primary_color,
      cursor: disabled ? 'not-allowed' : 'pointer'
    };
    return baseStyle;
  };

  const getLabelStyle = () => ({
    color: disabled ? theme.text_muted : theme.text_primary,
    cursor: disabled ? 'not-allowed' : 'pointer'
  });

  return (
    <label className={`inline-flex items-center gap-2 ${className}`}>
      <input
        ref={ref}
        type="checkbox"
        checked={checked}
        onChange={onChange}
        disabled={disabled}
        className={`${checkboxSize} rounded border-gray-300 transition-colors focus:ring-2 focus:ring-offset-2 disabled:opacity-50`}
        style={{
          ...getCheckboxStyle(),
          '--tw-ring-color': error ? theme.danger_color : theme.primary_color
        }}
        {...props}
      />
      {label && (
        <span className={labelSize} style={getLabelStyle()}>
          {label}
        </span>
      )}
    </label>
  );
});

Checkbox.displayName = 'Checkbox';

export default Checkbox;
