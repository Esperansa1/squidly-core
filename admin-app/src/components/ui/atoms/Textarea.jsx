/**
 * Textarea Component
 *
 * Multi-line text input with character count support
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Textarea = React.forwardRef(({
  value,
  onChange,
  placeholder = '',
  disabled = false,
  error = false,
  fullWidth = false,
  rows = 4,
  maxLength = null,
  showCharCount = false,
  resize = 'vertical',
  className = '',
  ...props
}, ref) => {
  const theme = DEFAULT_THEME;

  // Base classes
  const baseClasses = [
    'border',
    'rounded-lg',
    'px-4',
    'py-2',
    'text-base',
    'transition-all',
    'duration-200',
    'outline-none',
    'disabled:bg-gray-50',
    'disabled:cursor-not-allowed',
    'disabled:text-gray-500'
  ];

  // Resize options
  const resizeClasses = {
    none: 'resize-none',
    vertical: 'resize-y',
    horizontal: 'resize-x',
    both: 'resize'
  };

  baseClasses.push(resizeClasses[resize] || 'resize-y');

  // Full width option
  if (fullWidth) {
    baseClasses.push('w-full');
  }

  // Combine all classes
  const allClasses = [
    ...baseClasses,
    className
  ].filter(Boolean).join(' ');

  // Dynamic border and focus styles
  const getTextareaStyle = () => {
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

  const currentLength = value ? value.length : 0;
  const isNearLimit = maxLength && currentLength >= maxLength * 0.9;
  const isAtLimit = maxLength && currentLength >= maxLength;

  return (
    <div className="relative">
      <textarea
        ref={ref}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        disabled={disabled}
        rows={rows}
        maxLength={maxLength}
        className={`${allClasses} focus:ring-2 focus:border-transparent`}
        style={getTextareaStyle()}
        {...props}
      />

      {/* Character Count */}
      {showCharCount && maxLength && (
        <div className="absolute bottom-2 left-2 text-xs" style={{
          color: isAtLimit ? theme.danger_color : isNearLimit ? theme.warning_color : theme.text_muted
        }}>
          {currentLength} / {maxLength}
        </div>
      )}
    </div>
  );
});

Textarea.displayName = 'Textarea';

export default Textarea;
