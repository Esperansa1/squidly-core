import React, { useState, useRef, useEffect } from 'react';
import { ChevronDownIcon } from '@heroicons/react/24/outline';
import { DEFAULT_THEME } from '../../config/theme.js';

const DropdownButton = ({
  options = [],
  value = '',
  onChange = () => {},
  placeholder = 'בחר אפשרות...',
  disabled = false,
  className = '',
  dropdownClassName = '',
  optionClassName = '',
  getOptionLabel = (option) => option.label || option.name || option,
  getOptionValue = (option) => option.value || option.id || option,
  maxHeight = '200px',
  width = 'w-full',
  direction = 'left' // 'left' or 'right' for dropdown positioning
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef(null);
  const theme = DEFAULT_THEME;

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  const handleOptionClick = (option) => {
    const optionValue = getOptionValue(option);
    onChange(optionValue, option);
    setIsOpen(false);
  };

  const handleButtonClick = () => {
    if (!disabled) {
      setIsOpen(!isOpen);
    }
  };

  // Find the selected option for display
  const selectedOption = options.find(option => getOptionValue(option) === value);
  const displayText = selectedOption ? getOptionLabel(selectedOption) : placeholder;

  const buttonStyles = {
    backgroundColor: disabled ? theme.bg_gray_100 : theme.bg_white,
    border: `1px solid ${theme.border_color}`,
    color: disabled ? theme.text_disabled : (selectedOption ? theme.text_primary : theme.text_secondary),
    cursor: disabled ? 'not-allowed' : 'pointer'
  };

  const dropdownStyles = {
    backgroundColor: theme.bg_white,
    border: `1px solid ${theme.border_color}`,
    maxHeight: maxHeight
  };

  return (
    <div ref={dropdownRef} className={`relative ${width} ${className}`}>
      {/* Dropdown Button */}
      <button
        type="button"
        onClick={handleButtonClick}
        disabled={disabled}
        className={`flex items-center justify-between gap-2 px-3 py-2 rounded-md text-right hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 w-full ${
          disabled ? 'opacity-50 cursor-not-allowed' : ''
        }`}
        style={buttonStyles}
        onFocus={(e) => !disabled && (e.target.style.boxShadow = `0 0 0 2px ${theme.primary_color}40`)}
        onBlur={(e) => e.target.style.boxShadow = 'none'}
      >
        <ChevronDownIcon
          className={`w-4 h-4 text-gray-500 transition-transform flex-shrink-0 ${
            isOpen ? 'rotate-180' : ''
          }`}
        />
        <span className="text-sm truncate">{displayText}</span>
      </button>

      {/* Dropdown Menu */}
      {isOpen && !disabled && (
        <div
          className={`absolute top-full mt-1 ${direction === 'right' ? 'right-0' : 'left-0'} ${width} bg-white rounded-lg shadow-lg border border-gray-200 z-50 overflow-hidden ${dropdownClassName}`}
          style={dropdownStyles}
        >
          <div className="overflow-y-auto" style={{ maxHeight: maxHeight }}>
            {options.length === 0 ? (
              <div className="px-4 py-2 text-sm text-center" style={{ color: theme.text_secondary }}>
                אין אפשרויות זמינות
              </div>
            ) : (
              options.map((option, index) => {
                const optionValue = getOptionValue(option);
                const optionLabel = getOptionLabel(option);
                const isSelected = optionValue === value;

                return (
                  <button
                    key={optionValue || index}
                    onClick={() => handleOptionClick(option)}
                    className={`block w-full text-right px-4 py-2 text-sm transition-colors hover:bg-gray-50 ${
                      index === 0 ? 'first:rounded-t-lg' : ''
                    } ${
                      index === options.length - 1 ? 'last:rounded-b-lg' : ''
                    } ${optionClassName}`}
                    style={{
                      backgroundColor: isSelected ? theme.bg_gray_50 : 'transparent',
                      color: isSelected ? theme.primary_color : theme.text_primary
                    }}
                  >
                    {optionLabel}
                  </button>
                );
              })
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default DropdownButton;