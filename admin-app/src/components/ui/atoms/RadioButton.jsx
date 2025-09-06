/**
 * RadioButton Component
 * 
 * Atomic component for radio button inputs using Tailwind classes
 */

import React from 'react';

const RadioButton = React.forwardRef(({ 
  name, 
  value, 
  checked, 
  onChange, 
  className = '', 
  disabled = false,
  ...props 
}, ref) => {
  const handleChange = (e) => {
    if (onChange) {
      onChange(e);
    }
  };

  return (
    <div className="inline-flex items-center relative">
      <input
        ref={ref}
        type="radio"
        name={name}
        value={value}
        checked={checked}
        onChange={handleChange}
        disabled={disabled}
        className={`
          w-4 h-4 text-primary bg-white border-neutral-300 
          focus:ring-primary focus:ring-2 
          disabled:opacity-50 disabled:cursor-not-allowed
          ${className}
        `}
        {...props}
      />
    </div>
  );
});

RadioButton.displayName = 'RadioButton';

export default RadioButton;