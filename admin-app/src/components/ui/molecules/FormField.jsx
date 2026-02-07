/**
 * FormField Component
 *
 * Combines Label + Input/Select/Textarea with error display
 */

import React from 'react';
import { Label, Input, Select, Textarea } from '../atoms';
import { DEFAULT_THEME } from '../../../config/theme.js';

const FormField = ({
  label,
  type = 'text',
  name,
  value,
  onChange,
  placeholder = '',
  required = false,
  disabled = false,
  error = null,
  helpText = '',
  fieldType = 'input',
  options = [],
  rows = 4,
  maxLength = null,
  showCharCount = false,
  leftIcon = null,
  rightIcon = null,
  size = 'md',
  fullWidth = true,
  className = '',
  ...props
}) => {
  const theme = DEFAULT_THEME;

  // Determine which input component to render
  const renderField = () => {
    const commonProps = {
      name,
      value,
      onChange,
      placeholder,
      disabled,
      error: !!error,
      size,
      fullWidth,
      ...props
    };

    switch (fieldType) {
      case 'textarea':
        return (
          <Textarea
            {...commonProps}
            rows={rows}
            maxLength={maxLength}
            showCharCount={showCharCount}
          />
        );
      case 'select':
        return (
          <Select
            {...commonProps}
            options={options}
          />
        );
      case 'input':
      default:
        return (
          <Input
            {...commonProps}
            type={type}
            leftIcon={leftIcon}
            rightIcon={rightIcon}
          />
        );
    }
  };

  return (
    <div className={`flex flex-col gap-1 ${className}`} data-has-error={error ? 'true' : undefined}>
      {/* Label */}
      {label && (
        <Label
          htmlFor={name}
          required={required}
          disabled={disabled}
          size={size}
        >
          {label}
        </Label>
      )}

      {/* Field */}
      {renderField()}

      {/* Help Text */}
      {helpText && !error && (
        <p className="text-sm" style={{ color: theme.text_muted }}>
          {helpText}
        </p>
      )}

      {/* Error Message */}
      {error && (
        <p className="text-sm" style={{ color: theme.danger_color }}>
          {error}
        </p>
      )}
    </div>
  );
};

export default FormField;
