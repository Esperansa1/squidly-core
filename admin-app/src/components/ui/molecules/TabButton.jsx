/**
 * TabButton Component
 * 
 * Molecular component for tab navigation
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const TabButton = ({ 
  children,
  isActive = false,
  onClick,
  className = '',
  ...props 
}) => {
  const theme = DEFAULT_THEME;
  
  return (
    <button
      onClick={onClick}
      className={`
        px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200
        focus:outline-none focus:ring-2 focus:ring-offset-2
        ${isActive 
          ? 'text-white shadow-sm' 
          : 'text-neutral-600 hover:text-neutral-800 hover:bg-neutral-100'
        }
        ${className}
      `}
      style={{
        backgroundColor: isActive ? theme.primary_color : 'transparent',
        '--tw-ring-color': theme.primary_color,
      }}
      {...props}
    >
      {children}
    </button>
  );
};

export default TabButton;