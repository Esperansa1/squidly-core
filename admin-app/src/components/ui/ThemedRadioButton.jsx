/**
 * Custom Radio Button Component
 * 
 * Creates a completely custom radio button that bypasses all browser and CSS framework styling
 */

import React from 'react';

const ThemedRadioButton = ({ 
  name, 
  value, 
  checked, 
  onChange, 
  className = '', 
  apiTheme = {},
  ...props 
}) => {
  const primaryColor = '#D12525';
  
  // Debug log to confirm our component is rendering
  console.log('ThemedRadioButton rendering:', { name, value, checked });
  
  const handleChange = () => {
    if (onChange) {
      onChange({
        target: {
          name,
          value,
          checked: !checked,
          type: 'radio'
        }
      });
    }
  };
  
  return (
    <div className="inline-flex items-center relative">
      {/* Hidden native radio for form submission */}
      <input
        type="radio"
        name={name}
        value={value}
        checked={checked}
        onChange={onChange}
        style={{ display: 'none' }}
        {...props}
      />
      
      {/* Custom visual radio button */}
      <div
        onClick={handleChange}
        className={`cursor-pointer select-none ${className}`}
        style={{
          width: '16px',
          height: '16px',
          borderRadius: '50%',
          border: `2px solid ${checked ? primaryColor : '#d1d5db'}`,
          backgroundColor: checked ? primaryColor : '#ffffff',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          transition: 'all 0.2s ease-in-out',
          position: 'relative',
          // Add a thick border so we can see if our component is rendering
          boxShadow: checked ? `0 0 0 2px ${primaryColor}` : 'none'
        }}
        onMouseEnter={(e) => {
          if (!checked) {
            e.target.style.borderColor = primaryColor;
          }
        }}
        onMouseLeave={(e) => {
          if (!checked) {
            e.target.style.borderColor = '#d1d5db';
          }
        }}
      >
        {/* White dot in center when checked */}
        {checked && (
          <div
            style={{
              width: '6px',
              height: '6px',
              borderRadius: '50%',
              backgroundColor: '#ffffff'
            }}
          />
        )}
      </div>
    </div>
  );
};

export default ThemedRadioButton;