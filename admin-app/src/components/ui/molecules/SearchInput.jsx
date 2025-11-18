/**
 * SearchInput Component
 *
 * Enhanced search input with clear button
 */

import React from 'react';
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { Input, IconButton } from '../atoms';

const SearchInput = ({
  value,
  onChange,
  placeholder = 'חפש...',
  disabled = false,
  size = 'md',
  fullWidth = false,
  onClear = null,
  className = '',
  ...props
}) => {
  const handleClear = () => {
    if (onClear) {
      onClear();
    } else {
      onChange({ target: { value: '' } });
    }
  };

  const showClearButton = value && value.length > 0 && !disabled;

  return (
    <div className={`relative ${className}`}>
      <Input
        type="text"
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        disabled={disabled}
        size={size}
        fullWidth={fullWidth}
        rightIcon={MagnifyingGlassIcon}
        className={showClearButton ? 'pr-20' : ''}
        {...props}
      />
      {showClearButton && (
        <div className="absolute left-10 top-1/2 transform -translate-y-1/2">
          <IconButton
            icon={XMarkIcon}
            size="sm"
            variant="ghost"
            onClick={handleClear}
            tooltip="נקה חיפוש"
          />
        </div>
      )}
    </div>
  );
};

export default SearchInput;
