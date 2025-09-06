/**
 * TabButton Component
 * 
 * Molecular component for tab navigation
 */

import React from 'react';

const TabButton = ({ 
  children,
  isActive = false,
  onClick,
  className = '',
  ...props 
}) => {
  return (
    <button
      onClick={onClick}
      className={`
        px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200
        focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2
        ${isActive 
          ? 'bg-primary text-white shadow-sm' 
          : 'text-neutral-600 hover:text-neutral-800 hover:bg-neutral-100'
        }
        ${className}
      `}
      {...props}
    >
      {children}
    </button>
  );
};

export default TabButton;