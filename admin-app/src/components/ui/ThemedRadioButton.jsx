/**
 * Custom Radio Button Component
 * 
 * Creates a completely custom radio button that bypasses all browser and CSS framework styling
 */

import React from 'react';
import { DEFAULT_THEME } from '../../config/theme.js';

const ThemedRadioButton = ({ 
  name, 
  value, 
  checked, 
  onChange, 
  className = '', 
  apiTheme = {},
  ...props 
}) => {
  const theme = DEFAULT_THEME;
  
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
          border: `2px solid ${checked ? theme.primary_color : theme.border_light}`,
          backgroundColor: checked ? theme.primary_color : theme.bg_white,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          transition: 'all 0.2s ease-in-out',
          position: 'relative',
          boxShadow: checked ? `0 0 0 2px ${theme.primary_color}` : 'none'
        }}
        onMouseEnter={(e) => {
          if (!checked) {
            e.target.style.borderColor = theme.primary_color;
          }
        }}
        onMouseLeave={(e) => {
          if (!checked) {
            e.target.style.borderColor = theme.border_light;
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
              backgroundColor: theme.bg_white
            }}
          />
        )}
      </div>
    </div>
  );
};

export default ThemedRadioButton;