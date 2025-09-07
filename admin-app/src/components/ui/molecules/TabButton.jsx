/**
 * TabButton Component
 * 
 * Molecular component for tab navigation - extends base Button
 */

import React from 'react';
import Button from '../atoms/Button.jsx';
import { DEFAULT_THEME } from '../../../config/theme.js';

const TabButton = ({ 
  children,
  isActive = false,
  onClick,
  className = '',
  ...props 
}) => {
  const theme = DEFAULT_THEME;
  
  // Clean tab styles - no background color changes, only text color
  const tabStyles = {
    backgroundColor: 'transparent', // Always transparent - let sliding background handle active state
    color: isActive ? 'white' : theme.text_secondary,
  };

  return (
    <Button
      variant="ghost"
      size="sm"
      onClick={onClick}
      className={`tab-button rounded-lg font-semibold ${className}`}
      style={tabStyles}
      {...props}
    >
      {children}
    </Button>
  );
};

export default TabButton;