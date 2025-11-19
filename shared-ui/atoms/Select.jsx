/**
 * Select Component
 *
 * Theme-aware select dropdown with icon support
 */

import React from 'react';
import { ChevronDownIcon } from '@heroicons/react/24/outline';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Select = React.forwardRef(({
  value,
  onChange,
  options = [],
  placeholder = 'בחר אפשרות...',
  disabled = false,
  error = false,
  fullWidth = false,
  size = 'md',
  className = '',
  ...props
}, ref) => {
  const theme = DEFAULT_THEME;

  // Base classes
  const baseClasses = [
    'border',
    'rounded-lg',
    'transition-all',
    'duration-200',
    'outline-none',
    'appearance-none',
    'bg-white',
    'disabled:bg-gray-50',
    'disabled:cursor-not-allowed',
    'disabled:text-gray-500',
    'pr-10' // Space for chevron icon
  ];

  // Size variants
  const sizeClasses = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2 text-base',
    lg: 'px-5 py-3 text-lg'
  };

  // Full width option
  if (fullWidth) {
    baseClasses.push('w-full');
  }

  // Combine all classes
  const allClasses = [
    ...baseClasses,
    sizeClasses[size] || sizeClasses.md,
    className
  ].filter(Boolean).join(' ');

  // Dynamic border and focus styles
  const getSelectStyle = () => {
    if (error) {
      return {
        borderColor: theme.danger_color,
        '--tw-ring-color': theme.danger_color
      };
    }
    return {
      borderColor: theme.border_color,
      '--tw-ring-color': theme.primary_color
    };
  };

  return (
    <div className="relative inline-block">
      <select
        ref={ref}
        value={value}
        onChange={onChange}
        disabled={disabled}
        className={`${allClasses} focus:ring-2 focus:border-transparent`}
        style={getSelectStyle()}
        {...props}
      >
        {placeholder && (
          <option value="" disabled>
            {placeholder}
          </option>
        )}
        {options.map((option) => (
          <option
            key={option.value}
            value={option.value}
            disabled={option.disabled}
          >
            {option.label}
          </option>
        ))}
      </select>

      {/* Chevron Icon */}
      <div className="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
        <ChevronDownIcon
          className="w-5 h-5"
          style={{ color: disabled ? theme.text_muted : theme.text_secondary }}
        />
      </div>
    </div>
  );
});

Select.displayName = 'Select';

export default Select;
