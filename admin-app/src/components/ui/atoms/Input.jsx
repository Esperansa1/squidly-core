/**
 * Input Component
 *
 * Theme-aware input component with support for various types and states
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Input = React.forwardRef(({
  type = 'text',
  value,
  onChange,
  placeholder = '',
  disabled = false,
  error = false,
  fullWidth = false,
  size = 'md',
  leftIcon: LeftIcon = null,
  rightIcon: RightIcon = null,
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
    'disabled:bg-gray-50',
    'disabled:cursor-not-allowed',
    'disabled:text-gray-500'
  ];

  // Size variants
  const sizeClasses = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2 text-base',
    lg: 'px-5 py-3 text-lg'
  };

  // Icon padding adjustments
  const iconPaddingClasses = {
    left: {
      sm: 'pl-9',
      md: 'pl-10',
      lg: 'pl-12'
    },
    right: {
      sm: 'pr-9',
      md: 'pr-10',
      lg: 'pr-12'
    }
  };

  // Apply icon padding if icons exist
  let appliedPadding = sizeClasses[size] || sizeClasses.md;
  if (LeftIcon) {
    appliedPadding = appliedPadding.replace(/px-\d+/, iconPaddingClasses.left[size] || iconPaddingClasses.left.md);
  }
  if (RightIcon) {
    const rightPadding = iconPaddingClasses.right[size] || iconPaddingClasses.right.md;
    appliedPadding = LeftIcon
      ? `${appliedPadding} ${rightPadding}`
      : appliedPadding.replace(/px-\d+/, `${appliedPadding.split(' ')[0]} ${rightPadding}`);
  }

  // Full width option
  if (fullWidth) {
    baseClasses.push('w-full');
  }

  // Combine all classes
  const allClasses = [
    ...baseClasses,
    appliedPadding,
    className
  ].filter(Boolean).join(' ');

  // Dynamic border and focus styles
  const getInputStyle = () => {
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

  // Icon size based on input size
  const iconSizeMap = {
    sm: 'w-4 h-4',
    md: 'w-5 h-5',
    lg: 'w-6 h-6'
  };
  const iconSize = iconSizeMap[size] || 'w-5 h-5';

  // Icon position classes
  const iconPositionMap = {
    sm: { left: 'left-3', right: 'right-3' },
    md: { left: 'left-3', right: 'right-3' },
    lg: { left: 'left-4', right: 'right-4' }
  };
  const iconPosition = iconPositionMap[size] || iconPositionMap.md;

  return (
    <div className="relative">
      {/* Left Icon */}
      {LeftIcon && (
        <div className={`absolute ${iconPosition.left} top-1/2 transform -translate-y-1/2 pointer-events-none`}>
          <LeftIcon
            className={iconSize}
            style={{ color: error ? theme.danger_color : theme.text_muted }}
          />
        </div>
      )}

      {/* Input Element */}
      <input
        ref={ref}
        type={type}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        disabled={disabled}
        className={`${allClasses} focus:ring-2 focus:border-transparent`}
        style={getInputStyle()}
        {...props}
      />

      {/* Right Icon */}
      {RightIcon && (
        <div className={`absolute ${iconPosition.right} top-1/2 transform -translate-y-1/2 pointer-events-none`}>
          <RightIcon
            className={iconSize}
            style={{ color: error ? theme.danger_color : theme.text_muted }}
          />
        </div>
      )}
    </div>
  );
});

Input.displayName = 'Input';

export default Input;
