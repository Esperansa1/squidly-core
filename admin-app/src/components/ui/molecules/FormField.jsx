/**
 * FormField Component
 * 
 * Molecular component combining label, input, and error message
 */

import React from 'react';
import { Input } from '../atoms';

const FormField = ({ 
  label,
  error,
  required = false,
  className = '',
  children,
  ...inputProps 
}) => {
  const inputId = inputProps.id || `field-${inputProps.name}`;

  return (
    <div className={`space-y-1 ${className}`}>
      {label && (
        <label 
          htmlFor={inputId}
          className="block text-sm font-medium text-neutral-700"
        >
          {label}
          {required && <span className="text-error ml-1">*</span>}
        </label>
      )}
      
      {children || (
        <Input
          id={inputId}
          variant={error ? 'error' : 'default'}
          {...inputProps}
        />
      )}
      
      {error && (
        <p className="text-sm text-error">{error}</p>
      )}
    </div>
  );
};

export default FormField;